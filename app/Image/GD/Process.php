<?php

declare(strict_types=1);

namespace Imago\Image\GD;

final class Process
{
    public static function run(\GdImage $image, array $rules): \GdImage
    {
        $srcW = imagesx($image);
        $srcH = imagesy($image);

        foreach ($rules as $rule => $config) {
            $image = match ($rule) {
                'crop' => self::crop($image, $srcW, $srcH, $config['width'], $config['height']),
                'resize' => self::resize($image, $srcW, $srcH, $config['width'], $config['height']),
                'rotate' => self::rotate($image, $config['angle']),
                'grayscale' => self::grayscale($image),
                'watermark' => self::watermark($image, $config),
                default => throw new \RuntimeException("Unknown GD process rule: {$rule}"),
            };

            $srcW = imagesx($image);
            $srcH = imagesy($image);
        }

        return $image;
    }

    private static function crop(\GdImage $image, int $srcW, int $srcH, int $dstW, int $dstH): \GdImage
    {
        $ratio = $srcW / $srcH;
        $dstRatio = $dstW / $dstH;

        if ($ratio > $dstRatio) {
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

        $thumb = imagecreatetruecolor($dstW, $dstH);
        imagecopyresampled($thumb, $image, 0, 0, $cropX, $cropY, $dstW, $dstH, $cropW, $cropH);
        imagedestroy($image);

        return $thumb;
    }

    private static function resize(\GdImage $image, int $srcW, int $srcH, int $maxW, int $maxH): \GdImage
    {
        $ratio = min($maxW / $srcW, $maxH / $srcH);
        $destW = (int) round($srcW * $ratio);
        $destH = (int) round($srcH * $ratio);

        $thumb = imagecreatetruecolor($destW, $destH);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $destW, $destH, $srcW, $srcH);
        imagedestroy($image);

        return $thumb;
    }

    private static function rotate(\GdImage $image, int $angle): \GdImage
    {
        $rotated = imagerotate($image, $angle, 0);
        imagedestroy($image);
        return $rotated;
    }

    private static function grayscale(\GdImage $image): \GdImage
    {
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        return $image;
    }

    private static function watermark(\GdImage $image, array $config): \GdImage
    {
        $wm = imagecreatefrompng($config['image']);

        $wmW = (int) ($config['width'] ?? imagesx($wm));
        $wmH = (int) ($config['height'] ?? imagesy($wm));

        if (isset($config['width']) || isset($config['height'])) {
            $resized = imagecreatetruecolor($wmW, $wmH);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $wm, 0, 0, 0, 0, $wmW, $wmH, imagesx($wm), imagesy($wm));
            imagedestroy($wm);
            $wm = $resized;
        }

        $destW = imagesx($image);
        $destH = imagesy($image);
        $gap = (int) ($config['gap'] ?? 10);

        [$x, $y] = match ($config['position'] ?? 'SE') {
            'NW' => [$gap, $gap],
            'NE' => [$destW - $wmW - $gap, $gap],
            'SW' => [$gap, $destH - $wmH - $gap],
            'SE' => [$destW - $wmW - $gap, $destH - $wmH - $gap],
        };

        imagealphablending($image, true);
        imagesavealpha($image, true);
        imagecopy($image, $wm, $x, $y, 0, 0, $wmW, $wmH);
        imagedestroy($wm);

        return $image;
    }
}
