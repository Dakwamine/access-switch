# Contributing

Thank you for your interest in **access-switch**.

## Prerequisites

- PHP 8.2+ (8.3 recommended)
- Docker / DDEV (optional)

## Workflow

1. Fork and branch (`feat/...`, `fix/...`).
2. Edit code at the repository root (`src/`, `public/`).
3. Run tests:

   ```bash
   composer install
   composer test
   ```

   Optional API smoke tests:

   ```bash
   export ACCESS_SWITCH_TOKEN=test STATE_FILE=$PWD/data/state.json
   mkdir -p data
   php -S 127.0.0.1:8080 -t public public/index.php &
   DDEV_PRIMARY_URL=http://127.0.0.1:8080 ACCESS_SWITCH_TOKEN=test ./scripts/test-api.sh
   ```

4. Open a pull request.

## Style

- PHP: `declare(strict_types=1);`, namespace `AccessSwitch`, no framework.
- Documentation in English under `docs/`.
- No secrets in Git.

## License

Contributions are licensed under [GNU AGPL-3.0-or-later](LICENSE). See [docs/licensing.md](docs/licensing.md).

## Security

See [SECURITY.md](SECURITY.md).
