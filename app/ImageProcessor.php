<?php

declare(strict_types=1);

namespace Imago;

final class ImageProcessor
{
    private readonly string $defaultDriver;
    private readonly GdDriver $gd;
    private readonly ImagickDriver $imagick;

    public function __construct(array $config)
    {
        $this->defaultDriver = $config['processor']['driver'] ?? 'gd';
        $this->gd = new GdDriver();
        $this->imagick = new ImagickDriver();
    }

    public function process(
        string $sourcePath,
        string $destPath,
        int $width,
        int $height,
        string $mode = 'resize',
        int $quality = ImageProcessorInterface::DEFAULT_QUALITY,
        ?string $driver = null,
    ): void {
        $driver ??= $this->defaultDriver;

        $instance = match ($driver) {
            'gd' => $this->gd,
            'imagick' => $this->imagick,
            default => throw new \RuntimeException("Unknown image processor driver: {$driver}"),
        };

        $instance->process($sourcePath, $destPath, $width, $height, $mode, $quality);
    }
}
