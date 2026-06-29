<?php

declare(strict_types=1);

namespace Imago\Image;

use Intervention\Image\ImageManager;

final class InterventionDriver implements ImageProcessorInterface
{
    private readonly ImageManager $manager;

    public function __construct(string $backend = 'gd')
    {
        $driverClass = match ($backend) {
            'gd' => \Intervention\Image\Drivers\Gd\Driver::class,
            'imagick' => \Intervention\Image\Drivers\Imagick\Driver::class,
            default => throw new \RuntimeException("Unknown intervention backend: {$backend}"),
        };
        $this->manager = new ImageManager(new $driverClass());
    }

    public function process(
        string $sourcePath,
        string $destPath,
        int $width,
        int $height,
        string $mode = 'resize',
        int $quality = self::DEFAULT_QUALITY,
    ): void {
        $image = $this->manager->read($sourcePath);

        if ($mode === 'crop') {
            $image->cover($width, $height);
        } else {
            $image->scale($width, $height);
        }

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }

        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        $encoded = match ($ext) {
            'jpg', 'jpeg' => $image->toJpeg($quality),
            'png' => $image->toPng(),
            'gif' => $image->toGif(),
            'webp' => $image->toWebp($quality),
            default => $image->toJpeg($quality),
        };

        $encoded->save($destPath);
    }
}
