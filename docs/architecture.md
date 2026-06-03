# Architecture — access-switch

## Requirements

| Constraint | Detail |
|------------|--------|
| No VPN | Public access when ON; no Tailscale/WireGuard dependency |
| Fast toggle | No `docker compose up` on each change |
| Running services | Application containers stay up |
| Trigger | `curl`, n8n, or other HTTP clients |
| Stack | Raspberry Pi, Docker, Portainer, Traefik |

## Logical diagram

```text
Internet → Traefik (Host: app.example.com) → forwardAuth middleware
                                                    ├─ OFF → 503
                                                    └─ ON  → app container

Application: routed by Traefik, not directly exposed without the middleware
Admin API: POST /admin on access-switch (token, internal network)
```

## Compared options

### 1. Dedicated reverse-proxy container

Single public hostname; proxy to the app when ON, 503 when OFF. Extra hop.

### 2. Traefik forwardAuth (chosen)

Router → app + middleware → `http://access-switch:8080/check`.  
ON: `200`; OFF: `503`. Native Traefik; one sub-request per visit.

### 3. Dynamic file provider

Edit Traefik YAML to enable/disable routes. No extra container; more ops-heavy.

### 4. Traefik Manager

Full UI/API for routes; heavy for one site on a Pi.

### 5. Host firewall

Blocks ports globally; not per-site Traefik routing.

## Deployment artifact

- **Image**: `dakwamine/access-switch`
- **Compose**: [deploy/docker-compose.yml](../deploy/docker-compose.yml)

## References

- [api.md](api.md) — HTTP API
- [deployment.md](deployment.md) — Traefik labels, Pi
- [development.md](development.md) — local setup
