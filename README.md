# access-switch

[Tests](https://github.com/dakwamine/access-switch/actions/workflows/test.yml)

HTTP **ON/OFF** switch for sites behind **Traefik**: open or close public access in seconds **without stopping** your app.

Docker image: **[dakwamine/access-switch](https://hub.docker.com/r/dakwamine/access-switch)**

## How it works

```text
Visitor → Traefik → GET /check (forwardAuth) → 200 = serve app | 503 = blocked
You     → POST /admin (Bearer token)         → {"open": true|false}
```

1. **Traefik** calls `GET /check` before each request to your site.
2. **access-switch** answers **200** (access granted) or **503** (access denied).
3. You **toggle** that state with `POST /admin` (curl, n8n, cron — or external network apps if you're adventurous).

## Switch access on or off

Set `ACCESS_SWITCH_TOKEN` in your environment, then:

```bash
# Close public access (visitors get 503)
curl -sS -X POST http://access-switch:8080/admin \
  -H "Authorization: Bearer $ACCESS_SWITCH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"open": false}'

# Open public access
curl -sS -X POST http://access-switch:8080/admin \
  -H "Authorization: Bearer $ACCESS_SWITCH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"open": true}'
```

Use the Docker service hostname `access-switch` (or your compose service name) on the same network as Traefik.

## Check endpoint (Traefik forwardAuth)

Traefik does **not** call `/admin`. It only calls:

```http
GET http://access-switch:8080/check
```


| Response | Meaning                                                               |
| -------- | --------------------------------------------------------------------- |
| **200**  | Access is **open** — Traefik forwards the request to your application |
| **503**  | Access is **closed** — Traefik blocks the visitor                     |


No body required. This runs automatically on every visit once the middleware below is configured.

## Traefik labels (example)

Attach a **forwardAuth** middleware to your app router. The app stays routed by Traefik; access-switch only approves or denies:

```yaml
# On your application service (e.g. mon-app-web)
labels:
  - traefik.enable=true
  - traefik.http.routers.myapp.rule=Host(`app.example.com`)
  - traefik.http.routers.myapp.entrypoints=websecure
  - traefik.http.routers.myapp.tls=true
  - traefik.http.routers.myapp.middlewares=access-check
  - traefik.http.middlewares.access-check.forwardauth.address=http://access-switch:8080/check
  - traefik.http.services.myapp.loadbalancer.server.port=3000   # your app port
```

Run **access-switch** on the same Docker network as Traefik and the app. Full stack: `[deploy/docker-compose.yml](deploy/docker-compose.yml)`.

## Deploy access-switch

```bash
docker pull dakwamine/access-switch:latest
```

```yaml
# Minimal service (add to your compose)
access-switch:
  image: dakwamine/access-switch:latest
  environment:
    ACCESS_SWITCH_TOKEN: ${ACCESS_SWITCH_TOKEN}
    DEFAULT_OPEN: "false"          # start closed if no state file yet
  volumes:
    - access-switch-state:/data     # persists open/closed across restarts
  networks:
    - traefik
```

## Features

- **Lightweight** — PHP 8.3, Alpine, Pi-friendly (amd64 + arm64)
- **Persistent state** — `/data/state.json` on a volume
- **Fail-closed** — errors reading state → visitors blocked (503)

## Documentation


| Guide                                        | Description                   |
| -------------------------------------------- | ----------------------------- |
| [docs/api.md](docs/api.md)                   | Full API reference            |
| [docs/deployment.md](docs/deployment.md)     | Production, Pi, Docker Hub CI |
| [docs/architecture.md](docs/architecture.md) | Design and alternatives       |
| [docs/development.md](docs/development.md)   | Local dev and tests           |


## Local development

```bash
composer install && composer test
ddev start && bash scripts/test-api.sh
```

## License

**[GNU AGPL-3.0-or-later](LICENSE)** — [docs/licensing.md](docs/licensing.md).