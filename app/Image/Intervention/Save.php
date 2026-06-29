<?php

declare(strict_types=1);

namespace Imago\Image\Intervention;

use Intervention\Image\Image;

final class Save
{
    public static function run(Image $image, string $destPath, int $quality): void
    {
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
