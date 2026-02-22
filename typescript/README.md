# R2Z2 TypeScript Example

TypeScript client for the zKillboard R2Z2 ephemeral API. Hono server with built-in poller, MySQL storage, and 2-level filter pipeline. Runs on Bun.

## Setup

```bash
bun install
mysql -u root -e "CREATE DATABASE IF NOT EXISTS zkillboard"
mysql -u root zkillboard < schema.sql
```

## Run

```bash
bun run dev     # watch mode
bun run start   # production
```

Starts the API server (port 3000) and background poller.

## API

- `GET /kills` - list kills (`limit`, `offset`, `min_value`, `max_value`, `solar_system_id`, `ship_type_id`, `character_id`, `corporation_id`, `alliance_id`, `npc`, `solo`, `awox`)
- `GET /kills/:id` - full killmail with attackers and nested items
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
| `PORT` | 3000 |
