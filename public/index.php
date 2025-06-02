<?php

use App\Imago\ImageProxy;

define('ENGINE_START_TIME', microtime(true));
if (!session_id()) @session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$storageRoot = '/var/www/dch_fontanka_zsd_costume/files.storage/';
# $cacheDir = '/dev/shm/zsd_image_proxy/';
$cacheDir = '/var/www/dch_fontanka_zsd_costume/files.storage.cache/';
$configDir = dirname(__DIR__) . '/conf.d/';
$cacheTtl = 86400; // 24 часа
$defaultQuality = 80;

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $request = ImageProxy::getRequestParams();

    // оно должно загружать не конфиг, а класс, который отдает конфиг и функцию обработки файла!
    $worker = match ($request['project']) {
        '47news'    =>  new \App\Imago\Profiles\FSNews($configDir),
    };


    $config_file = match ($request['project']) {
        '47news'    =>  __DIR__ . '/../conf.d/47news.php',
        'zsdcostume'=>  __DIR__ . '/../conf.d/zsd_costume.php',
        default     =>  __DIR__ . '/../conf.d/zsd_costume.php'
    };
    if (empty($config_file)) {
        throw new RuntimeException("Invalid project", 400);
    }

    if (empty($request['file'])) {
        throw new RuntimeException('No file given', 400);
    }

    // загружаем конфиг проекта
    $config = require_once $config_file;

    $proxy = new ImageProxy($config);

    // Полный путь+файл исходный
    $sourceFile = $proxy->getSourceFilename();

    // Полный путь+файл кэшированный (на основе исходного)
    $cachedFile = $proxy->getCachedFilename($sourceFile);

    if (!file_exists($sourceFile)) {
        $proxy->removeFile($cachedFile);
        throw new RuntimeException('File not found', 404);
    }

    // Если файл в кэше существует и свежий - отдаем его
    if (file_exists($cachedFile) && (time() - filemtime($cachedFile) < $cacheTtl)) {
        $proxy->serveCachedImage($cachedFile);

        // LOG

        exit;
    }

    // Создание директории кэша если нужно
    ImageProxy::validateCacheDirectory($cacheDir);

    // main
    $proxy->makeWithGD($sourceFile, $cachedFile);

    // Отдача результата
    $proxy->serveCachedImage($cachedFile);

} catch (RuntimeException $e) {
    http_response_code($e->getCode() ?: 500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'line' => $e->getLine(), 'code' => $e->getCode() ]);
}
