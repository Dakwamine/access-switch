# Development

How to work on the code locally and validate changes before a pull request.

## Quick start

| Setup | Commands |
|-------|----------|
| **DDEV** (recommended on Windows/WSL) | `ddev start` → `ddev composer install` → `ddev exec composer test` |
| **Native PHP** | `composer install` → `composer test` |

Details below. For API reference and deployment, see [api.md](api.md) and [deployment.md](deployment.md).

## Choose your environment

### Option A — DDEV (recommended on Windows/WSL)

**Requires:** [DDEV](https://ddev.com/) v1.25+, Docker (Docker Desktop with WSL integration on Windows).

Configuration: `.ddev/config.yaml` at the repository root.

```bash
ddev start
ddev composer install
ddev exec composer test
./scripts/test-api.sh
```

- App URL: `https://access-switch.ddev.site`
- Dev token: `dev-token-change-me` (set in `.ddev/config.yaml` as `ACCESS_SWITCH_TOKEN`)

**Windows:** use a WSL2 terminal at the repository root. With Docker Desktop’s WSL integration enabled, `ddev` and `docker` behave like on Linux. Prefer DDEV over installing PHP 8.5 manually in WSL.

### Option B — Native PHP

**Requires:** PHP 8.5+, [Composer](https://getcomposer.org/), and extensions needed by PHPUnit (`dom`, `json`, `libxml`, `mbstring`, `tokenizer`, `xml`, `xmlwriter`).

From the repository root:

```bash
composer install
composer test
```

## Tests

### Unit tests (PHPUnit)

```bash
composer test
```

With DDEV: `ddev exec composer test`.

Optional coverage (PCOV or Xdebug on your machine):

```bash
composer test:coverage   # writes build/clover.xml
```

- Tests: `tests/`
- Config: `phpunit.xml.dist`, coverage: `phpunit.coverage.xml.dist`

### API smoke tests

Script: `scripts/test-api.sh` — exercises `/health`, `/check`, and `/admin` against a running server.

**With DDEV** (server already running after `ddev start`):

```bash
./scripts/test-api.sh
```

Uses `https://access-switch.ddev.site` and token `dev-token-change-me` by default.

**With the PHP built-in server** (no DDEV):

```bash
export ACCESS_SWITCH_TOKEN=test
export DEFAULT_OPEN=false
sudo mkdir -p /data/states
sudo chmod 777 /data /data/states
php -S 127.0.0.1:8080 -t public public/index.php &
DDEV_PRIMARY_URL=http://127.0.0.1:8080 ./scripts/test-api.sh
```

| Variable | Role |
|----------|------|
| `DDEV_PRIMARY_URL` | Base URL of the running app (name kept for CI compatibility) |
| `ACCESS_SWITCH_TOKEN` | Bearer token for `POST /admin` |

State files are written to `/data/states/` (fixed path). DDEV mounts the project `data/` directory on `/data` via `.ddev/docker-compose.data.yaml`.

CI runs the same smoke tests with `php -S`; see [deployment.md](deployment.md#cicd).

## Validate the production image (optional)

The published image serves HTTP with [FrankenPHP](https://frankenphp.dev/) (`Caddyfile`, `Dockerfile`) — not `php -S`. Use this before changing the Docker build:

```bash
docker build -t dakwamine/access-switch:local .
docker run --rm -e ACCESS_SWITCH_TOKEN=test -p 8080:8080 dakwamine/access-switch:local
curl -s http://127.0.0.1:8080/health
```

## See also

| Document | Contents |
|----------|----------|
| [CONTRIBUTING.md](../CONTRIBUTING.md) | Fork, branch, PR workflow, code style |
| [deployment.md](deployment.md) | Production deploy, CI/CD workflows |
| [service.md](service.md) | Service overview, configuration, source layout |
| [api.md](api.md) | HTTP endpoints |
