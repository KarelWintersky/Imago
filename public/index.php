<?php

use App\Imago\Exceptions\FileCachedException;
use App\Imago\Exceptions\FileDeletedException;
use App\Imago\Exceptions\FileNotFoundException;
use App\Imago\ImageProxy;

define('ENGINE_START_TIME', microtime(true));

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$configDir = dirname(__DIR__) . '/conf.d/';

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $request = ImageProxy::getRequestParams();

    $worker = match ($request['project']) {
        '47news'        =>  new \App\Imago\Profiles\FSNews($configDir),
        'zsdcostume'    =>  new \App\Imago\Profiles\ZSDCostume($configDir),
        default         =>  new \App\Imago\Profiles\ZSDCostume($configDir)
    };

    if (empty($request['file'])) {
        throw new FileNotFoundException('No file given', 400);
    }

    $proxy = new ImageProxy($worker, $request);

    // Полный путь+файл исходный
    $sourceFile = $proxy->getSourceFilename();

    // Полный путь+файл кэшированный (на основе исходного)
    $cachedFile = $proxy->getCachedFilename($sourceFile);

    if (!file_exists($sourceFile)) {
        $proxy->removeFile($cachedFile);
        throw new FileNotFoundException('File not found', 404);
    }

    if (!empty($request['action']) && $request['action'] == 'purge') {
        $proxy->removeFile($cachedFile);

        // логгируем событие "удален файл"

        throw new FileDeletedException();
    }

    // Если файл в кэше существует и свежий - отдаем его
    if (file_exists($cachedFile) && (time() - filemtime($cachedFile) < $worker->getConfig('TTL'))) {
        $proxy->serveCachedImage($cachedFile);

        // логгируем то, что отдан кэшированный файл

        throw new FileCachedException();
    }

    // Создание директории кэша если нужно
    ImageProxy::validateCacheDirectory($worker->getConfig('CACHE'));

    // main
    $proxy->makeWithGD($sourceFile, $cachedFile);

    // Отдача результата
    $proxy->serveCachedImage($cachedFile);

} catch (FileCachedException $e) {
} catch (FileDeletedException $e) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['state' => 'ok', 'message' => 'File deleted']);
} catch (FileNotFoundException|RuntimeException $e) {
    http_response_code($e->getCode() ?: 500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'code' => $e->getCode(),
        'file' => $e->getFile()
    ]);
}
/*finally {

}*/

// финальное логгирование
