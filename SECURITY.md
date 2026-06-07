# Security

## Threat model

**access-switch** controls whether Traefik forwards traffic to your application. It is not end-user authentication. `POST /admin` is a **global switch**: anyone with `ACCESS_SWITCH_TOKEN` can open or close public access.

## Admin UI (`UI_ENABLED`)

The optional UI (`UI_ENABLED=true`, default **false**) adds browser-based toggling. Treat it like `/admin`:

1. **`UI_ENABLED=false`** in production unless you need the UI — routes return 404 when disabled.
2. **Network** — expose `/ui` only on LAN or VPN (Traefik private router, no public DNS). Never publish it on the Internet.
3. **Session cookie** — signed with `ACCESS_SWITCH_TOKEN`; `HttpOnly`, `SameSite=Strict`. Logout clears the browser cookie; a copied cookie stays valid until `UI_SESSION_TTL` (stateless design).
4. **`UI_COOKIE_SECURE=true`** when the UI is served over HTTPS.

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
