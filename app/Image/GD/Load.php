<?php

declare(strict_types=1);

namespace Imago\Image\GD;

final class Load
{
    public static function run(string $sourcePath): \GdImage
    {
        $info = getimagesize($sourcePath);
        if ($info === false) {
            throw new \RuntimeException("Cannot read image: {$sourcePath}");
        }

        [, , $type] = $info;

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => throw new \RuntimeException("Unsupported image type: {$type}"),
        };

        if ($image === false) {
            throw new \RuntimeException("Failed to open image: {$sourcePath}");
        }

        return $image;
    }
}
