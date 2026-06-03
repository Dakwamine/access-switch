# access-switch service

PHP application for [Traefik ForwardAuth](https://doc.traefik.io/traefik/reference/routing-configuration/http/middlewares/forwardauth/): open or close public access to a site without stopping the app.

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
Dockerfile
composer.json
```

## Local tests

```bash
ddev start && ./scripts/test-api.sh
```

See [development.md](development.md).

## License

[GNU AGPL-3.0-or-later](../LICENSE) — [licensing.md](licensing.md).
