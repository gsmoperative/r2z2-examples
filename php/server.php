<?php

require __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

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

$repo = new \R2Z2Examples\KillmailRepository($pdo);

$app = AppFactory::create();
$app->addErrorMiddleware(false, true, true);

// ── Routes ──────────────────────────────────────────────────────────

$app->get('/health', function (Request $request, Response $response) use ($pdo) {
    try {
        $pdo->query('SELECT 1');
        $response->getBody()->write(json_encode(['status' => 'ok']));
    } catch (Throwable) {
        $response->getBody()->write(json_encode(['status' => 'error', 'detail' => 'Database unavailable']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(503);
    }
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/kills', function (Request $request, Response $response) use ($repo) {
    $params = $request->getQueryParams();

    $limit = isset($params['limit']) ? (int) $params['limit'] : 50;
    $offset = isset($params['offset']) ? (int) $params['offset'] : 0;

    if ($limit < 1 || $limit > 1000) {
        $response->getBody()->write(json_encode(['error' => 'limit must be between 1 and 1000']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    if ($offset < 0) {
        $response->getBody()->write(json_encode(['error' => 'offset must be >= 0']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $intOrNull = function (string $key) use ($params): ?int {
        if (!isset($params[$key])) return null;
        $v = (int) $params[$key];
        return $v >= 1 ? $v : null;
    };

    $boolOrNull = function (string $key) use ($params): ?bool {
        if (!isset($params[$key])) return null;
        return $params[$key] === 'true' || $params[$key] === '1';
    };

    $result = $repo->listKills(
        limit: $limit,
        offset: $offset,
        minValue: isset($params['min_value']) ? (float) $params['min_value'] : null,
        maxValue: isset($params['max_value']) ? (float) $params['max_value'] : null,
        solarSystemId: $intOrNull('solar_system_id'),
        shipTypeId: $intOrNull('ship_type_id'),
        characterId: $intOrNull('character_id'),
        corporationId: $intOrNull('corporation_id'),
        allianceId: $intOrNull('alliance_id'),
        npc: $boolOrNull('npc'),
        solo: $boolOrNull('solo'),
        awox: $boolOrNull('awox'),
    );

    $response->getBody()->write(json_encode($result));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/kills/{id}', function (Request $request, Response $response, array $args) use ($repo) {
    $killmailId = (int) $args['id'];
    if ($killmailId < 1) {
        $response->getBody()->write(json_encode(['error' => 'Invalid killmail ID']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $kill = $repo->getKill($killmailId);
    if ($kill === null) {
        $response->getBody()->write(json_encode(['error' => 'Killmail not found']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    $response->getBody()->write(json_encode($kill));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/stats', function (Request $request, Response $response) use ($repo) {
    $stats = $repo->getStats();
    $response->getBody()->write(json_encode($stats));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
