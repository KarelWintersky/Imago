<?php

declare(strict_types=1);

namespace Imago;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;

final class RequestHandler
{
    private readonly ImageProcessor $processor;
    private readonly PlaceholderGenerator $placeholder;
    private readonly CacheManager $cache;
    private readonly Logger $fileLogger;
    private readonly ?\Psr\Log\LoggerInterface $consoleLogger;

    public function __construct(
        private readonly array $config,
        ?\Psr\Log\LoggerInterface $consoleLogger = null,
    ) {
        $this->fileLogger = new Logger($config);
        $this->consoleLogger = $consoleLogger;
        $this->cache = new CacheManager($config, $this->fileLogger);
        $this->processor = new ImageProcessor();
        $this->placeholder = new PlaceholderGenerator();
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $this->fileLogger->log($level, $message, $context);
        $this->consoleLogger?->log($level, $message, $context);
    }

    public function __invoke(Request $request): Response
    {
        try {
            return $this->handle($request);
        } catch (\Throwable $e) {
            $this->log('error', 'Request failed', [
                'uri' => (string) $request->getUri(),
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse(500, 'Internal server error');
        }
    }

    private function handle(Request $request): Response
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $query = $uri->getQuery();
        $fullUrl = $path . ($query ? '?' . $query : '');

        if ($path === '/health') {
            return $this->jsonResponse(200, ['status' => 'ok', 'time' => time()]);
        }

        $parts = explode('/', trim($path, '/'));
        if (count($parts) < 2) {
            $this->log('warning', "GET {$fullUrl} → 400 invalid path");
            return $this->errorResponse(400, 'Invalid path: /{service}/{image_path} expected');
        }

        $service = array_shift($parts);
        $relativePath = implode('/', $parts);

        if ($relativePath === '' || !isset($this->config['services'][$service])) {
            $this->log('warning', "GET {$fullUrl} → 404 service not found");
            return $this->errorResponse(404, 'Service not found');
        }

        $serviceConfig = $this->config['services'][$service];
        $storagePath = $serviceConfig['storage_path'] . '/' . $relativePath;

        parse_str($query, $params);

        try {
            [$width, $height, $mode] = $this->resolveDimensions($params, $serviceConfig);
        } catch (\Throwable $e) {
            $this->log('warning', "GET {$fullUrl} → 400 {$e->getMessage()}");
            return $this->errorResponse(400, $e->getMessage());
        }

        if (file_exists($storagePath)) {
            if ($mode === 'original') {
                $size = filesize($storagePath);
                $mime = CacheManager::detectMime($storagePath);
                $this->log('info', "GET {$fullUrl} → 200 original ({$size}B, {$mime})");
                return $this->imageResponse($storagePath, $mime);
            }

            $cacheKey = $this->cache->buildKey($service, $relativePath, $params);
            $cached = $this->cache->get($cacheKey);

            if ($cached !== null) {
                $this->log('info', "GET {$fullUrl} → 200 from cache");
                return $this->imageResponse($cached['path'], $cached['mime']);
            }

            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) ?: 'jpg';
            $cachePath = $this->cache->buildPath($cacheKey, $extension);

            $start = hrtime(true);

            $this->processor->process($storagePath, $cachePath, $width, $height, $mode);

            $elapsed = (int) ((hrtime(true) - $start) / 1_000_000);

            $mime = CacheManager::detectMime($cachePath);
            $this->cache->set($cacheKey, $cachePath, $mime);

            $this->log('info', "GET {$fullUrl} → 200 generated in {$elapsed}ms ({$width}x{$height}, {$mode})");
            return $this->imageResponse($cachePath, $mime);
        }

        if ($serviceConfig['placeholder']['enabled'] ?? false) {
            if ($width <= 0 || $height <= 0) {
                $width = 800;
                $height = 600;
            }

            $placeholderKey = $this->cache->buildKey($service, $relativePath, ['__placeholder__' => '1']);
            $placeholderPath = $this->cache->buildPath($placeholderKey, 'jpg');

            $start = hrtime(true);

            $this->placeholder->generate(
                $placeholderPath,
                $width,
                $height,
                $serviceConfig['placeholder']['color'] ?? '3d4070',
                $serviceConfig['placeholder']['background'] ?? 'ffffff',
            );

            $elapsed = (int) ((hrtime(true) - $start) / 1_000_000);

            $mime = CacheManager::detectMime($placeholderPath);
            $this->cache->set($placeholderKey, $placeholderPath, $mime);

            $this->log('info', "GET {$fullUrl} → 200 placeholder generated in {$elapsed}ms ({$width}x{$height})");
            return $this->imageResponse($placeholderPath, $mime);
        }

        $this->log('warning', "GET {$fullUrl} → 404 not found");
        return $this->errorResponse(404, 'Image not found');
    }

    private function resolveDimensions(array $params, array $serviceConfig): array
    {
        if (isset($params['profile'])) {
            $pm = new ProfileManager($serviceConfig);

            if (!$pm->has($params['profile'])) {
                throw new \RuntimeException("Unknown profile: {$params['profile']}");
            }

            return array_values($pm->resolve($params['profile']));
        }

        $width = isset($params['width']) ? (int) $params['width'] : 0;
        $height = isset($params['height']) ? (int) $params['height'] : 0;
        $mode = $params['mode'] ?? 'resize';

        if ($width <= 0 && $height <= 0) {
            return [0, 0, 'original'];
        }

        if ($width <= 0) {
            $width = $height;
        }
        if ($height <= 0) {
            $height = $width;
        }

        $maxDim = $this->config['max_dimension'] ?? 4096;

        if ($width < 1 || $height < 1) {
            throw new \RuntimeException('Dimensions must be positive');
        }
        if ($width > $maxDim || $height > $maxDim) {
            throw new \RuntimeException("Dimensions exceed maximum ({$maxDim}px)");
        }

        return [$width, $height, $mode];
    }

    private function imageResponse(string $filePath, string $mime): Response
    {
        $etag = sprintf('"%x-%x"', fileinode($filePath), filemtime($filePath));

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($filePath),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000),
        ];

        return new Response(200, $headers, file_get_contents($filePath));
    }

    private function errorResponse(int $status, string $message): Response
    {
        return new Response($status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ], json_encode(['error' => $message], JSON_UNESCAPED_UNICODE));
    }

    private function jsonResponse(int $status, array $data): Response
    {
        return new Response($status, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
