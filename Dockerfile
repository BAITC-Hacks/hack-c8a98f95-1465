FROM node:22-alpine AS frontend
WORKDIR /src/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ENV VITE_API_URL=/api
RUN npm run build:unified

FROM php:8.2-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev libsqlite3-dev libzip-dev unzip ca-certificates \
    && docker-php-ext-install mbstring pdo_sqlite opcache zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY backend/ ./
ENV APP_ENV=production APP_DEBUG=false PORT=8080 \
    DB_CONNECTION=sqlite DB_DATABASE=/data/database.sqlite \
    CACHE_STORE=file SESSION_DRIVER=array QUEUE_CONNECTION=sync \
    LOG_CHANNEL=stderr LOG_LEVEL=warning DEMO_MODE=true AI_PROVIDER=mock
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs /data
COPY --from=frontend /src/backend/public/build/ ./public/build/
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/ports.conf /etc/apache2/ports.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/alemedu.ini
COPY deploy/container-prepare.php ./deploy/container-prepare.php
COPY deploy/entrypoint.sh /usr/local/bin/alemedu-start
RUN chmod +x /usr/local/bin/alemedu-start
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:".getenv("PORT")."/api/health") === false ? 1 : 0);'
CMD ["alemedu-start"]
