# R2Z2 PHP Example

Example PHP client for the zKillboard R2Z2 ephemeral API. Polls killmails, stores them in MySQL, and supports a 2-level filter pipeline.

## Setup

```bash
composer install
mysql -u root -e "CREATE DATABASE IF NOT EXISTS zkillboard"
mysql -u root zkillboard < schema.sql
```

## Usage

```bash
php example.php
```

Edit `example.php` to configure DB credentials (or set `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` env vars) and filters.

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
