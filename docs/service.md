# access-switch service

HTTP service to open or close public access to a site without stopping the app. A reverse proxy (or any client) calls `GET /check` before serving traffic: **200** allows the request through, **503** blocks it. [Traefik forwardAuth](https://doc.traefik.io/traefik/reference/routing-configuration/http/middlewares/forwardauth/) is a common integration, but the API is generic HTTP.

## HTTP endpoints

| Endpoint | Role |
|----------|------|
| `GET /check` | Visitor authorization (200 = allow, 503 = deny) |
| `POST /admin` | ON/OFF toggle (Bearer `ACCESS_SWITCH_TOKEN`) |
| `GET /health` | Healthcheck |

Full reference: [api.md](api.md).

## Docker image

Published as **[`dakwamine/access-switch`](https://hub.docker.com/r/dakwamine/access-switch)** on Docker Hub.

```bash
docker pull dakwamine/access-switch:latest
```

Build locally from the repository root:

```bash
docker build -t dakwamine/access-switch:local .
```

## Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `ACCESS_SWITCH_TOKEN` | — | Admin API secret (required in production) |
| `STATE_FILE` | `/data/state.json` | Persisted open/closed state |
| `DEFAULT_OPEN` | `false` | State when the file does not exist |

Copy [`.env.example`](../.env.example) to `.env` for local runs (do not commit).

## Source layout

```
public/index.php      # Entry point
src/                  # Application code (namespace AccessSwitch)
scripts/test-api.sh   # API smoke tests
Caddyfile             # FrankenPHP / Caddy (production image)
Dockerfile
composer.json
```

Production image serves HTTP with **[FrankenPHP](https://frankenphp.dev/)** (Caddy + PHP thread-safe) on port 8080, instead of the single-threaded `php -S` built-in server — useful when many concurrent clients hit `GET /check`. Local CI smoke tests still use `php -S` for a minimal PHP-only setup.

## Local tests

```bash
ddev start && ./scripts/test-api.sh
```

See [development.md](development.md).

## License

[GNU AGPL-3.0-or-later](../LICENSE) — [licensing.md](licensing.md).
