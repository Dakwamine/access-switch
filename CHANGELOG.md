# Changelog

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.1.0](https://github.com/dakwamine/access-switch/releases/tag/1.1.0) - 2026-06-04

### Changed

- **Docker image**: [FrankenPHP](https://frankenphp.dev/) (Caddy + `Caddyfile`) on port 8080 instead of the single-threaded `php -S` built-in server — concurrent handling of `GET /check`
- **PHP 8.5** across the project (FrankenPHP image, DDEV, GitHub Actions, `composer.json`)
- **PHPUnit 12** (dev dependency)

### Documentation

- Reorganized [docs/development.md](docs/development.md) (quick start, environment choice, tests, optional image validation)
- [docs/service.md](docs/service.md): describe access-switch as a generic HTTP gate; Traefik forwardAuth as a common integration example
- CI workflow overview moved to [docs/deployment.md](docs/deployment.md); [CONTRIBUTING.md](CONTRIBUTING.md) links to development guide for test commands

## [1.0.0](https://github.com/dakwamine/access-switch/releases/tag/1.0.0) - 2026-06-03

First public release.

### Added

- **access-switch** HTTP service: `GET /check` (Traefik forwardAuth), `POST /admin`, `GET /health`
- JSON state persistence with file locking (`STATE_FILE`, volume `/data`)
- Admin authentication via `ACCESS_SWITCH_TOKEN`
- Docker image **`dakwamine/access-switch`** (Alpine + PHP 8.5, amd64/arm64)
- GitHub Actions: PHPUnit unit tests, API smoke tests, test badge, Docker Hub publish on `main` / version tags
- DDEV environment and `scripts/test-api.sh`
- Documentation under `docs/` (architecture, API, deployment, development, service, licensing)
- `SECURITY.md`, `CONTRIBUTING.md`, `AGENTS.md`
- Traefik stack example: `deploy/docker-compose.yml` (pull image, no build)
- License: **GNU AGPL-3.0-or-later** (AGPL 3+)

[1.1.0]: https://github.com/dakwamine/access-switch/releases/tag/1.1.0
[1.0.0]: https://github.com/dakwamine/access-switch/releases/tag/1.0.0
