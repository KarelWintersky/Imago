<?php

declare(strict_types=1);

namespace Imago\Image;

final class ImagickDriver implements ImageProcessorInterface
{
    public function process(
        string $sourcePath,
        string $destPath,
        int $width,
        int $height,
        string $mode = 'resize',
        int $quality = self::DEFAULT_QUALITY,
    ): void {
        $img = new \Imagick($sourcePath);

        if ($mode === 'crop') {
            $img->cropThumbnailImage($width, $height);
        } else {
            $img->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, true);
        }

        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }

        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));

        match ($ext) {
            'jpg', 'jpeg' => $img->setImageFormat('jpeg'),
            'png' => $img->setImageFormat('png'),
            'gif' => $img->setImageFormat('gif'),
            'webp' => $img->setImageFormat('webp'),
            default => $img->setImageFormat('jpeg'),
        };

        $img->setImageCompressionQuality($quality);
        $img->writeImage($destPath);
        $img->clear();
    }
}
