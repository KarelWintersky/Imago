<?php

declare(strict_types=1);

use app\ConfigLoader;
use app\RequestHandler;

$consoleLogger = isset($consoleLogger) && $consoleLogger instanceof \Psr\Log\LoggerInterface
    ? $consoleLogger
    : null;

if (!isset($config)) {
    $config = ConfigLoader::load(__DIR__ . '/../_config.php');
}

$handler = new RequestHandler($config, $consoleLogger);

return [
    'config' => $config,
    'handler' => $handler,
];
