# access-switch

[![Tests](https://github.com/dakwamine/access-switch/actions/workflows/test.yml/badge.svg)](https://github.com/dakwamine/access-switch/actions/workflows/test.yml)

HTTP **ON/OFF** switch for sites behind **Traefik** (or similar): cut or restore public access in seconds **without stopping** the application and **without a VPN**.

Docker image: **[dakwamine/access-switch](https://hub.docker.com/r/dakwamine/access-switch)**

## Features

- **Traefik forwardAuth** — `GET /check` allows or blocks each visit (503 when closed)
- **Admin API** — `POST /admin` + Bearer token to toggle state
- **Persistence** — JSON file on a Docker volume (`/data/state.json`)
- **Lightweight** — PHP 8.3, Alpine image, Pi-friendly (amd64 + arm64)
- **Automatable** — curl, n8n, scripts

## Quick start

```bash
# Pull and run (set ACCESS_SWITCH_TOKEN in .env first)
docker pull dakwamine/access-switch:latest

# Toggle access (on the Docker network)
curl -sS -X POST http://access-switch:8080/admin \
  -H "Authorization: Bearer $ACCESS_SWITCH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"open": true}'
```

Stack example with Traefik: `[deploy/docker-compose.yml](deploy/docker-compose.yml)`.

## Documentation


| Guide                                        | Description                            |
| -------------------------------------------- | -------------------------------------- |
| [docs/README.md](docs/README.md)             | Documentation index                    |
| [docs/service.md](docs/service.md)           | Service overview, image, source layout |
| [docs/architecture.md](docs/architecture.md) | Diagram and design choices             |
| [docs/api.md](docs/api.md)                   | API reference                          |
| [docs/deployment.md](docs/deployment.md)     | Production, Traefik, Pi, CI/CD image   |
| [docs/development.md](docs/development.md)   | DDEV, WSL, tests                       |
| [docs/licensing.md](docs/licensing.md)       | AGPL-3.0+                              |


## Repository layout

```
access-switch/
├── public/                 # HTTP entry point
├── src/                    # PHP application (AccessSwitch)
├── tests/                  # PHPUnit unit tests
├── scripts/                # API smoke tests (test-api.sh)
├── deploy/                 # docker-compose (image only)
├── docs/
├── Dockerfile
└── README.md
```

## Local development

```bash
composer install
composer test              # PHPUnit unit tests
ddev start && ./scripts/test-api.sh
```

See [docs/development.md](docs/development.md). Optional local coverage: `composer test:coverage` (requires PCOV or Xdebug).

## Publish Docker image (maintainers)

On push to `main` / `master` or tag `v*`, GitHub Actions runs tests then pushes to Docker Hub as `dakwamine/access-switch`.

Repository secrets: `DOCKERHUB_USERNAME`, `DOCKERHUB_TOKEN`.

## License

**[GNU AGPL-3.0-or-later](LICENSE)** (AGPL 3+). Summary: [docs/licensing.md](docs/licensing.md).