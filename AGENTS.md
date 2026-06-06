# AGENTS — access-switch

## Mission

HTTP access switch for Traefik (Raspberry Pi, Docker/Portainer): ON/OFF via API without stopping the app.

## Architecture

- **Root app**: `GET /check` / `GET /check/{serviceId}`, `POST /admin`, state in `/data/states/{serviceId}.json`.
- **Image**: `dakwamine/access-switch` on Docker Hub.
- **Deploy**: `deploy/docker-compose.yml` (image pull only).
- **Docs**: `docs/README.md`, `docs/service.md`, `docs/api.md`.

## Rules

- No VPN required for primary design.
- Toggle without compose redeploy.
- Protect `/admin` (token, internal network).
- Small, Pi-friendly changes.

## Follow-ups

- Traefik integration tests on Pi
- Admin rate limiting, metrics
