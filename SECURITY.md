# Security

## Threat model

**access-switch** controls whether Traefik forwards traffic to your application. It is not end-user authentication. `POST /admin` is a **global switch**: anyone with `ACCESS_SWITCH_TOKEN` can open or close public access.

## Admin UI (`UI_ENABLED`)

The optional UI (`UI_ENABLED=true`, default **false**) adds browser-based toggling. Treat it like `/admin`:

1. **`UI_ENABLED=false`** in production unless you need the UI — routes return 404 when disabled.
2. **Network** — expose `/ui` only on LAN or VPN (Traefik private router, no public DNS). Never publish it on the Internet.
3. **Session cookie** — signed with `ACCESS_SWITCH_UI_SECRET` when set, otherwise `ACCESS_SWITCH_TOKEN`; `HttpOnly`, `SameSite=Strict`. Logout clears the browser cookie; a copied cookie stays valid until `UI_SESSION_TTL` (stateless design).
4. **Rate limiting** — `POST /admin` and `POST /ui/login` return **429** before checking credentials once the per-IP quota is reached (defaults: **2** per 60s → 3rd try blocked even with a valid token; set `RATE_LIMIT_MAX_ATTEMPTS=0` to disable). Behind Traefik, set `TRUSTED_PROXIES` so limits use the real client IP.
5. **`UI_COOKIE_SECURE=true`** when the UI is served over HTTPS.

## Best practices

1. **`ACCESS_SWITCH_TOKEN`** — long random secret; `.env` or secrets manager, never in Git. Optionally set **`ACCESS_SWITCH_UI_SECRET`** separately for UI session cookies (backward compatible: one secret still works for both).
2. **Network** — keep `/admin` on the Docker internal network (or protected LAN).
3. **`DEFAULT_OPEN=false`** — closed by default when no state file exists.
4. **Volume `/data`** — the container entrypoint adjusts ownership of `/data` and `/config` at start (runs briefly as root, then UID **1000**). Bind-mounts owned by root on the host are supported.

## Failure behavior

- State read error → `GET /check` returns **503** (fail-closed).
- Empty `ACCESS_SWITCH_TOKEN` → `POST /admin` returns **503**.

## Reporting

Contact the maintainer privately; do not file a public issue with exploit details.
