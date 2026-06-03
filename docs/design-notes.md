# Design notes

Background for a **Raspberry Pi / Traefik / Portainer** setup.

## Stated need

Toggle public access to a site **without stopping** containers, via HTTP (curl, n8n).

## Requirements retained

1. No VPN for the primary scenario.
2. Very fast toggle (no full stack redeploy).
3. Access control in front of the app (not direct public exposure).

## Decision

**Traefik forwardAuth** + **access-switch** service:

- `GET /check` before each visitor request.
- `POST /admin` + `ACCESS_SWITCH_TOKEN` toggles persisted state.

Alternatives in [architecture.md](architecture.md).

## References

- [Traefik ForwardAuth](https://doc.traefik.io/traefik/reference/routing-configuration/http/middlewares/forwardauth/)
- [Traefik Manager](https://github.com/chr0nzz/traefik-manager)
