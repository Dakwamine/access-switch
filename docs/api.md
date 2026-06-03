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

| State | Code | Traefik effect |
|-------|------|----------------|
| Access **open** | 200 | Request forwarded to the application |
| Access **closed** | 503 | Request blocked (Service Unavailable) |
| State read error | 503 | Fail-closed |

Empty response body (sufficient for forwardAuth).

## `POST /admin`

Toggles access. **Expose only on a trusted network** (internal Docker, LAN, admin VPN).

### Authentication

Required header:

```http
Authorization: Bearer <ACCESS_SWITCH_TOKEN>
```

Constant-time comparison (`hash_equals`). Token set via the `ACCESS_SWITCH_TOKEN` environment variable.

### Body (JSON)

```json
{ "open": true }
```

| Field | Type | Description |
|-------|------|-------------|
| `open` | boolean | `true` = public access allowed, `false` = closed |

### Responses

| Code | Situation |
|------|-----------|
| 200 | State saved; body `{"open":bool,"updated_at":"ISO8601"}` |
| 400 | Invalid JSON or missing / non-boolean `open` field |
| 401 | Missing or invalid token |
| 503 | `ACCESS_SWITCH_TOKEN` not configured on the server |
| 500 | Failed to write state file |

### Examples

```bash
# Close access
curl -sS -X POST http://access-switch:8080/admin \
  -H "Authorization: Bearer $ACCESS_SWITCH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"open": false}'

# Open access
curl -sS -X POST http://access-switch:8080/admin \
  -H "Authorization: Bearer $ACCESS_SWITCH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"open": true}'
```

## Persistence

State stored in `STATE_FILE` (default: `/data/state.json`):

```json
{
  "open": false,
  "updated_at": "2026-06-03T12:00:00+00:00"
}
```

Survives container restarts when a volume is mounted on `/data`.

## Environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `ACCESS_SWITCH_TOKEN` | *(empty)* | Admin secret; if empty, `/admin` returns 503 |
| `STATE_FILE` | `/data/state.json` | State file path |
| `DEFAULT_OPEN` | `false` | State when the file does not exist yet |

See also [deployment.md](deployment.md).
