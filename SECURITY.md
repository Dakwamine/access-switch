# Security

## Threat model

**access-switch** controls whether Traefik forwards traffic to your application. It is not end-user authentication. `POST /admin` is a **global switch**: anyone with `ACCESS_SWITCH_TOKEN` can open or close public access.

## Best practices

1. **`ACCESS_SWITCH_TOKEN`** — long random secret; `.env` or secrets manager, never in Git.
2. **Network** — keep `/admin` on the Docker internal network (or protected LAN).
3. **`DEFAULT_OPEN=false`** — closed by default when no state file exists.
4. **Volume `/data`** — restrict host permissions if needed.

## Failure behavior

- State read error → `GET /check` returns **503** (fail-closed).
- Empty `ACCESS_SWITCH_TOKEN` → `POST /admin` returns **503**.

## Reporting

Contact the maintainer privately; do not file a public issue with exploit details.
