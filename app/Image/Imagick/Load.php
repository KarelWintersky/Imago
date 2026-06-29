<?php

declare(strict_types=1);

namespace Imago\Image\Imagick;

final class Load
{
    public static function run(string $sourcePath): \Imagick
    {
        $image = new \Imagick($sourcePath);
        return $image;
    }
}
