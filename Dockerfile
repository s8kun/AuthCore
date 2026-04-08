FROM php:8.3-fpm

ARG APP_VERSION=latest
ARG APP_ENV=production

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    supervisor \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

RUN pecl install redis && docker-php-ext-enable redis

RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY --from=node:22-bookworm /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node:22-bookworm /usr/local/include/node /usr/local/include/node
ENV PATH="/usr/local/lib/node_modules/npm/bin:${PATH}"

RUN npm install -g npm@latest

COPY . .

RUN composer install --optimize-autoloader --no-dev

RUN php artisan optimize

RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache

RUN npm ci && npm run build

RUN chmod -R 755 storage bootstrap/cache
RUN chown -R www-data:www-data /var/www

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]