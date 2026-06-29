<?php

declare(strict_types=1);

namespace Imago\Cache;

use Imago\Logger;
use Predis\Client as RedisClient;

final class CacheManager
{
    private readonly array $config;
    private readonly Logger $logger;
    private ?RedisClient $redis = null;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function buildKey(string $service, string $path, array $params): string
    {
        ksort($params);
        $query = http_build_query($params);
        return $service . ':' . $path . ($query !== '' ? '?' . $query : '');
    }

    private function cacheDir(): string
    {
        return $this->config['cache']['files']['dir']
            ?? $this->config['cache_dir']
            ?? $this->config['root_dir'] . '/public/cache';
    }

    private function ttl(): int
    {
        return $this->config['cache']['files']['ttl']
            ?? $this->config['ttl']
            ?? 86400 * 30;
    }

    public function buildPath(string $key, string $extension): string
    {
        $hash = md5($key);
        $prefix = substr($hash, 0, 2);
        return $this->cacheDir() . '/' . $prefix . '/' . $hash . '.' . ltrim($extension, '.');
    }

    public function get(string $key): ?array
    {
        $meta = $this->getRedisMeta($key);
        if ($meta !== null && isset($meta['path']) && file_exists($meta['path'])) {
            if (time() - filemtime($meta['path']) < $this->ttl()) {
                return $meta;
            }
            @unlink($meta['path']);
        }

        return $this->findFile($key);
    }

    public function set(string $key, string $filePath, string $mime): void
    {
        $meta = json_encode(['path' => $filePath, 'mime' => $mime], JSON_UNESCAPED_SLASHES);
        $this->setRedisMeta($key, $meta);
    }

    public function touch(string $key): void
    {
        $existing = $this->findFile($key);
        if ($existing !== null) {
            @touch($existing['path']);
        }
    }

    public static function detectMime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            default => mime_content_type($path) ?: 'application/octet-stream',
        };
    }

    private function findFile(string $key): ?array
    {
        $cacheDir = $this->cacheDir();
        $hash = md5($key);
        $prefix = substr($hash, 0, 2);
        $pattern = $cacheDir . '/' . $prefix . '/' . $hash . '.*';

        $files = glob($pattern);
        if ($files === false || $files === []) {
            return null;
        }

        $path = $files[0];

        if (time() - filemtime($path) < $this->ttl()) {
            return ['path' => $path, 'mime' => self::detectMime($path)];
        }

        @unlink($path);
        return null;
    }

    private function getRedis(): ?RedisClient
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        $driver = $this->config['cache']['meta']['driver']
            ?? $this->config['driver']
            ?? 'file';

        if ($driver !== 'redis') {
            return null;
        }

        try {
            $rc = $this->config['cache']['meta']['redis'] ?? $this->config['redis'] ?? [];
            $host = $rc['host'] ?? '127.0.0.1';
            $port = $rc['port'] ?? 6379;

            $this->redis = new RedisClient("tcp://{$host}:{$port}", [
                'prefix' => $rc['prefix'] ?? 'imago:cache:',
            ]);

            $this->redis->ping();
            $this->logger->info('Redis connected');

            return $this->redis;
        } catch (\Throwable $e) {
            $this->logger->warning('Redis unavailable, falling back to file cache', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getRedisMeta(string $key): ?array
    {
        $redis = $this->getRedis();
        if ($redis === null) {
            return null;
        }

        try {
            $value = $redis->get($key);
            if ($value === null) {
                return null;
            }

            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            $this->logger->warning('Redis GET error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function setRedisMeta(string $key, string $value): void
    {
        $redis = $this->getRedis();
        if ($redis === null) {
            return;
        }

        try {
            $redis->setex($key, $this->ttl(), $value);
        } catch (\Throwable $e) {
            $this->logger->warning('Redis SET error', ['error' => $e->getMessage()]);
        }
    }
}
