<?php

declare(strict_types=1);

namespace Imago\Image\Intervention;

use Intervention\Image\ImageManager;

final class Load
{
    public static function run(ImageManager $manager, string $sourcePath): \Intervention\Image\Image
    {
        return $manager->read($sourcePath);
    }
}
