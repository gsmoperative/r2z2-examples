# R2Z2 Python Example

Python client for the zKillboard R2Z2 ephemeral API. FastAPI server with a built-in poller, MySQL storage, and 2-level filter pipeline.

## Setup

```bash
pip install -r requirements.txt
mysql -u root -e "CREATE DATABASE IF NOT EXISTS zkillboard"
mysql -u root zkillboard < schema.sql
```

## Run

```bash
uvicorn main:app --reload
```

Starts the API server and background poller. Docs at `http://localhost:8000/docs`.

## API

- `GET /kills` - list kills with filtering (`limit`, `offset`, `min_value`, `max_value`, `solar_system_id`, `ship_type_id`, `character_id`, `corporation_id`, `alliance_id`, `npc`, `solo`, `awox`)
- `GET /kills/{killmail_id}` - full killmail with attackers and nested items
- `GET /stats` - aggregate stats

## Config

All via environment variables:

| Var | Default |
|-----|---------|
| `DB_HOST` | 127.0.0.1 |
| `DB_PORT` | 3306 |
| `DB_NAME` | zkillboard |
| `DB_USER` | root |
| `DB_PASS` | |
| `POLLER_ENABLED` | true |
| `POLLER_EXCLUDE_NPC` | true |
| `POLLER_SECURITY_ZONES` | nullsec,lowsec |
| `POLLER_MIN_VALUE` | 10000000 |
