<?php

require __DIR__ . '/vendor/autoload.php';

use R2Z2Examples\Filter\FilterPipeline;
use R2Z2Examples\Filter\Level1\NpcFilter;
use R2Z2Examples\Filter\Level1\SecurityFilter;
use R2Z2Examples\Filter\Level2\MinValueFilter;
use R2Z2Examples\KillmailRepository;
use R2Z2Examples\ZKillboardR2Z2;

// -- Config
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'zkillboard';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

$repo = new KillmailRepository($pdo);

// -- Filters: skip NPC kills, only nullsec/lowsec, 10M+ ISK
$filters = new FilterPipeline();
$filters->addLevel1(new NpcFilter(exclude: true));
$filters->addLevel1(new SecurityFilter(allow: ['nullsec', 'lowsec']));
$filters->addLevel2(new MinValueFilter(10_000_000));

$zkill = new ZKillboardR2Z2(
    stateFile: __DIR__ . '/zkill_sequence.txt',
    filters: $filters,
);

echo "Starting zKillboard poller (filtered: no NPC, nullsec/lowsec only, 10M+ ISK)...\n";

$zkill->poll(function (array $killmail, int $sequenceId) use ($repo) {
    try {
        $killId = $killmail['killmail_id'];
        $value  = number_format($killmail['zkb']['totalValue'] ?? 0);

        $saved = $repo->save($killmail);
        $status = $saved ? 'saved' : 'skipped (duplicate)';

        echo "[#{$sequenceId}] Kill {$killId} | {$value} ISK | {$status}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Error saving killmail at sequence {$sequenceId}: {$e->getMessage()}\n");
    }
});
