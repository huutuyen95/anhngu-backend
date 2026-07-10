# ---- backend repo: Dockerfile (php-fpm cho Laravel) ----
FROM php:8.4-fpm-alpine

# Cài extension bằng công cụ chính thức (tự lo thư viện phụ thuộc + dọn dẹp)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_mysql mbstring zip exif pcntl bcmath gd intl redis

RUN apk add --no-cache git curl bash

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 1) Cache layer: cài dependency trước (KHÔNG chạy script, chưa dựng autoloader)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

# 2) Copy toàn bộ source
COPY . .

# 3) Dựng autoloader (KHÔNG chạy artisan package:discover lúc build -> tránh lỗi)
RUN composer dump-autoload --optimize --no-scripts \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]