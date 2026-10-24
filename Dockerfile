# Prebuilt Hexlet frontend. The source is not part of this repository.
FROM node:22-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --omit=dev

# Composer dependencies without development packages.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-pdo_pgsql

FROM php:8.3-cli-alpine AS app

RUN apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install pdo_pgsql \
    && apk del .build-deps

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /build/node_modules/@hexlet/php-online-store-frontend/dist/. ./public/

CMD ["sh", "docker-entrypoint.sh"]
