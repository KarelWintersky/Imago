<?php

declare(strict_types=1);

return [
    'server' => [
        'host' => '127.0.0.1',
        'port' => 8080,
    ],

    'services' => [
        'pulsar' => [
            'storage' => 'pulsar',
            'domains' => ['images.pulsar.local'],
            'placeholder' => [
                'enabled' => true,
                'color' => '3d4070',
                'background' => 'ffffff',
            ],
            'profiles' => [
                'thumb' => ['width' => 150, 'height' => 150, 'mode' => 'crop'],
                'small' => ['width' => 300, 'height' => 200, 'mode' => 'resize'],
                'medium' => ['width' => 800, 'height' => 600, 'mode' => 'resize'],
                'large' => ['width' => 1200, 'height' => 900, 'mode' => 'resize'],
            ],
        ],
        'news47' => [
            'storage' => '',
            'domains' => ['images.47news.local'],
            'placeholder' => [
                'enabled' => true,
                'color' => 'ffffff',
                'background' => 'cc0000',
            ],
            'profiles' => [
                'thumb' => ['width' => 150, 'height' => 150, 'mode' => 'crop'],
                'preview' => ['width' => 600, 'height' => 400, 'mode' => 'crop'],
            ],
        ],
    ],

    'cache' => [
        'driver' => 'file',
        'ttl' => 86400 * 30,
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'prefix' => 'imago:cache:',
        ],
    ],

    'log' => [
        'level' => 'info',
        'file' => __DIR__ . '/logs/imago.log',
        'max_files' => 30,
    ],
];
