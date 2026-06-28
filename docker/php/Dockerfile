FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    bash \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Non-root user (uid 1000 avoids root in production)
RUN addgroup -g 1000 appgroup && adduser -u 1000 -G appgroup -s /bin/sh -D appuser \
    && mkdir -p /var/www/backend && chown appuser:appgroup /var/www/backend

WORKDIR /var/www/backend

# Copy backend source (build context = repo root)
COPY --chown=appuser:appgroup backend/ .

USER appuser
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Copy entrypoints and make executable (needs root)
USER root
COPY docker/php/entrypoint.sh /entrypoint.sh
COPY docker/php/worker-entrypoint.sh /worker-entrypoint.sh
RUN chmod +x /entrypoint.sh /worker-entrypoint.sh

USER appuser

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
