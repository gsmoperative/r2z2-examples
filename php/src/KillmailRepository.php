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
}
