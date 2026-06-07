FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

FROM dunglas/frankenphp:1-php8.5-alpine
LABEL org.opencontainers.image.source="https://github.com/dakwamine/access-switch"
LABEL org.opencontainers.image.title="access-switch"
RUN apk add --no-cache tini
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY composer.json ./
COPY public ./public
COPY resources ./resources
COPY src ./src
COPY Caddyfile /etc/caddy/Caddyfile
RUN mkdir -p /data
EXPOSE 8080
ENV SERVER_NAME=:8080
ENTRYPOINT ["/sbin/tini", "--"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
