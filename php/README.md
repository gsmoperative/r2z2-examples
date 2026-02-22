# R2Z2 PHP Example

PHP client for the zKillboard R2Z2 ephemeral API. Slim 4 API server + standalone poller, MySQL storage, and 2-level filter pipeline.

## Setup

```bash
composer install
mysql -u root -e "CREATE DATABASE IF NOT EXISTS zkillboard"
mysql -u root zkillboard < schema.sql
```

## Run

```bash
# API server
php -S localhost:8080 server.php

# Poller (separate process)
php example.php
```

The API server runs on PHP's built-in server. The poller runs as a separate process, continuously fetching killmails from R2Z2. Both connect to the same database.

## API

- `GET /health` - database health check
- `GET /kills` - list kills (`limit`, `offset`, `min_value`, `max_value`, `solar_system_id`, `ship_type_id`, `character_id`, `corporation_id`, `alliance_id`, `npc`, `solo`, `awox`)
- `GET /kills/{id}` - full killmail with attackers and nested items
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

Poller filters are configured directly in `example.php`.

## Filters

Two levels - L1 runs first (broad category), L2 runs after (specific values). Any rejection at either level skips the killmail.

**Level 1:** `NpcFilter`, `SoloFilter`, `AwoxFilter`, `SecurityFilter`
**Level 2:** `MinValueFilter`, `MaxValueFilter`, `ShipTypeFilter`, `CharacterFilter`, `CorporationFilter`, `AllianceFilter`, `SolarSystemFilter`, `RegionFilter`

```php
$filters = new FilterPipeline();
$filters->addLevel1(new NpcFilter(exclude: true));
$filters->addLevel1(new SecurityFilter(allow: ['nullsec', 'lowsec']));
$filters->addLevel2(new MinValueFilter(10_000_000));

$zkill = new ZKillboardR2Z2(stateFile: 'zkill_sequence.txt', filters: $filters);
```

## Schema

Three tables: `killmails`, `killmail_attackers`, `killmail_items` (with `parent_id` for nested container items). See `schema.sql`.
