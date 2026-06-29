<?php

declare(strict_types=1);

namespace Imago\Image;

final class PlaceholderGenerator
{
    public function generate(
        string $destPath,
        int $width,
        int $height,
        string $bgHex = 'cccccc',
        string $textHex = '333333',
        int $quality = 85,
    ): void {
        $destDir = dirname($destPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0775, true);
        }

        $width = max(1, $width);
        $height = max(1, $height);

        $image = imagecreatetruecolor($width, $height);

        [$bgR, $bgG, $bgB] = $this->hexToRgb($bgHex);
        [$textR, $textG, $textB] = $this->hexToRgb($textHex);

        $bgColor = imagecolorallocate($image, $bgR, $bgG, $bgB);
        $textColor = imagecolorallocate($image, $textR, $textG, $textB);

        imagefill($image, 0, 0, $bgColor);

        $label = "{$width} x {$height}";

        $fontSize = max(8, min(48, (int) round($width / 12)));

        if (function_exists('imagettftext')) {
            $fontPaths = [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/TTF/DejaVuSans.ttf',
                '/usr/share/fonts/ttf/DejaVuSans.ttf',
            ];

            $fontFile = null;
            foreach ($fontPaths as $fp) {
                if (file_exists($fp)) {
                    $fontFile = $fp;
                    break;
                }
            }

            if ($fontFile !== null) {
                $bbox = @imagettfbbox($fontSize, 0, $fontFile, $label);
                if ($bbox !== false) {
                    $textW = $bbox[2] - $bbox[0];
                    $textH = $bbox[1] - $bbox[7];
                    $x = (int) round(($width - $textW) / 2);
                    $y = (int) round(($height - $textH) / 2 + $textH);
                    @imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontFile, $label);
                }
            }
        } else {
            $this->drawTextFallback($image, $label, $width, $height, $textColor);
        }

        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));

        match ($ext) {
            'png' => imagepng($image, $destPath, 9),
            'gif' => imagegif($image, $destPath),
            'webp' => imagewebp($image, $destPath, $quality),
            default => imagejpeg($image, $destPath, $quality),
        };

        imagedestroy($image);
    }

    private function drawTextFallback(\GdImage $image, string $text, int $width, int $height, int $color): void
    {
        $len = strlen($text);
        if ($len === 0) {
            return;
        }

        $fontSize = 5;
        $charW = imagefontwidth($fontSize);
        $charH = imagefontheight($fontSize);
        $textW = $charW * $len;
        $textH = $charH;

        $x = (int) round(($width - $textW) / 2);
        $y = (int) round(($height - $textH) / 2);

        imagestring($image, $fontSize, max(0, $x), max(0, $y), $text, $color);
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return [204, 204, 204];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
