FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

FROM php:8.3-cli-alpine
LABEL org.opencontainers.image.source="https://github.com/dakwamine/access-switch"
LABEL org.opencontainers.image.title="access-switch"
RUN apk add --no-cache tini
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY composer.json ./
COPY public ./public
COPY src ./src
RUN mkdir -p /data
EXPOSE 8080
ENTRYPOINT ["/sbin/tini", "--"]
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
