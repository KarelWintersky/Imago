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
                'thumb' => ['crop' => ['width' => 150, 'height' => 150]],
                'small' => ['resize' => ['width' => 300, 'height' => 200]],
                'medium' => [
                    'resize' => ['width' => 800, 'height' => 600],
                    'grayscale' =>  []
                ],
                'large' => ['resize' => ['width' => 1200, 'height' => 900]],
                'wm'    =>  [
                    'watermark' =>  [
                        'image'     =>  __DIR__ . '/public/assets/wm_47news.png',
                        'width'     =>  150,
                        'height'    =>  150,
                        'gap'       =>  50
                    ],
                ]
            ],
            'preProcess' => [
                /*function (string $path, array $params): null|false|string|array {
                    if (($params['token'] ?? '') === 'password') return null;

                    if (preg_match('#/202[5-6]/#', $path)) {
                        return __DIR__ . '/public/assets/title_photo_archived.png';
                    }
                    return null;
                },*/
            ],
            'postProcess' => [],
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
                'thumb' => ['crop' => ['width' => 150, 'height' => 150]],
                'preview' => ['crop' => ['width' => 600, 'height' => 400]],
            ],
            'preProcess' => [
                /*function (string $path, array $params): null|false|string|array {
                    if (preg_match('#/202[5-6]/#', $path)) {
                        return [
                            'status' => 410,
                            'body' => 'The requested file has been archived and is no longer available.',
                            'content_type' => 'text/plain; charset=utf-8',
                        ];
                    }
                    return null;
                },*/
            ],
            'postProcess' => [],
            'processor' => 'imagick',
        ],
    ],

    'processor' => 'gd',

    'cache' => [
        'files' => [
            'dir' => __DIR__ . '/public/cache',
            'ttl' => 86400 * 30,
        ],
        'meta' => [
            'driver' => 'file',
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'prefix' => 'imago:cache:',
            ],
        ],
    ],

    'log' => [
        'level' => 'info',
        'file' => __DIR__ . '/logs/imago.log',
        'max_files' => 30,
    ],
];
