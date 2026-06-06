# Deployment

## Docker image

Official image on Docker Hub:

```text
dakwamine/access-switch:latest
dakwamine/access-switch:<version>   # e.g. 1.0.0 on git tag
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
4. **forwardAuth URL** — `http://access-switch:8080/check` (service name = hostname on the network).

### Traefik labels

```yaml
- traefik.http.routers.<name>.middlewares=access-check
- traefik.http.middlewares.access-check.forwardauth.address=http://access-switch:8080/check
```

## CI/CD

| Workflow | Role |
|----------|------|
| [`.github/workflows/test.yml`](../.github/workflows/test.yml) | PHPUnit, then API smoke tests (`php -S` + `scripts/test-api.sh`) on every push/PR |
| [`.github/workflows/docker-publish.yml`](../.github/workflows/docker-publish.yml) | Same tests, then build and push `dakwamine/access-switch` to Docker Hub on `main` / `master` or semver tag (e.g. `1.0.0`) |

### Docker Hub publish (maintainers)

Release tag (SemVer, **no `v` prefix** — same as the Docker image tag):

```bash
git tag 1.0.0
git push origin 1.0.0
```

GitHub repository secrets:

| Secret | Purpose |
|--------|---------|
| `DOCKERHUB_USERNAME` | Docker Hub user (`dakwamine`) |
| `DOCKERHUB_TOKEN` | Access token (read/write) |

Pin a version in production:

```yaml
image: dakwamine/access-switch:1.0.0
```

## Production security

- Do not expose `POST /admin` on the public Internet without extra controls.
- Prefer the admin API on the internal Docker network only.
- `DEFAULT_OPEN=false` and a named volume on `/data`.

See [SECURITY.md](../SECURITY.md).

## n8n

**HTTP Request**: `POST http://access-switch:8080/admin` with `Authorization: Bearer <token>` and JSON `{"open": true|false}`.

## Portainer

Add the `access-switch` service from the compose file, volume `access-switch-state`, and environment variables.
