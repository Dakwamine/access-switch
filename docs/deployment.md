# Deployment

## Docker image

Official image on Docker Hub:

```text
dakwamine/access-switch:latest
dakwamine/access-switch:<version>   # e.g. 2.0.0 on git tag
```

```bash
docker pull dakwamine/access-switch:latest
```

Multi-arch: `linux/amd64`, `linux/arm64` (Raspberry Pi).

## Prerequisites

- Docker Compose v2
- [Traefik](https://traefik.io/) with Docker provider or file provider
- Shared Docker network (e.g. `traefik`) for Traefik, **access-switch**, and the application

## Compose example

[`deploy/docker-compose.yml`](../deploy/docker-compose.yml) — **image only**, no `build:`.

Customize:

1. **`ACCESS_SWITCH_TOKEN`** in `.env` (never commit).
2. **`traefik` network** — must exist.
3. **Application labels** — hostname, TLS, service port.
4. **forwardAuth URL** — per app, e.g. `http://access-switch:8080/check/toto` or `/check` for the default service.

### Data volume

Mount a named volume (or bind-mount) on **`/data`**:

- States: `/data/states/{serviceId}.json` (created on first admin toggle)
- Optional service list: bind-mount `./services.json:/data/services.json:ro`

Existing deployments with `/data/state.json` from v1.x: no action required — the `default` service reads that file until the first admin write migrates to `/data/states/default.json`.

### Traefik labels

One middleware per application / service:

```yaml
# Named service (e.g. toto)
- traefik.http.routers.toto.middlewares=access-check-toto
- traefik.http.middlewares.access-check-toto.forwardauth.address=http://access-switch:8080/check/toto

# Default service (legacy /check)
- traefik.http.routers.legacy.middlewares=access-check-default
- traefik.http.middlewares.access-check-default.forwardauth.address=http://access-switch:8080/check
```

### Authorized services

Three layers (each can only restrict further; `default` is always allowed):

1. **No `services.json`** — a service works once `/data/states/{id}.json` exists.
2. **`/data/services.json`** present — only listed services are allowed.
3. **`AUTHORIZED_SERVICES`** set — only listed env ids are allowed (restricts `services.json` too).

Configure via Compose env and/or a writable JSON file on `/data`:

```yaml
environment:
  AUTHORIZED_SERVICES: "toto"   # optional extra restriction
volumes:
  - access-switch-state:/data
  # optional explicit list (writable — a future UI may edit it):
  # - ./services.json:/data/services.json
```

```json
["toto", "autre-app"]
```

## CI/CD

| Workflow | Role |
|----------|------|
| [`.github/workflows/test.yml`](../.github/workflows/test.yml) | PHPUnit, then API smoke tests (`php -S` + `scripts/test-api.sh`) on every push/PR |
| [`.github/workflows/docker-publish.yml`](../.github/workflows/docker-publish.yml) | Same tests, then build and push `dakwamine/access-switch` to Docker Hub on `main` / `master` or semver tag (e.g. `2.0.0`) |

### Docker Hub publish (maintainers)

Release tag (SemVer, **no `v` prefix** — same as the Docker image tag):

```bash
git tag 2.0.0
git push origin 2.0.0
```

GitHub repository secrets:

| Secret | Purpose |
|--------|---------|
| `DOCKERHUB_USERNAME` | Docker Hub user (`dakwamine`) |
| `DOCKERHUB_TOKEN` | Access token (read/write) |

Pin a version in production:

```yaml
image: dakwamine/access-switch:2.0.0
```

## Production security

- Do not expose `POST /admin` on the public Internet without extra controls.
- Prefer the admin API on the internal Docker network only.
- **`UI_ENABLED=false`** by default; enable only when `/ui` is reachable on LAN/VPN.
- `DEFAULT_OPEN=false` and a named volume on `/data`.

See [SECURITY.md](../SECURITY.md).

## n8n

**HTTP Request**: `POST http://access-switch:8080/admin` with `Authorization: Bearer <token>` and JSON `{"open": true|false}` or `{"service":"toto","open":true}`.

## Portainer

Add the `access-switch` service from the compose file, volume `access-switch-state` on `/data`, and environment variables.

## Upgrading from 1.x to 2.0

1. Remove `STATE_FILE` from environment (variable removed).
2. Keep the `/data` volume — existing `/data/state.json` continues to work for the `default` service.
3. Add `AUTHORIZED_SERVICES` or bind-mount `/data/services.json` if you use named services.
4. Update Traefik forwardAuth URLs to `/check/{serviceId}` where needed.
