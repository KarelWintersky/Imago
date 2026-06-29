<?php

declare(strict_types=1);

namespace Imago\Image;

final class ImageProcessor
{
    private readonly string $defaultDriver;
    private readonly array $config;
    private readonly GdDriver $gd;
    private readonly ImagickDriver $imagick;
    private ?InterventionDriver $intervention = null;

    public function __construct(array $config)
    {
        $this->config = $config;
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
            'intervention' => $this->intervention ??= new InterventionDriver($this->config),
            default => throw new \RuntimeException("Unknown image processor driver: {$driver}"),
        };

        $instance->process($sourcePath, $destPath, $width, $height, $mode, $quality);
    }
}
