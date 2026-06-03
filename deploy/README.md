# Deployment

Uses the published image **`dakwamine/access-switch`** (no local build).

```bash
cp docker-compose.yml /path/to/your-stack/
# Set ACCESS_SWITCH_TOKEN in .env
docker compose pull
docker compose up -d
```

Documentation: [../docs/deployment.md](../docs/deployment.md).
