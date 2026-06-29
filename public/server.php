#!/usr/bin/env php
<?php

declare(strict_types=1);

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\SocketHttpServer;
use Amp\ByteStream\WritableResourceStream;
use Amp\Log\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Revolt\EventLoop;

require_once __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['config:']);
$configPath = $options['config'] ?? null;

if ($configPath === null) {
    $configPath = __DIR__ . '/../_config.php';
}

if (!file_exists($configPath)) {
    fwrite(STDERR, "Config file not found: {$configPath}\n");
    exit(1);
}

$config = \Imago\ConfigLoader::load($configPath);

$logLevel = $config['log']['level'] ?? 'info';
$logHandler = new StreamHandler(new WritableResourceStream(STDOUT), $logLevel);
$logHandler->pushProcessor(new PsrLogMessageProcessor());
$logger = new Logger('imago', [$logHandler]);

$consoleLogger = $logger;

$container = require __DIR__ . '/../app/bootstrap.php';
$config = $container['config'];
$handler = $container['handler'];

$addr = $config['server']['host'] . ':' . $config['server']['port'];

$logger->notice("Imago server starting on {$addr}...");

$httpServer = SocketHttpServer::createForBehindProxy(
    $logger,
    \Amp\Http\Server\Middleware\ForwardedHeaderType::XForwardedFor,
    ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
    enableCompression: false,
);

$httpServer->expose($addr);

$shutdown = function () use ($httpServer, $logger): void {
    $logger->info('Shutting down...');

    EventLoop::delay(3, fn () => EventLoop::stop());

    \Amp\async(function () use ($httpServer): void {
        $httpServer->stop();
        EventLoop::stop();
    });
};

EventLoop::onSignal(SIGINT, $shutdown);
EventLoop::onSignal(SIGTERM, $shutdown);

$httpServer->start(
    new ClosureRequestHandler(\Closure::fromCallable($handler)),
    new DefaultErrorHandler(),
);

$logger->info('Imago server started', ['address' => "http://{$addr}"]);

EventLoop::run();
