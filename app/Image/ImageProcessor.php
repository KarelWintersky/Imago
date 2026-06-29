<?php

declare(strict_types=1);

namespace Imago\Image;

final class ImageProcessor
{
    private readonly string $defaultDriver;
    private readonly GdDriver $gd;
    private readonly ImagickDriver $imagick;

    /** @var array<string, InterventionDriver> */
    private array $interventions = [];

    public function __construct(array $config)
    {
        $default = $config['processor'] ?? 'gd';
        $this->defaultDriver = is_string($default) ? $default : 'gd';
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

        [$name, $backend] = str_contains($driver, ':')
            ? explode(':', $driver, 2)
            : [$driver, null];

        $instance = match ($name) {
            'gd' => $this->gd,
            'imagick' => $this->imagick,
            'intervention' => $this->interventions[$backend ?? 'gd']
                ??= new InterventionDriver($backend ?? 'gd'),
            default => throw new \RuntimeException("Unknown image processor driver: {$driver}"),
        };

        $instance->process($sourcePath, $destPath, $width, $height, $mode, $quality);
    }
}
