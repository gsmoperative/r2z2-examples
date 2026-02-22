# R2Z2 Examples

Example clients for the [zKillboard R2Z2 API](https://github.com/zKillboard/zKillboard/wiki/API-(R2Z2)) in PHP, Python, and TypeScript.

Each example polls killmails from the R2Z2 ephemeral endpoint, stores them in MySQL, and serves them via a web API with query filtering.

## R2Z2 API Overview

Base URL: `https://r2z2.zkillboard.com/ephemeral`

| Endpoint | Description |
|----------|-------------|
| `GET /sequence.json` | Current sequence ID |
| `GET /{sequence_id}.json` | Killmail data for a sequence |

- **Rate limit:** 20 req/s per IP (429 on exceed)
- **No auth required**
- **Sequence IDs** are strictly increasing and monotonic
- **Ephemeral storage:** files persist ~24 hours before expiration
- **Polling:** iterate sequences until 404, sleep 6s, repeat

## Examples

| Directory | Stack | Run |
|-----------|-------|-----|
| [`php/`](php/) | Slim, Guzzle, PDO | `php example.php` |
| [`python/`](python/) | FastAPI, httpx, SQLAlchemy | `uvicorn main:app` |
| [`typescript/`](typescript/) | Hono, mysql2, Bun | `bun run dev` |

All three share the same MySQL schema (`schema.sql`) and implement:

- **Poller** - continuous polling with state persistence and rate limiting
- **2-level filter pipeline** - L1 broad filters (NPC, solo, awox, security zone), L2 specific filters (value range, ship type, character/corp/alliance, solar system, region)
- **MySQL storage** - killmails, attackers, items (with recursive nesting for containers)
- **API endpoints** - list kills with filtering, get kill by ID (full detail), aggregate stats

## Schema

All examples use the same 3-table schema:

- `killmails` - main killmail data + zkb metadata
- `killmail_attackers` - one-to-many attacker rows
- `killmail_items` - items with `parent_id` for nested containers

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS zkillboard"
mysql -u root zkillboard < php/schema.sql
```

## Filters

Filters run before DB insert. Level 1 rejects first (any fail = skip), then Level 2.

**Level 1:** `NpcFilter`, `SoloFilter`, `AwoxFilter`, `SecurityFilter`
**Level 2:** `MinValueFilter`, `MaxValueFilter`, `ShipTypeFilter`, `CharacterFilter`, `CorporationFilter`, `AllianceFilter`, `SolarSystemFilter`, `RegionFilter`

## Links

- [R2Z2 API docs](https://github.com/zKillboard/zKillboard/wiki/API-(R2Z2))
- [zKillboard](https://zkillboard.com)
