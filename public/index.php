<?php

/**
 * Imago Image Proxy Server - Development Fallback
 *
 * This file allows running with PHP's built-in server for development:
 *   php -S 0.0.0.0:8080 -t public/
 *
 * For production, run the persistent AmPHP server behind nginx:
 *   php bin/imago-server
 *
 * See nginx.conf.example for production setup.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../app/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$body = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];

$pair = @stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
if ($pair === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create socket pair']);
    exit;
}

$socket = \Amp\Socket\ResourceSocket::fromServerSocket($pair[0]);
$client = new \Amp\Http\Server\Driver\SocketClient($socket);

$request = new \Amp\Http\Server\Request(
    $client,
    $method,
    \League\Uri\Http::createFromString("http://{$host}{$uri}"),
    $headers,
    $body,
);

$response = $container['handler']($request);

fclose($pair[0]);
fclose($pair[1]);

http_response_code($response->getStatus());

foreach ($response->getHeaders() as $name => $values) {
    foreach ((array) $values as $value) {
        header("{$name}: {$value}", false);
    }
}

$stream = $response->getBody();
while (null !== $chunk = $stream->read()) {
    echo $chunk;
}
