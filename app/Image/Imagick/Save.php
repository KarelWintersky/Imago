<?php

declare(strict_types=1);

namespace Imago\Image\Imagick;

final class Save
{
    public static function run(\Imagick $image, string $destPath, int $quality): void
    {
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }

        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));

        match ($ext) {
            'jpg', 'jpeg' => $image->setImageFormat('jpeg'),
            'png' => $image->setImageFormat('png'),
            'gif' => $image->setImageFormat('gif'),
            'webp' => $image->setImageFormat('webp'),
            default => $image->setImageFormat('jpeg'),
        };

        $image->setImageCompressionQuality($quality);
        $image->writeImage($destPath);
        $image->clear();
    }
}
