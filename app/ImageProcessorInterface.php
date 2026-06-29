<?php

declare(strict_types=1);

namespace Imago;

interface ImageProcessorInterface
{
    public const DEFAULT_QUALITY = 90;

    public function process(
        string $sourcePath,
        string $destPath,
        int $width,
        int $height,
        string $mode = 'resize',
        int $quality = self::DEFAULT_QUALITY,
    ): void;
}
