<?php

declare(strict_types=1);

namespace Imago\Image\Imagick;

final class Process
{
    public static function run(\Imagick $image, array $rules): \Imagick
    {
        foreach ($rules as $rule => $config) {
            match ($rule) {
                'crop' => $image->cropThumbnailImage($config['width'], $config['height']),
                'resize' => $image->resizeImage($config['width'], $config['height'], \Imagick::FILTER_LANCZOS, 1, true),
                'rotate' => $image->rotateImage(new \ImagickPixel('transparent'), $config['angle']),
                'grayscale' => $image->modulateImage(100, 0, 100),
                'watermark' => self::watermark($image, $config),
                default => throw new \RuntimeException("Unknown Imagick process rule: {$rule}"),
            };
        }

        return $image;
    }

    private static function watermark(\Imagick $image, array $config): void
    {
        $wm = new \Imagick($config['image']);

        $wmW = (int) ($config['width'] ?? $wm->getImageWidth());
        $wmH = (int) ($config['height'] ?? $wm->getImageHeight());

        if (isset($config['width']) || isset($config['height'])) {
            $wm->resizeImage($wmW, $wmH, \Imagick::FILTER_LANCZOS, 1, true);
        }

        $destW = $image->getImageWidth();
        $destH = $image->getImageHeight();
        $gap = (int) ($config['gap'] ?? 10);

        [$x, $y] = match ($config['position'] ?? 'SE') {
            'NW' => [$gap, $gap],
            'NE' => [$destW - $wmW - $gap, $gap],
            'SW' => [$gap, $destH - $wmH - $gap],
            'SE' => [$destW - $wmW - $gap, $destH - $wmH - $gap],
        };

        $image->compositeImage($wm, \Imagick::COMPOSITE_OVER, $x, $y);
        $wm->clear();
    }
}
