FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

FROM dunglas/frankenphp:1-php8.5-alpine
LABEL org.opencontainers.image.source="https://github.com/dakwamine/access-switch"
LABEL org.opencontainers.image.title="access-switch"
RUN apk add --no-cache tini su-exec
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY composer.json ./
COPY public ./public
COPY resources ./resources
COPY src ./src
COPY Caddyfile /etc/caddy/Caddyfile
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN addgroup -g 1000 app && adduser -u 1000 -G app -D app \
    && mkdir -p /data /config/caddy \
    && chown -R app:app /app /data /etc/caddy /config \
    && chmod +x /docker-entrypoint.sh
EXPOSE 8080
ENV SERVER_NAME=:8080
# Override FrankenPHP default (curl localhost:2019/metrics) — incompatible with `admin off`.
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://127.0.0.1:8080/health || exit 1
ENTRYPOINT ["/docker-entrypoint.sh"]
