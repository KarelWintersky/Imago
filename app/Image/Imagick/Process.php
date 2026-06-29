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
                default => throw new \RuntimeException("Unknown Imagick process rule: {$rule}"),
            };
        }

        return $image;
    }
}
