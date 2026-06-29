# Imago — Image Proxy Server

Асинхронный прокси-сервер изображений на **PHP 8.2+** и **AmPHP v3**.

```
GET /pulsar/photo.jpg?width=300&height=200
GET /pulsar/photo.jpg?profile=thumb

# Через домен (без префикса сервиса):
# images.pulsar.local/photo.jpg?width=300
```

## Быстрый старт

### Требования

- PHP ≥ 8.2 с ext-`gd` / ext-`imagick` (опционально)
- Composer
- Redis (опционально)

### Установка

```bash
git clone https://github.com/KarelWintersky/Imago.git
cd Imago
composer install --no-dev
```

### Монтирование хранилищ

```bash
ln -s /mnt/disks/pulsar-images public/storage/pulsar
# или для теста:
mkdir -p public/storage/pulsar
cp photo.jpg public/storage/pulsar/
```

### Запуск

```bash
php public/server.php --config=_config.php
# или
php bin/imago-server
```

Сервер слушает `127.0.0.1:8080`.

### Проверка

```bash
curl http://127.0.0.1:8080/health

curl -o result.jpg "http://127.0.0.1:8080/pulsar/photo.jpg?width=300"
curl -o thumb.jpg  "http://127.0.0.1:8080/pulsar/photo.jpg?profile=thumb"
curl -o crop.jpg   "http://127.0.0.1:8080/pulsar/photo.jpg?width=150&height=150&mode=crop"
```

---

## Архитектура

```
Client → Nginx → AmPHP (127.0.0.1:8080)
                    │
                    ├─ RequestHandler → resolve service + params
                    │     ├─ CacheManager → file cache ({md5[0:2]}/{md5}.ext)
                    │     │     └─ Redis (опционально, мета-данные)
                    │     ├─ ImageProcessor → Load → Process(rules) → Save
                    │     └─ PlaceholderGenerator → заглушка при 404
                    │
                    └─ Response (200, Content-Type, Cache-Control)
```

Кэш: `public/cache/{md5[0:2]}/{md5}.{ext}`.

---

## Конфигурация

### Основной конфиг (`_config.php`)

```php
return [
    'server' => [
        'host' => '127.0.0.1',
        'port' => 8080,
    ],

    'processor' => 'gd',   // gd | imagick | intervention:gd | intervention:imagick

    'cache' => [
        'files' => [
            'dir' => __DIR__ . '/public/cache',
            'ttl' => 86400 * 30,
        ],
        'meta' => [
            'driver' => 'file',   // file | redis
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'prefix' => 'imago:cache:',
            ],
        ],
    ],

    'services' => [
        'pulsar' => [
            'storage' => 'pulsar',                          // поддиректория в public/storage/
            'domains' => ['images.pulsar.local'],
            'placeholder' => ['enabled' => true, 'color' => '3d4070', 'background' => 'ffffff'],
            'profiles' => [
                'thumb'  => ['crop'   => ['width' => 150, 'height' => 150]],
                'small'  => ['resize' => ['width' => 300, 'height' => 200]],
                'medium' => ['resize' => ['width' => 800, 'height' => 600]],
                'large'  => ['resize' => ['width' => 1200, 'height' => 900]],
            ],
        ],
    ],

    'log' => [
        'level' => 'info',
        'file'  => __DIR__ . '/logs/imago.log',
        'max_files' => 30,
    ],
];
```

### Сервисы и роутинг

Приоритет разрешения сервиса:
1. Первый сегмент пути совпадает с именем сервиса (`/pulsar/photo.jpg`)
2. Заголовок `Imago-Host` (например `proxy_set_header Imago-Host pulsar`)
3. `Host` → маппинг `domains`

```nginx
upstream imago_backend {
    server 127.0.0.1:8080;
    keepalive 64;
}

server {
    listen 80;
    server_name images.pulsar.local;
    location / { proxy_pass http://imago_backend; }
}
```

### Процессор

| Драйвер | Значение `processor` | Бэкенд |
|---------|---------------------|--------|
| GD | `gd` | ext-gd |
| ImageMagick | `imagick` | ext-imagick |
| Intervention GD | `intervention:gd` | intervention/image + ext-gd |
| Intervention Imagick | `intervention:imagick` | intervention/image + ext-imagick |

Драйвер задаётся глобально, можно переопределить на сервис:

```php
'processor' => 'gd',

'services' => [
    'pulsar' => [],                          // использует gd
    'news47' => ['processor' => 'imagick'],  // переопределение
],
```

> **Перспектива (PHP 8.3+)**: `intervention/image` v4 с VipsDriver (libvips) и `intervention/gif` (анимированные GIF).

### Service-specific конфиг

```php
'services' => [
    'pulsar' => [
        'config' => '_config.pulsar.php',   // будет смержен через array_replace_recursive
        'storage' => 'pulsar',
    ],
],
```

---

## Правила обработки (rules)

Подробно: [RULES.md](RULES.md)

Кратко — профили и query-параметры преобразуются в набор правил, которые последовательно применяются к изображению:

```php
'profiles' => [
    'thumb' => [
        'crop'   => ['width' => 150, 'height' => 150],
        'grayscale' => [],
    ],
],
```

Поддерживаемые правила: `resize`, `crop`, `rotate`, `grayscale`.

---

## preProcess / postProcess

Массивы callbacks, перехватывающих запрос до/после обработки. Порядок вызова — объявления. Первый не-`null` возврат прерывает цепочку.

| Возврат | Поведение |
|---------|-----------|
| `null` | Пропустить |
| `false` | `403 Forbidden` |
| `string` (сущ. файл) | Отдать этот файл |
| `string` (не файл) | `410 Gone` с этим текстом |
| `array{status,body,...}` | Кастомный ответ |

```php
'preProcess' => [
    function (string $path, array $params): null|false|string|array {
        if (preg_match('#/202[0-3]/#', $path)) {
            return [
                'status' => 410,
                'body' => 'File archived and no longer available.',
                'content_type' => 'text/plain; charset=utf-8',
            ];
        }
        return null;
    },
],
'postProcess' => [],
```

---

## Параметры запроса

| Параметр | Тип | Описание |
|----------|-----|----------|
| `width` | int | Ширина в px |
| `height` | int | Высота в px |
| `mode` | `resize` / `crop` | Режим (только без profile) |
| `profile` | string | Имя профиля из конфига |

Если указан только `width` или `height` — недостающая копируется (квадрат).  
Максимальный размер — 4096px (настраивается `max_dimension`).

---

## Деплой

```
/var/www/imagoV2/
├── _config.php
├── app/
├── bin/imago-server
├── public/
│   ├── cache/          #必须是 www-data:www-data, 775
│   └── storage/        # хранилища сервисов
├── logs/               #必须是 www-data:www-data, 775
└── vendor/
```

### Systemd

```bash
cp imago.service /etc/systemd/system/
systemctl daemon-reload && systemctl enable --now imago
```

### Nginx

```nginx
location /cache/ {
    # nginx отдаёт кэш напрямую, минуя AmPHP
    root /var/www/imagoV2/public;
    try_files $uri @backend;
    expires 365d;
    add_header Cache-Control "public, immutable";
}

location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $host;
    proxy_read_timeout 30s;
}
```

### Redis (опционально)

Ускоряет lookup кэша. При недоступности — бесшовное падение на `glob()`.

---

## Мониторинг

- `GET /health` → `{"status":"ok","time":...}`
- Логи: `logs/imago.log` (ротация 100MB, 30 файлов)
- `journalctl -u imago -f`
