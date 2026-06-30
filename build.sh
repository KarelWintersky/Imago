#!/bin/bash
set -e

PROJECT="$(basename "$(pwd)")"

if [ ! -f "box.json" ]; then
    echo "Error: box.json not found"
    exit 1
fi

BOX_MAIN=$(php -r "echo json_decode(file_get_contents('box.json'), true)['main'] ?? 'index.php';")
BOX_OUTPUT=$(php -r "echo json_decode(file_get_contents('box.json'), true)['output'] ?? 'output.phar';")
VERSION_DIR=$(dirname "$BOX_MAIN")

echo "Building $PROJECT PHAR..."
echo "   Main: $BOX_MAIN"
echo "   Output: $BOX_OUTPUT"

IMAGE="phar-builder-imago"

if [ "$1" = "--rebuild" ]; then
    echo "Rebuilding image $IMAGE..."
    docker rmi "$IMAGE" 2>/dev/null || true
fi

if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
    echo "Building image $IMAGE..."

    TMP_DF="/tmp/phar-builder-$$.Dockerfile"
    trap 'rm -f "$TMP_DF"' EXIT

    if [ -f "box.phar" ]; then
        BOX_INSTALL="COPY box.phar /usr/local/bin/box"
    else
        BOX_INSTALL="RUN curl -LSs https://github.com/box-project/box/releases/download/4.5.0/box.phar -o /usr/local/bin/box && chmod +x /usr/local/bin/box"
    fi

    cat > "$TMP_DF" << DOCKERFILE
FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev coreutils libicu-dev libsodium-dev \
    libmagickwand-dev libwebp-dev libjpeg-dev libpng-dev libfreetype-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j\$(nproc) gd intl pcntl zip sodium \
    && pecl install imagick redis \
    && docker-php-ext-enable imagick redis

RUN git config --global --add safe.directory '*'

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

${BOX_INSTALL}

WORKDIR /app
DOCKERFILE

    docker build -f "$TMP_DF" -t "$IMAGE" .
fi

CURRENT_UID=$(id -u)
CURRENT_GID=$(id -g)

echo "Compiling..."
docker run --rm \
    -v "$(pwd)":/app \
    -e HOST_UID=$CURRENT_UID \
    -e HOST_GID=$CURRENT_GID \
    "$IMAGE" sh -c "
        composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction 2>&1 | sed 's/^/   /'
        if git rev-parse --git-dir > /dev/null 2>&1; then
            mkdir -p $VERSION_DIR
            git rev-parse --short HEAD > $VERSION_DIR/_version
            git log --oneline --format=%B -n 1 HEAD | head -n 1 >> $VERSION_DIR/_version
            git log --oneline --format='%at' -n 1 HEAD | xargs -I{} date -d @{} +%Y-%m-%d >> $VERSION_DIR/_version
        fi
        box compile 2>&1 | sed 's/^/   /'
        chown \${HOST_UID}:\${HOST_GID} /app/$BOX_OUTPUT 2>/dev/null || true
    "

if [ -f "$BOX_OUTPUT" ]; then
    echo "PHAR ready:"
    ls -lh "$BOX_OUTPUT"
else
    echo "Error: $BOX_OUTPUT was not created."
    exit 1
fi
