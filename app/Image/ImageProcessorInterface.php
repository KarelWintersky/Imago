<?php

declare(strict_types=1);

namespace Imago\Image;

interface ImageProcessorInterface
{
    public const DEFAULT_QUALITY = 90;

    public function process(
        string $sourcePath,
        string $destPath,
        array $rules,
        int $quality = self::DEFAULT_QUALITY,
    ): void;
}
