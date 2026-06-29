<?php

declare(strict_types=1);

namespace Imago\Image;

use Imago\Image\GD;
use Imago\Image\Imagick;
use Imago\Image\Intervention as IV;
use Intervention\Image\ImageManager;

final class ImageProcessor
{
    private readonly string $defaultDriver;
    private readonly array $config;
    private ?ImageManager $ivManager = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $default = $config['processor'] ?? 'gd';
        $this->defaultDriver = is_string($default) ? $default : 'gd';
    }

    public function process(
        string $sourcePath,
        string $destPath,
        array $rules,
        int $quality = ImageProcessorInterface::DEFAULT_QUALITY,
        ?string $driver = null,
    ): void {
        $driver ??= $this->defaultDriver;

        [$name, $backend] = str_contains($driver, ':')
            ? explode(':', $driver, 2)
            : [$driver, null];

        match ($name) {
            'gd' => $this->processGd($sourcePath, $destPath, $rules, $quality),
            'imagick' => $this->processImagick($sourcePath, $destPath, $rules, $quality),
            'intervention' => $this->processIntervention($sourcePath, $destPath, $rules, $quality, $backend ?? 'gd'),
            default => throw new \RuntimeException("Unknown image processor driver: {$driver}"),
        };
    }

    private function processGd(string $sourcePath, string $destPath, array $rules, int $quality): void
    {
        $img = GD\Load::run($sourcePath);
        $img = GD\Process::run($img, $rules);
        GD\Save::run($img, $destPath, $quality);
    }

    private function processImagick(string $sourcePath, string $destPath, array $rules, int $quality): void
    {
        $img = Imagick\Load::run($sourcePath);
        $img = Imagick\Process::run($img, $rules);
        Imagick\Save::run($img, $destPath, $quality);
    }

    private function processIntervention(string $sourcePath, string $destPath, array $rules, int $quality, string $backend): void
    {
        $driverClass = match ($backend) {
            'gd' => \Intervention\Image\Drivers\Gd\Driver::class,
            'imagick' => \Intervention\Image\Drivers\Imagick\Driver::class,
            default => throw new \RuntimeException("Unknown intervention backend: {$backend}"),
        };

        $this->ivManager ??= new ImageManager(new $driverClass());
        $img = IV\Load::run($this->ivManager, $sourcePath);
        $img = IV\Process::run($img, $rules);
        IV\Save::run($img, $destPath, $quality);
    }
}
