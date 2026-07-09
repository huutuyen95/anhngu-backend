# ---- backend repo: Dockerfile (php-fpm cho Laravel) ----
FROM php:8.3-fpm-alpine

# Thư viện hệ thống + extension PHP cần cho Laravel
RUN apk add --no-cache \
        git curl bash libpng-dev libzip-dev icu-dev oniguruma-dev mysql-client \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd intl \
    && apk add --no-cache --virtual .redis-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .redis-deps

# Composer (copy binary từ image chính thức)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Cache layer: cài dependency trước khi copy toàn bộ source
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction --prefer-dist

# Copy source
COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
