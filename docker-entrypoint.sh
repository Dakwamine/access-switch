#!/bin/sh
set -e

# Bind-mounts are often root-owned on the host; fix ownership at start (runs as root).
for dir in /data /config; do
    if [ -d "$dir" ]; then
        chown -R app:app "$dir" 2>/dev/null || true
    fi
done

exec su-exec app:app /sbin/tini -- frankenphp run --config /etc/caddy/Caddyfile
