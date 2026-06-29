# Imago — Image Proxy Server

Асинхронный прокси-сервер изображений на **PHP 8.2+** и **AmPHP v3**.

```

GET /pulsar/photo.jpg?width=300&height=200
GET /pulsar/photo.jpg?width=300&height=200&mode=crop
GET /pulsar/photo.jpg?profile=thumb

# Или через домен (без префикса сервиса в пути):
# images.pulsar.local/photo.jpg?width=300
```

---

## Быстрый старт (локальный стенд)

### 1. Требования

- PHP ≥ 8.2 с ext-`gd`
- Composer
- Redis (опционально, для ускорения кэша)

```bash
php -v                    # 8.2+
php -m | grep gd          # GD есть?
composer --version        # установлен?
```

### 2. Установка

```bash
cd /var/www/imagoV2
composer install --no-dev
```

### 3. Монтирование стораджей

Создайте симлинки или смонтируйте бакеты в `public/storage/`:

```bash
# Пример: хранилище сервиса pulsar
ln -s /mnt/disks/pulsar-images public/storage/pulsar

# Или просто создать директорию для теста:
mkdir -p public/storage/pulsar
cp /path/to/test.jpg public/storage/pulsar/
```

### 4. Запуск

```bash
# Прямой запуск (AmPHP-сервер):
php bin/imago-server

# Или с явным указанием конфига:
php public/server.php --config=_config.php

# Или через PHP built-in server (только для отладки):
php -S 0.0.0.0:8080 -t public/
```

Сервер слушает `127.0.0.1:8080` (порт и хост настраиваются в конфиге).

### 5. Проверка

```bash
# Health check:
curl http://127.0.0.1:8080/health

# Ресайз существующего изображения:
curl -o result.jpg "http://127.0.0.1:8080/pulsar/test.jpg?width=300&height=200"

# Через профиль (из _config.php):
curl -o thumb.jpg "http://127.0.0.1:8080/pulsar/test.jpg?profile=thumb"

# Crop (кадрирование):
curl -o cropped.jpg "http://127.0.0.1:8080/pulsar/test.jpg?width=150&height=150&mode=crop"

# Плейсхолдер (если файла нет):
curl -o placeholder.jpg "http://127.0.0.1:8080/pulsar/no-such-file.jpg?width=300&height=200"

# Проверить размеры:
identify result.jpg thumb.jpg cropped.jpg placeholder.jpg
```

### 6. Остановка

```bash
kill $(pgrep -f imago-server)
```

---

## Деплой на production

### 1. Структура директорий

```
/var/www/imagoV2/
├── _config.php          # конфиг
├── app/                 # код
├── bin/imago-server     # запуск сервера
├── public/
│   ├── cache/           # кэш (должен быть доступен на запись)
│   └── storage/         # стораджи сервисов
├── logs/                # логи (должен быть доступен на запись)
└── vendor/              # composer-зависимости
```

Права:

```bash
chown -R www-data:www-data /var/www/imagoV2/{public/cache,logs}
chmod -R 775 /var/www/imagoV2/{public/cache,logs}
```

### 2. Systemd-сервис

```bash
cp imago.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable imago
systemctl start imago

# Проверка:
systemctl status imago
journalctl -u imago -f
```

### 3. Nginx

```bash
cp nginx.conf.example /etc/nginx/sites-available/imago.example.com
ln -s /etc/nginx/sites-available/imago.example.com /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

Основные моменты конфига:

- **`location /cache/`** — nginx отдаёт кэшированные файлы напрямую, минуя AmPHP (быстрый путь)
- **`location /`** — прокси на `127.0.0.1:8080`
- Заблокированы файлы, начинающиеся с `_` и `.`
- Таймауты: чтение 30s (на случай обработки больших файлов)

### 4. Redis (опционально)

Включите Redis-кэш в `_config.php`:

```php
'cache' => [
    'driver' => 'redis',     // было 'file'
    'ttl' => 86400 * 30,
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'prefix' => 'imago:cache:',
    ],
],
```

Redis хранит мета-данные: путь к файлу кэша и mime-type. Сами файлы хранятся на диске. Если Redis недоступен — бесшовное падение на файловый кэш.

---

## Конфигурация

### Основной конфиг (`_config.php`)

```php
return [
    'server' => [
        'host' => '127.0.0.1',  // адрес, на котором слушает AmPHP
        'port' => 8080,
    ],

    'services' => [
        'pulsar' => [
            'storage' => 'pulsar',           // поддиректория в public/storage/
            'domains' => ['images.pulsar.local'],  // домены для роутинга без префикса
            'placeholder' => [               // заглушки вместо отсутствующих файлов
                'enabled' => true,
                'color' => '3d4070',         // цвет текста (hex без #)
                'background' => 'ffffff',    // цвет фона (hex без #)
            ],
            'profiles' => [                  // именованные профили ресайза
                'thumb' => ['width' => 150, 'height' => 150, 'mode' => 'crop'],
                'small' => ['width' => 300, 'height' => 200, 'mode' => 'resize'],
            ],
        ],
    ],
];
```

### Service-specific конфиг

Можно вынести настройки сервиса в отдельный файл:

```php
// _config.php
'services' => [
    'pulsar' => [
        'config' => '_config.pulsar.php',  // доп. конфиг будет смержен
        'storage' => 'pulsar',
    ],
],

// _config.pulsar.php — вернёт массив, который будет смержен через array_replace_recursive
```

---

## Доменный роутинг

Каждый сервис может быть привязан к одному или нескольким доменам через ключ `domains`. Если запрос приходит на соответствующий `Host`, префикс сервиса в URL не требуется.

```php
'services' => [
    'pulsar' => [
        'storage' => 'pulsar',                     // файлы в public/storage/pulsar/
        'domains' => ['images.pulsar.local'],      // этот домен → сервис pulsar
        'placeholder' => ['enabled' => true],
    ],
    '47news' => [
        'storage' => '',                           // файлы прямо в public/storage/
        'domains' => ['images.47news.local'],
        'placeholder' => ['enabled' => true],
    ],
],
```

Схема работы:

- Если первый сегмент пути совпадает с именем сервиса (`/pulsar/photo.jpg`) — используется **path-based** роутинг (как и раньше).
- Если нет — извлекается `Host` из заголовка запроса, и сервис ищется по маппингу `domains`.
- Если ни то, ни другое — `404 Service not found`.

Путь к файлу на диске: `{storage_path}/{relative_path}`. Если `storage` пустой — файлы лежат непосредственно в `public/storage/`.

Пример nginx для двух доменов на одном upstream:

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

server {
    listen 80;
    server_name images.47news.local;
    location / { proxy_pass http://imago_backend; }
}
```

Оба домена проксируются на один и тот же сервер Imago — выбор сервиса происходит по `Host` внутри приложения.

---

## Restrict-коллбэки (preprocess)

Для каждого сервиса можно задать массив `restrict` — функций, которые перехватывают запрос до обработки изображения. Это позволяет блокировать доступ, возвращать кастомные ответы или подменять файл.

Коллбэк получает `(string $storagePath, array $queryParams)` и должен вернуть:

| Возврат | Поведение |
|---------|-----------|
| `null` | Пропустить — продолжить нормальную обработку |
| `false` | `403 Forbidden` |
| `string` — существующий файл | Отдать этот файл вместо запрошенного |
| `string` — не файл | `410 Gone` с этим текстом в теле |
| `array{status, body, ...}` | Кастомный ответ (см. пример ниже) |

```php
'services' => [
    'pulsar' => [
        'storage' => 'pulsar',
        'restrict' => [
            function (string $path, array $params): null|false|string|array {
                // Файлы за 2020-2023 — архив, отдаём плашку
                if (preg_match('#/202[0-3]/#', $path)) {
                    return [
                        'status' => 410,
                        'body' => 'The requested file has been archived '
                            . 'and is no longer available.',
                        'content_type' => 'text/plain; charset=utf-8',
                    ];
                }

                // Можно подменить файл
                // if (...) { return '/var/www/imagoV2/placeholder.png'; }

                // Можно запретить
                // if (...) { return false; }

                return null; // продолжить как обычно
            },
        ],
    ],
],
```

Коллбэки выполняются **до** проверки кэша и обработки изображения, в порядке объявления. Первый не-`null` возврат прерывает цепочку.

---

## Параметры запроса

| Параметр | Тип | Описание |
|----------|-----|----------|
| `width` | int | Ширина в px |
| `height` | int | Высота в px |
| `mode` | `resize` / `crop` | Режим обработки |
| `profile` | string | Имя профиля из конфига |

- `resize` — вписать в прямоугольник `width×height` с сохранением пропорций
- `crop` — кадрировать до точных `width×height` (center crop)

Если указан только `width` или только `height` — недостающая размерность копируется из имеющейся (квадрат).

**Ограничение:** максимальный размер — 4096px по каждой стороне (настраивается через `max_dimension` в конфиге).

---

## Мониторинг

- **Health check:** `GET /health` → `{"status":"ok","time":...}`
- **Логи:** `logs/imago.log` (ротация каждые 100MB, хранятся 30 файлов)
- **Systemd:** `journalctl -u imago -f`

---

## Архитектура

```
Client → Nginx → AmPHP Server (127.0.0.1:8080)
                    │
                    ├─ RequestHandler → parse URI + params
                    │       │
                    │       ├─ CacheManager → проверка public/cache/{hash}.ext
                    │       │       │
                    │       │       └─ Redis (опционально, мета-данные)
                    │       │
                    │       ├─ ImageProcessor → GD resize/crop → cache
                    │       │
                    │       └─ PlaceholderGenerator → заглушка → cache
                    │
                    └─ Response (200, Content-Type, Cache-Control: immutable)
```

Кэш структурирован как `public/cache/{первые 2 символа md5}/{md5-хэш}.{ext}` — это предотвращает проблемы с миллионами файлов в одной директории.
