# access-switch service

HTTP service to open or close public access to one or more sites without stopping the apps. A reverse proxy (or any client) calls `GET /check` (or `GET /check/{serviceId}`) before serving traffic: **200** allows the request through, **503** blocks it. [Traefik forwardAuth](https://doc.traefik.io/traefik/reference/routing-configuration/http/middlewares/forwardauth/) is a common integration, but the API is generic HTTP.

## HTTP endpoints

| Endpoint | Role |
|----------|------|
| `GET /check` | Visitor authorization for the **default** service |
| `GET /check/{serviceId}` | Visitor authorization for a named service |
| `POST /admin` | ON/OFF toggle per service (Bearer `ACCESS_SWITCH_TOKEN`) |
| `GET /health` | Healthcheck |
| `GET /ui` | Admin web UI (requires `UI_ENABLED=true`) |
| `GET /admin/status` | List services and states (UI / admin auth) |
| `POST /ui/login` | UI session login |
| `POST /ui/logout` | Clear UI session cookie |

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
| `ACCESS_SWITCH_UI_SECRET` | *(token)* | Optional separate key for UI session cookie HMAC |
| `DEFAULT_OPEN` | `false` | State when the state file does not exist |
| `AUTHORIZED_SERVICES` | *(empty)* | Optional extra restriction (CSV); when set, only listed ids are allowed |
| `UI_ENABLED` | `false` | Serve `/ui` and UI admin routes; keep **false** unless exposed on LAN/VPN only |
| `UI_SESSION_TTL` | `2592000` | UI session cookie lifetime in seconds (30 days) |
| `UI_COOKIE_SECURE` | `false` | Set `Secure` on UI cookie (use `true` behind HTTPS) |

Fixed paths inside the container (mount a volume on `/data`):

| Path | Role |
|------|------|
| `/data/states/{serviceId}.json` | Persisted open/closed state |
| `/data/services.json` | Optional authorized-services list (writable) |

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
