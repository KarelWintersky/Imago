<?php

declare(strict_types=1);

namespace Imago\Image\GD;

final class Save
{
    public static function run(\GdImage $image, string $destPath, int $quality): void
    {
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }

        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));

        match ($ext) {
            'jpg', 'jpeg' => imagejpeg($image, $destPath, $quality),
            'png' => imagepng($image, $destPath, (int) round(9 - ($quality / 11))),
            'gif' => imagegif($image, $destPath),
            'webp' => imagewebp($image, $destPath, $quality),
            default => imagejpeg($image, $destPath, $quality),
        };

        imagedestroy($image);
    }
}
