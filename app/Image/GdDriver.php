<?php

declare(strict_types=1);

namespace Imago\Image;

final class GdDriver implements ImageProcessorInterface
{
    public function process(
        string $sourcePath,
        string $destPath,
        int $width,
        int $height,
        string $mode = 'resize',
        int $quality = self::DEFAULT_QUALITY,
    ): void {
        $info = getimagesize($sourcePath);
        if ($info === false) {
            throw new \RuntimeException("Cannot read image: {$sourcePath}");
        }

        [$srcW, $srcH, $type] = $info;

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            default => throw new \RuntimeException("Unsupported image type: {$type}"),
        };

        if ($source === false) {
            throw new \RuntimeException("Failed to open image: {$sourcePath}");
        }

        if ($mode === 'crop') {
            $srcRatio = $srcW / $srcH;
            $dstRatio = $width / $height;

            if ($srcRatio > $dstRatio) {
                $cropW = (int) round($srcH * $dstRatio);
                $cropH = $srcH;
                $cropX = (int) round(($srcW - $cropW) / 2);
                $cropY = 0;
            } else {
                $cropW = $srcW;
                $cropH = (int) round($srcW / $dstRatio);
                $cropX = 0;
                $cropY = (int) round(($srcH - $cropH) / 2);
            }

            $thumb = imagecreatetruecolor($width, $height);
            imagecopyresampled($thumb, $source, 0, 0, $cropX, $cropY, $width, $height, $cropW, $cropH);
        } else {
            $ratio = min($width / $srcW, $height / $srcH);
            $destW = (int) round($srcW * $ratio);
            $destH = (int) round($srcH * $ratio);

            $thumb = imagecreatetruecolor($destW, $destH);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $destW, $destH, $srcW, $srcH);
        }

        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        $destDir = dirname($destPath);

        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }

        match ($ext) {
            'jpg', 'jpeg' => imagejpeg($thumb, $destPath, $quality),
            'png' => imagepng($thumb, $destPath, (int) round(9 - ($quality / 11))),
            'gif' => imagegif($thumb, $destPath),
            'webp' => imagewebp($thumb, $destPath, $quality),
            default => imagejpeg($thumb, $destPath, $quality),
        };

        imagedestroy($source);
        imagedestroy($thumb);
    }
}
