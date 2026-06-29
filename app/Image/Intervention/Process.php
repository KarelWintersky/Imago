<?php

declare(strict_types=1);

namespace Imago\Image\Intervention;

use Intervention\Image\Image;

final class Process
{
    public static function run(Image $image, array $rules): Image
    {
        foreach ($rules as $rule => $config) {
            $image = match ($rule) {
                'crop' => $image->cover($config['width'], $config['height']),
                'resize' => $image->scale($config['width'], $config['height']),
                default => throw new \RuntimeException("Unknown Intervention process rule: {$rule}"),
            };
        }

        return $image;
    }
}
