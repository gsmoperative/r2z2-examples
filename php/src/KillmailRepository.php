<?php

namespace R2Z2Examples;

use PDO;

class KillmailRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Store a full killmail (killmail + attackers + items) in a single transaction.
     * Silently skips duplicates.
     */
    public function save(array $killmail): bool
    {
        $killmailId = $killmail['killmail_id'];
        $esi = $killmail['esi'];
        $zkb = $killmail['zkb'];
        $victim = $esi['victim'];

        $this->pdo->beginTransaction();

        try {
            // -- killmail row
            $stmt = $this->pdo->prepare('
                INSERT IGNORE INTO killmails (
                    killmail_id, hash, killmail_time, solar_system_id, sequence_id,
                    war_id, moon_id,
                    victim_character_id, victim_corporation_id, victim_alliance_id, victim_faction_id,
                    victim_ship_type_id, victim_damage_taken,
                    victim_pos_x, victim_pos_y, victim_pos_z,
                    zkb_location_id, zkb_fitted_value, zkb_dropped_value, zkb_destroyed_value,
                    zkb_total_value, zkb_points, zkb_is_npc, zkb_is_solo, zkb_is_awox,
                    zkb_labels, zkb_href, zkb_attacker_count, uploaded_at
                ) VALUES (
                    :killmail_id, :hash, :killmail_time, :solar_system_id, :sequence_id,
                    :war_id, :moon_id,
                    :victim_character_id, :victim_corporation_id, :victim_alliance_id, :victim_faction_id,
                    :victim_ship_type_id, :victim_damage_taken,
                    :victim_pos_x, :victim_pos_y, :victim_pos_z,
                    :zkb_location_id, :zkb_fitted_value, :zkb_dropped_value, :zkb_destroyed_value,
                    :zkb_total_value, :zkb_points, :zkb_is_npc, :zkb_is_solo, :zkb_is_awox,
                    :zkb_labels, :zkb_href, :zkb_attacker_count, :uploaded_at
                )
            ');

            $position = $victim['position'] ?? null;

            $stmt->execute([
                'killmail_id'          => $killmailId,
                'hash'                 => $killmail['hash'],
                'killmail_time'        => $esi['killmail_time'],
                'solar_system_id'      => $esi['solar_system_id'],
                'sequence_id'          => $killmail['sequence_id'],
                'war_id'               => $esi['war_id'] ?? null,
                'moon_id'              => $esi['moon_id'] ?? null,
                'victim_character_id'  => $victim['character_id'] ?? null,
                'victim_corporation_id'=> $victim['corporation_id'] ?? null,
                'victim_alliance_id'   => $victim['alliance_id'] ?? null,
                'victim_faction_id'    => $victim['faction_id'] ?? null,
                'victim_ship_type_id'  => $victim['ship_type_id'],
                'victim_damage_taken'  => $victim['damage_taken'],
                'victim_pos_x'         => $position['x'] ?? null,
                'victim_pos_y'         => $position['y'] ?? null,
                'victim_pos_z'         => $position['z'] ?? null,
                'zkb_location_id'      => $zkb['locationID'] ?? null,
                'zkb_fitted_value'     => $zkb['fittedValue'] ?? 0,
                'zkb_dropped_value'    => $zkb['droppedValue'] ?? 0,
                'zkb_destroyed_value'  => $zkb['destroyedValue'] ?? 0,
                'zkb_total_value'      => $zkb['totalValue'] ?? 0,
                'zkb_points'           => $zkb['points'] ?? 0,
                'zkb_is_npc'           => (int) ($zkb['npc'] ?? false),
                'zkb_is_solo'          => (int) ($zkb['solo'] ?? false),
                'zkb_is_awox'          => (int) ($zkb['awox'] ?? false),
                'zkb_labels'           => json_encode($zkb['labels'] ?? []),
                'zkb_href'             => $zkb['href'] ?? null,
                'zkb_attacker_count'   => count($esi['attackers'] ?? []),
                'uploaded_at'          => date('Y-m-d H:i:s', $killmail['uploaded_at']),
            ]);

            // Duplicate - skip attackers/items
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }

            // -- attackers
            $this->insertAttackers($killmailId, $esi['attackers'] ?? []);

            // -- items (recursive for nested containers)
            $this->insertItems($killmailId, $victim['items'] ?? []);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function insertAttackers(int $killmailId, array $attackers): void
    {
        if (empty($attackers)) {
            return;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO killmail_attackers (
                killmail_id, character_id, corporation_id, alliance_id, faction_id,
                ship_type_id, weapon_type_id, damage_done, final_blow, security_status
            ) VALUES (
                :killmail_id, :character_id, :corporation_id, :alliance_id, :faction_id,
                :ship_type_id, :weapon_type_id, :damage_done, :final_blow, :security_status
            )
        ');

        foreach ($attackers as $attacker) {
            $stmt->execute([
                'killmail_id'     => $killmailId,
                'character_id'    => $attacker['character_id'] ?? null,
                'corporation_id'  => $attacker['corporation_id'] ?? null,
                'alliance_id'     => $attacker['alliance_id'] ?? null,
                'faction_id'      => $attacker['faction_id'] ?? null,
                'ship_type_id'    => $attacker['ship_type_id'] ?? null,
                'weapon_type_id'  => $attacker['weapon_type_id'] ?? null,
                'damage_done'     => $attacker['damage_done'] ?? 0,
                'final_blow'      => (int) ($attacker['final_blow'] ?? false),
                'security_status' => $attacker['security_status'] ?? 0,
            ]);
        }
    }

    private function insertItems(int $killmailId, array $items, ?int $parentId = null): void
    {
        if (empty($items)) {
            return;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO killmail_items (
                killmail_id, parent_id, item_type_id, flag, quantity_destroyed, quantity_dropped, singleton
            ) VALUES (
                :killmail_id, :parent_id, :item_type_id, :flag, :quantity_destroyed, :quantity_dropped, :singleton
            )
        ');

        foreach ($items as $item) {
            $stmt->execute([
                'killmail_id'        => $killmailId,
                'parent_id'          => $parentId,
                'item_type_id'       => $item['item_type_id'],
                'flag'               => $item['flag'] ?? 0,
                'quantity_destroyed' => $item['quantity_destroyed'] ?? 0,
                'quantity_dropped'   => $item['quantity_dropped'] ?? 0,
                'singleton'          => $item['singleton'] ?? 0,
            ]);

            // Recurse into nested items (containers)
            if (!empty($item['items'])) {
                $insertedId = (int) $this->pdo->lastInsertId();
                $this->insertItems($killmailId, $item['items'], $insertedId);
            }
        }
    }

    // ── Read (API) ──────────────────────────────────────────────────

    public function listKills(
        int $limit = 50,
        int $offset = 0,
        ?float $minValue = null,
        ?float $maxValue = null,
        ?int $solarSystemId = null,
        ?int $shipTypeId = null,
        ?int $characterId = null,
        ?int $corporationId = null,
        ?int $allianceId = null,
        ?bool $npc = null,
        ?bool $solo = null,
        ?bool $awox = null,
    ): array {
        $clauses = [];
        $params = [];

        if ($minValue !== null) {
            $clauses[] = 'k.zkb_total_value >= ?';
            $params[] = $minValue;
        }
        if ($maxValue !== null) {
            $clauses[] = 'k.zkb_total_value <= ?';
            $params[] = $maxValue;
        }
        if ($solarSystemId !== null) {
            $clauses[] = 'k.solar_system_id = ?';
            $params[] = $solarSystemId;
        }
        if ($shipTypeId !== null) {
            $clauses[] = 'k.victim_ship_type_id = ?';
            $params[] = $shipTypeId;
        }
        if ($characterId !== null) {
            $clauses[] = '(k.victim_character_id = ? OR EXISTS (SELECT 1 FROM killmail_attackers a WHERE a.killmail_id = k.killmail_id AND a.character_id = ?))';
            $params[] = $characterId;
            $params[] = $characterId;
        }
        if ($corporationId !== null) {
            $clauses[] = '(k.victim_corporation_id = ? OR EXISTS (SELECT 1 FROM killmail_attackers a WHERE a.killmail_id = k.killmail_id AND a.corporation_id = ?))';
            $params[] = $corporationId;
            $params[] = $corporationId;
        }
        if ($allianceId !== null) {
            $clauses[] = '(k.victim_alliance_id = ? OR EXISTS (SELECT 1 FROM killmail_attackers a WHERE a.killmail_id = k.killmail_id AND a.alliance_id = ?))';
            $params[] = $allianceId;
            $params[] = $allianceId;
        }
        if ($npc !== null) {
            $clauses[] = 'k.zkb_is_npc = ?';
            $params[] = (int) $npc;
        }
        if ($solo !== null) {
            $clauses[] = 'k.zkb_is_solo = ?';
            $params[] = (int) $solo;
        }
        if ($awox !== null) {
            $clauses[] = 'k.zkb_is_awox = ?';
            $params[] = (int) $awox;
        }

        $where = $clauses ? implode(' AND ', $clauses) : '1=1';

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM killmails k WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $limit = min(max($limit, 1), 1000);
        $offset = max($offset, 0);

        $stmt = $this->pdo->prepare(
            "SELECT k.killmail_id, k.killmail_time, k.solar_system_id,
                k.victim_ship_type_id, k.victim_character_id, k.victim_corporation_id,
                k.victim_alliance_id, k.zkb_total_value, k.zkb_is_npc, k.zkb_is_solo,
                k.zkb_is_awox, k.zkb_attacker_count
            FROM killmails k WHERE {$where}
            ORDER BY k.killmail_time DESC LIMIT ? OFFSET ?"
        );

        $allParams = array_merge($params, [$limit, $offset]);
        foreach ($allParams as $i => $val) {
            $stmt->bindValue($i + 1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        $kills = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['zkb_is_npc'] = (bool) $row['zkb_is_npc'];
            $row['zkb_is_solo'] = (bool) $row['zkb_is_solo'];
            $row['zkb_is_awox'] = (bool) $row['zkb_is_awox'];
            $kills[] = $row;
        }

        return ['kills' => $kills, 'total' => $total];
    }

    public function getKill(int $killmailId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM killmails WHERE killmail_id = ?');
        $stmt->execute([$killmailId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $row['zkb_labels'] = json_decode($row['zkb_labels'] ?? '[]', true);
        $row['zkb_is_npc'] = (bool) $row['zkb_is_npc'];
        $row['zkb_is_solo'] = (bool) $row['zkb_is_solo'];
        $row['zkb_is_awox'] = (bool) $row['zkb_is_awox'];

        $stmt = $this->pdo->prepare('SELECT * FROM killmail_attackers WHERE killmail_id = ? ORDER BY damage_done DESC');
        $stmt->execute([$killmailId]);
        $attackers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($attackers as &$a) {
            $a['final_blow'] = (bool) $a['final_blow'];
        }
        $row['attackers'] = $attackers;

        $stmt = $this->pdo->prepare('SELECT * FROM killmail_items WHERE killmail_id = ?');
        $stmt->execute([$killmailId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $row['items'] = $this->buildItemTree($items);

        return $row;
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) AS total_kills, COALESCE(SUM(zkb_total_value), 0) AS total_value,
                SUM(zkb_is_npc) AS kills_npc, SUM(zkb_is_solo) AS kills_solo,
                SUM(zkb_is_awox) AS kills_awox FROM killmails'
        );
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_kills'] = (int) $stats['total_kills'];
        $stats['total_value'] = (float) $stats['total_value'];
        $stats['kills_npc'] = (int) ($stats['kills_npc'] ?? 0);
        $stats['kills_solo'] = (int) ($stats['kills_solo'] ?? 0);
        $stats['kills_awox'] = (int) ($stats['kills_awox'] ?? 0);

        $stmt = $this->pdo->query(
            'SELECT victim_ship_type_id AS ship_type_id, COUNT(*) AS count
            FROM killmails GROUP BY victim_ship_type_id ORDER BY count DESC LIMIT 10'
        );
        $stats['top_ships'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->query(
            'SELECT solar_system_id, COUNT(*) AS count
            FROM killmails GROUP BY solar_system_id ORDER BY count DESC LIMIT 10'
        );
        $stats['top_solar_systems'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    private function buildItemTree(array $flatItems): array
    {
        $byId = [];
        foreach ($flatItems as $item) {
            $item['singleton'] = (bool) $item['singleton'];
            $item['items'] = [];
            $byId[$item['id']] = $item;
        }

        $roots = [];
        foreach ($byId as &$item) {
            $parentId = $item['parent_id'];
            unset($item['parent_id']);
            if ($parentId === null || !isset($byId[$parentId])) {
                $roots[] = &$item;
            } else {
                $byId[$parentId]['items'][] = &$item;
            }
        }

        return $roots;
    }
}
