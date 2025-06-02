<?php

namespace App\Imago;

class ImageProxy
{
    private array $config = [];

    /**
     * Дефолтное качество сжатия у превьюшек
     * @var array|int[]
     */
    private array $formatQuality = [
        'jpeg'  =>  80,
        'jpg'   =>  80,

    ];

    private int $jpeg_quality = 80;

    /**
     * Параметры запроса - обрабатываемый файл
     *
     * @var array
     */
    private array $requestParams = [];
    private string $storageRoot;
    private string $cacheRoot;

    public function __construct(array $config)
    {
        $this->storageRoot = $config['STORAGE'];
        $this->cacheRoot = $config['CACHE'];
    }

    /**
     * Определяет настройки обрабатываемого файла из $_GET
     *
     * @return array
     */
    public static function getRequestParams():array
    {
        $params = [
            'project'   =>  $_GET['project'] ?? '',
            'file'      =>  $_GET['file'] ?? '',
            'width'     =>  (int)($_GET['width'] ?? 0),
            'height'    =>  (int)($_GET['height'] ?? 0),
            'format'    =>  strtolower($_GET['format'] ?? 'jpeg'),
        ];

        // $this->requestParams = $params;

        return $params;
    }

    /**
     * @param string $format
     * @return string
     */
    public static function getMimeTypeByExtension(string $format): string
    {
        return match ($format) {
            'jpg', 'jpeg'   => 'image/jpeg',
            'png'           => 'image/png',
            'gif'           => 'image/gif',
            'webp'          => 'image/webp',
            default         => 'application/octet-stream'
        };
    }

    /**
     * Отдает кэшированный файл через механизм X-Accel-Redirect
     *
     * @param string $path
     * @return void
     */
    public function serveCachedImage(string $path): void
    {
        // $cache_location = "/cache/";
        $cache_location = $this->config['LOCATION'];
        $format = $this->requestParams['format'];

        header("X-Accel-Redirect: " . $cache_location . basename($path));
        header('Content-Type: ' . self::getMimeTypeByExtension($format));
    }

    /**
     * Создает папку кэша если она не существует
     *
     * @param $cacheDir
     * @return bool
     */
    public static function validateCacheDirectory($cacheDir):bool
    {
        if (!file_exists($cacheDir)) {
            return mkdir($cacheDir, 0755, true);
        }
        return true;
    }

    /**
     * Генерирует исходное имя файла. Смысла не имеет, на самом деле, сделано для симметрии
     *
     * @param $sourceFile
     * @return string
     */
    public function getSourceFilename():string
    {
        return rtrim($this->storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->requestParams['file'];
    }

    /**
     * Возвращает имя файла в кэше
     *
     * @param $sourceFile
     * @return string
     */
    public function getCachedFilename($sourceFile):string
    {
        $cacheKey = md5(implode('|', $this->requestParams) . filemtime($sourceFile));
        return $this->cacheRoot . $cacheKey . '.' . $this->requestParams['format'];
    }

    /**
     * Удаляет файл
     *
     * @param $filepath
     * @return bool
     */
    public function removeFile($filepath):bool
    {
        return is_file($filepath) ? unlink($filepath) : true;
    }

    /**
     * Make image with GD Library
     *
     * @param $sourceFile
     * @param $params
     * @param $cacheFile
     * @return void
     */
    public function makeWithGD($sourceFile, $cacheFile): void
    {
        $imageInfo = getimagesize($sourceFile);
        if (!$imageInfo) {
            throw new RuntimeException('Unsupported image type', 400);
        }

        $params = $this->requestParams;

        $sourceImage = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourceFile),
            IMAGETYPE_PNG => imagecreatefrompng($sourceFile),
            IMAGETYPE_GIF => imagecreatefromgif($sourceFile),
            IMAGETYPE_WEBP => imagecreatefromwebp($sourceFile),
            default => throw new RuntimeException('Unsupported image format', 400),
        };

        // Ресайз
        if ($params['width'] > 0 || $params['height'] > 0) {
            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);

            // Рассчет новых размеров с сохранением пропорций
            if ($params['width'] == 0) {
                $params['width'] = (int)($originalWidth * $params['height'] / $originalHeight);
            } elseif ($params['height'] == 0) {
                $params['height'] = (int)($originalHeight * $params['width'] / $originalWidth);
            }

            $resizedImage = imagecreatetruecolor($params['width'], $params['height']);

            // Прозрачность для PNG/GIF
            if ($imageInfo[2] == IMAGETYPE_PNG || $imageInfo[2] == IMAGETYPE_GIF) {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
                imagefilledrectangle($resizedImage, 0, 0, $params['width'], $params['height'], $transparent);
            }

            imagecopyresampled(
                $resizedImage, $sourceImage,
                0, 0, 0, 0,
                $params['width'], $params['height'],
                $originalWidth, $originalHeight
            );

            imagedestroy($sourceImage);
            $sourceImage = $resizedImage;
        }

        // Сохранение в кэш
        switch ($params['format']) {
            /*case 'jpg':
            case 'jpeg':
                imagejpeg($sourceImage, $cacheFile, $params['quality']);
                break;*/
            case 'png':
                imagepng($sourceImage, $cacheFile, (int)(9 * $this->jpeg_quality / 100));
                break;
            case 'gif':
                imagegif($sourceImage, $cacheFile);
                break;
            case 'webp':
                imagewebp($sourceImage, $cacheFile, $params['quality']);
                break;
            default:
                imagejpeg($sourceImage, $cacheFile, $params['quality']);
                break;
            // throw new RuntimeException('Unsupported output format', 400);
        }

    }



}