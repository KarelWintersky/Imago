<?php

declare(strict_types=1);

namespace Imago\Image\Intervention;

use Intervention\Image\Image;
use Intervention\Image\ImageManager;

final class Process
{
    public static function run(Image $image, array $rules, ?ImageManager $manager = null): Image
    {
        foreach ($rules as $rule => $config) {
            $image = match ($rule) {
                'crop' => $image->cover($config['width'], $config['height']),
                'resize' => $image->scale($config['width'], $config['height']),
                'rotate' => $image->rotate($config['angle'], 'ffffff'),
                'grayscale' => $image->greyscale(),
                'watermark' => self::watermark($image, $config, $manager),
                default => throw new \RuntimeException("Unknown Intervention process rule: {$rule}"),
            };
        }

        return $image;
    }

    private static function watermark(Image $image, array $config, ?ImageManager $manager): Image
    {
        $wmPath = $config['image'];
        $gap = (int) ($config['gap'] ?? 10);

        if ($manager !== null && (isset($config['width']) || isset($config['height']))) {
            $wm = $manager->read($wmPath);
            $wmW = (int) ($config['width'] ?? $wm->width());
            $wmH = (int) ($config['height'] ?? $wm->height());
            $wm->cover($wmW, $wmH);
            $element = $wm;
        } else {
            $element = $wmPath;
            $wmW = 0;
            $wmH = 0;
        }

        $position = match ($config['position'] ?? 'SE') {
            'NW' => 'top-left',
            'NE' => 'top-right',
            'SW' => 'bottom-left',
            'SE' => 'bottom-right',
        };

        $image->place($element, $position, $gap, $gap);

        return $image;
    }
}
