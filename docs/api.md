# API — access-switch

Lightweight HTTP service for [Traefik ForwardAuth](https://doc.traefik.io/traefik/reference/routing-configuration/http/middlewares/forwardauth/).

Typical base URL on the Docker network: `http://access-switch:8080`  
(Compose service name must match: `access-switch`.)

## `GET /health`

Health check (Docker healthcheck, monitoring).

| Code | Body |
|------|------|
| 200 | `{"status":"ok"}` |

## `GET /check`

Called by Traefik **forwardAuth** before each visitor request.

Checks the **`default`** service (same as `GET /check/default`).

| State | Code | Traefik effect |
|-------|------|----------------|
| Access **open** | 200 | Request forwarded to the application |
| Access **closed** | 503 | Request blocked (Service Unavailable) |
| Unknown / unauthorized service | 503 | Fail-closed |
| State read error | 503 | Fail-closed |

Empty response body (sufficient for forwardAuth).

## `GET /check/{serviceId}`

Same as `GET /check`, but for a named service (e.g. `toto` → `/check/toto`).

Each Traefik router can point its forwardAuth middleware to a different service URL.

## `POST /admin`

Toggles access for one service. **Expose only on a trusted network** (internal Docker, LAN, admin VPN).

### Authentication

Required header:

```http
Authorization: Bearer <ACCESS_SWITCH_TOKEN>
```

Constant-time comparison (`hash_equals`). Token set via the `ACCESS_SWITCH_TOKEN` environment variable.

### Body (JSON)

```json
{ "open": true, "service": "toto" }
```

| Field | Type | Description |
|-------|------|-------------|
| `open` | boolean | `true` = public access allowed, `false` = closed |
| `service` | string (optional) | Service id; defaults to `default` |

### Responses

| Code | Situation |
|------|-----------|
| 200 | State saved; body `{"service":"…","open":bool,"updated_at":"ISO8601"}` |
| 400 | Invalid JSON, missing / non-boolean `open`, invalid or unauthorized `service` |
| 401 | Missing or invalid token |
| 429 | Too many failed auth attempts from the same client IP |
| 503 | `ACCESS_SWITCH_TOKEN` not configured on the server |
| 500 | Failed to write state file |

### Examples

```bash
# Close access (default service)
curl -sS -X POST http://access-switch:8080/admin \
  -H "Authorization: Bearer $ACCESS_SWITCH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"open": false}'

# Open access for a named service
curl -sS -X POST http://access-switch:8080/admin \
  -H "Authorization: Bearer $ACCESS_SWITCH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"service": "toto", "open": true}'
```

## Admin UI

Optional web interface to list and toggle services. **Disabled by default** (`UI_ENABLED=false`).

### Enable

Set in environment:

```yaml
UI_ENABLED: "true"
```

When disabled, `GET /ui`, `GET /admin/status`, `POST /ui/login`, and `POST /ui/logout` return **404** (routes hidden).

### Exposure

Expose the UI **only on LAN or VPN** (Traefik internal entrypoint, private hostname, IP allowlist). Do not publish `/ui` on the public Internet.

Example Traefik router (LAN hostname, no public DNS):

```yaml
- traefik.http.routers.access-switch-ui.rule=Host(`switch.home.lan`)
- traefik.http.routers.access-switch-ui.entrypoints=lan
- traefik.http.routers.access-switch-ui.service=access-switch-ui
- traefik.http.services.access-switch-ui.loadbalancer.server.port=8080
```

### `GET /ui`

Serves a minimal HTML page (list + toggle buttons). No auth required to load the page; actions require login.

| Code | Situation |
|------|-----------|
| 200 | HTML page |
| 404 | `UI_ENABLED=false` |

### `GET /admin/status`

Returns open/closed state for all authorized services.

**Authentication:** same as `POST /admin` — `Authorization: Bearer <token>` **or** valid UI session cookie (after `POST /ui/login`).

| Code | Body |
|------|------|
| 200 | `{"services":[{"service":"…","open":bool,"updated_at":"ISO8601\|null"},…]}` |
| 401 | Missing or invalid auth |
| 404 | `UI_ENABLED=false` |

### `POST /ui/login`

Body (JSON):

```json
{ "token": "<ACCESS_SWITCH_TOKEN>" }
```

On success, sets an `HttpOnly` session cookie (`access_switch_ui`). The browser can then call `/admin/status` and `/admin` without resending the Bearer header.

| Code | Situation |
|------|-----------|
| 200 | Cookie set |
| 401 | Invalid token |
| 429 | Too many failed login attempts from the same client IP |
| 404 | `UI_ENABLED=false` |
| 503 | `ACCESS_SWITCH_TOKEN` not configured |

### `POST /ui/logout`

Clears the UI session cookie in the browser. Stateless sessions remain valid until expiry if the cookie value was copied elsewhere.

| Code | Situation |
|------|-----------|
| 200 | Cookie cleared |
| 404 | `UI_ENABLED=false` |

## Persistence

State is stored under fixed paths inside the container (mount a volume on `/data`):

| Path | Role |
|------|------|
| `/data/states/{serviceId}.json` | Open/closed state per service |
| `/data/services.json` | Optional authorized-services list (writable; bind-mount or volume) |
| `/data/state.json` | Legacy read-only fallback for `default` until migrated |

Example state file (`/data/states/toto.json`):

```json
{
  "open": false,
  "updated_at": "2026-06-03T12:00:00+00:00"
}
```

Survives container restarts when a volume is mounted on `/data`.

## Authorized services

The **`default`** service is always allowed.

Other services are authorized according to this hierarchy (each level can only **restrict** further):

| Layer | When active | Effect |
|-------|-------------|--------|
| **Existing state file** | No `/data/services.json` | A service with `/data/states/{id}.json` already on disk works for `GET /check` |
| **`/data/services.json`** | File present | Only services listed in the file are allowed (replaces discovery by state file) |
| **`AUTHORIZED_SERVICES`** | Env var non-empty | Only services listed in the env var are allowed (restricts even `services.json`) |

Examples:

- No config, no `services.json` — `toto` works after its state file exists (via a prior `POST /admin`, or manual file creation).
- `services.json` lists `toto` — only listed services work; an existing state file for `autre` is ignored if `autre` is not in the file.
- `AUTHORIZED_SERVICES=toto` and `services.json` lists `toto,autre` — `autre` returns **503** on `/check`.

`POST /admin` follows the same rules, except when neither `services.json` nor `AUTHORIZED_SERVICES` is set: any valid service id can be toggled (creates the state file on first write).

Service ids must match `^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$`.

### `/data/services.json`

Optional JSON array (writable volume — not read-only):

```json
["toto", "autre-app"]
```

Bind-mount or persist on the `/data` volume.

## Environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `ACCESS_SWITCH_TOKEN` | *(empty)* | Admin secret; if empty, `/admin` returns 503 |
| `ACCESS_SWITCH_UI_SECRET` | *(same as token)* | HMAC key for UI session cookies; when set, only this signs cookies (API Bearer still uses `ACCESS_SWITCH_TOKEN`) |
| `DEFAULT_OPEN` | `false` | State when the file does not exist yet |
| `AUTHORIZED_SERVICES` | *(empty)* | Additional restriction: comma-separated ids; when set, only listed services are allowed |
| `UI_ENABLED` | `false` | Enable `/ui` and related routes; expose only on LAN/VPN |
| `UI_SESSION_TTL` | `2592000` | UI session cookie lifetime (seconds) |
| `UI_COOKIE_SECURE` | `false` | Add `Secure` flag to UI cookie (HTTPS) |
| `RATE_LIMIT_MAX_ATTEMPTS` | `2` | Failed auth attempts per IP per window on `POST /admin` and `POST /ui/login` only; successful auth is not counted; once the quota is reached, **429** on any further request (including a valid token) until the window expires; `0` disables |
| `RATE_LIMIT_WINDOW_SECONDS` | `60` | Rate-limit window in seconds |
| `TRUSTED_PROXIES` | *(empty)* | Comma-separated proxy IPs or IPv4 CIDRs; when `REMOTE_ADDR` matches, client IP is taken from `X-Real-IP` then leftmost `X-Forwarded-For` |
| `LOG_CLIENT_IP` | `false` | Log client IP fields on `GET /ui`, `POST /admin`, and `POST /ui/login` (via FrankenPHP/Caddy logger → `docker logs`) |

See also [deployment.md](deployment.md).
