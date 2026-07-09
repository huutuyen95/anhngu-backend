#!/bin/sh
set -e

# Khi dev bind-mount source, thư mục vendor có thể bị che -> cài lại nếu thiếu
if [ ! -d vendor ]; then
  echo "Chưa có vendor/, đang chạy composer install..."
  composer install --no-interaction --prefer-dist
fi

# Tạo .env từ mẫu nếu chưa có (dev)
if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

# Chờ MySQL sẵn sàng (tối đa ~60s)
echo "Đang chờ MySQL..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
  sleep 2
done
echo "MySQL đã sẵn sàng."

# Tạo APP_KEY nếu chưa có
if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
  php artisan key:generate --force || true
fi

# Chạy migration (thêm --seed nếu muốn seed lần đầu)
php artisan migrate --force

# Cache config/route cho production (bỏ khi dev để hot-reload .env)
if [ "$APP_ENV" = "production" ]; then
  php artisan config:cache
  php artisan route:cache
fi

exec "$@"
