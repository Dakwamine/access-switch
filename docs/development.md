# Development

## Prerequisites

- PHP 8.3+ and Composer
- [DDEV](https://ddev.com/) (v1.25+ on **WSL2**, optional)

## Unit tests (PHPUnit)

```bash
composer install
composer test
```

Optional coverage report (PCOV or Xdebug on your machine):

```bash
composer test:coverage   # writes build/clover.xml
```

Tests live in `tests/`. Coverage config: `phpunit.coverage.xml.dist`.

## DDEV + WSL

Configuration at the **repository root**: `.ddev/config.yaml`.

```bash
ddev start
ddev composer install
ddev exec composer test
./scripts/test-api.sh
```

- URL: `https://access-switch.ddev.site`
- Dev token: `dev-token-change-me` (in `.ddev/config.yaml`)

## API smoke tests (integration)

```bash
export ACCESS_SWITCH_TOKEN=dev-token STATE_FILE=$PWD/data/state.json DEFAULT_OPEN=false
mkdir -p data
php -S 127.0.0.1:8080 -t public public/index.php &
DDEV_PRIMARY_URL=http://127.0.0.1:8080 ACCESS_SWITCH_TOKEN=dev-token ./scripts/test-api.sh
```

## Build image locally

```bash
docker build -t dakwamine/access-switch:local .
docker run --rm -e ACCESS_SWITCH_TOKEN=test -p 8080:8080 dakwamine/access-switch:local
```

## CI

| Workflow | Role |
|----------|------|
| `test.yml` | PHPUnit, then API smoke tests |
| `docker-publish.yml` | Same tests, then push `dakwamine/access-switch` to Docker Hub |
