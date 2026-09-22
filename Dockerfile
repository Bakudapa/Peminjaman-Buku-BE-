# ---- Stage 1: Composer dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---- Stage 2: Runtime ----
FROM php:8.5-fpm-alpine

RUN apk add --no-cache postgresql-dev libzip-dev zip unzip \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]