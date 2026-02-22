import type { Pool, PoolConnection, RowDataPacket, ResultSetHeader } from "mysql2/promise";
import type {
  R2Z2Killmail,
  KillmailSummary,
  KillmailDetail,
  AttackerRow,
  ItemNode,
  Stats,
  KillListQuery,
} from "./types.js";

export class KillmailRepository {
  constructor(private pool: Pool) {}

  // ── Write (poller) ──────────────────────────────────────────────

  async save(killmail: R2Z2Killmail): Promise<boolean> {
    const conn = await this.pool.getConnection();
    try {
      await conn.beginTransaction();

      const { esi, zkb } = killmail;
      const { victim } = esi;
      const pos = victim.position;

      const [result] = await conn.execute<ResultSetHeader>(
        `INSERT IGNORE INTO killmails (
          killmail_id, hash, killmail_time, solar_system_id, sequence_id,
          war_id, moon_id,
          victim_character_id, victim_corporation_id, victim_alliance_id,
          victim_faction_id, victim_ship_type_id, victim_damage_taken,
          victim_pos_x, victim_pos_y, victim_pos_z,
          zkb_location_id, zkb_fitted_value, zkb_dropped_value,
          zkb_destroyed_value, zkb_total_value, zkb_points,
          zkb_is_npc, zkb_is_solo, zkb_is_awox,
          zkb_labels, zkb_href, zkb_attacker_count, uploaded_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          killmail.killmail_id,
          killmail.hash,
          esi.killmail_time,
          esi.solar_system_id,
          killmail.sequence_id,
          esi.war_id ?? null,
          esi.moon_id ?? null,
          victim.character_id ?? null,
          victim.corporation_id ?? null,
          victim.alliance_id ?? null,
          victim.faction_id ?? null,
          victim.ship_type_id,
          victim.damage_taken,
          pos?.x ?? null,
          pos?.y ?? null,
          pos?.z ?? null,
          zkb.locationID ?? null,
          zkb.fittedValue ?? 0,
          zkb.droppedValue ?? 0,
          zkb.destroyedValue ?? 0,
          zkb.totalValue ?? 0,
          zkb.points ?? 0,
          zkb.npc ? 1 : 0,
          zkb.solo ? 1 : 0,
          zkb.awox ? 1 : 0,
          JSON.stringify(zkb.labels ?? []),
          zkb.href ?? null,
          (esi.attackers ?? []).length,
          new Date(killmail.uploaded_at * 1000).toISOString().slice(0, 19).replace("T", " "),
        ]
      );

      if (result.affectedRows === 0) {
        await conn.rollback();
        return false;
      }

      await this.insertAttackers(conn, killmail.killmail_id, esi.attackers ?? []);
      await this.insertItems(conn, killmail.killmail_id, victim.items ?? []);

      await conn.commit();
      return true;
    } catch (e) {
      await conn.rollback();
      throw e;
    } finally {
      conn.release();
    }
  }

  private async insertAttackers(
    conn: PoolConnection,
    killmailId: number,
    attackers: R2Z2Killmail["esi"]["attackers"] & {}
  ): Promise<void> {
    for (const a of attackers) {
      await conn.execute(
        `INSERT INTO killmail_attackers (
          killmail_id, character_id, corporation_id, alliance_id, faction_id,
          ship_type_id, weapon_type_id, damage_done, final_blow, security_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          killmailId,
          a.character_id ?? null,
          a.corporation_id ?? null,
          a.alliance_id ?? null,
          a.faction_id ?? null,
          a.ship_type_id ?? null,
          a.weapon_type_id ?? null,
          a.damage_done ?? 0,
          a.final_blow ? 1 : 0,
          a.security_status ?? 0,
        ]
      );
    }
  }

  private async insertItems(
    conn: PoolConnection,
    killmailId: number,
    items: R2Z2Killmail["esi"]["victim"]["items"] & {},
    parentId: number | null = null
  ): Promise<void> {
    for (const item of items) {
      const [result] = await conn.execute<ResultSetHeader>(
        `INSERT INTO killmail_items (
          killmail_id, parent_id, item_type_id, flag,
          quantity_destroyed, quantity_dropped, singleton
        ) VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [
          killmailId,
          parentId,
          item.item_type_id,
          item.flag ?? 0,
          item.quantity_destroyed ?? 0,
          item.quantity_dropped ?? 0,
          item.singleton ?? 0,
        ]
      );

      if (item.items?.length) {
        await this.insertItems(conn, killmailId, item.items, result.insertId);
      }
    }
  }

  // ── Read (API) ──────────────────────────────────────────────────

  async listKills(query: KillListQuery): Promise<{ kills: KillmailSummary[]; total: number }> {
    const clauses: string[] = [];
    const params: unknown[] = [];

    if (query.min_value != null) {
      clauses.push("k.zkb_total_value >= ?");
      params.push(query.min_value);
    }
    if (query.max_value != null) {
      clauses.push("k.zkb_total_value <= ?");
      params.push(query.max_value);
    }
    if (query.solar_system_id != null) {
      clauses.push("k.solar_system_id = ?");
      params.push(query.solar_system_id);
    }
    if (query.ship_type_id != null) {
      clauses.push("k.victim_ship_type_id = ?");
      params.push(query.ship_type_id);
    }
    if (query.character_id != null) {
      clauses.push(
        "(k.victim_character_id = ? OR EXISTS (SELECT 1 FROM killmail_attackers a WHERE a.killmail_id = k.killmail_id AND a.character_id = ?))"
      );
      params.push(query.character_id, query.character_id);
    }
    if (query.corporation_id != null) {
      clauses.push(
        "(k.victim_corporation_id = ? OR EXISTS (SELECT 1 FROM killmail_attackers a WHERE a.killmail_id = k.killmail_id AND a.corporation_id = ?))"
      );
      params.push(query.corporation_id, query.corporation_id);
    }
    if (query.alliance_id != null) {
      clauses.push(
        "(k.victim_alliance_id = ? OR EXISTS (SELECT 1 FROM killmail_attackers a WHERE a.killmail_id = k.killmail_id AND a.alliance_id = ?))"
      );
      params.push(query.alliance_id, query.alliance_id);
    }
    if (query.npc != null) {
      clauses.push("k.zkb_is_npc = ?");
      params.push(query.npc ? 1 : 0);
    }
    if (query.solo != null) {
      clauses.push("k.zkb_is_solo = ?");
      params.push(query.solo ? 1 : 0);
    }
    if (query.awox != null) {
      clauses.push("k.zkb_is_awox = ?");
      params.push(query.awox ? 1 : 0);
    }

    const where = clauses.length ? clauses.join(" AND ") : "1=1";
    const limit = Math.min(query.limit ?? 50, 1000);
    const offset = query.offset ?? 0;

    const [countRows] = await this.pool.execute<RowDataPacket[]>(
      `SELECT COUNT(*) AS total FROM killmails k WHERE ${where}`,
      params
    );
    const total = countRows[0].total as number;

    const [rows] = await this.pool.execute<RowDataPacket[]>(
      `SELECT k.killmail_id, k.killmail_time, k.solar_system_id,
        k.victim_ship_type_id, k.victim_character_id, k.victim_corporation_id,
        k.victim_alliance_id, k.zkb_total_value, k.zkb_is_npc, k.zkb_is_solo,
        k.zkb_is_awox, k.zkb_attacker_count
      FROM killmails k WHERE ${where}
      ORDER BY k.killmail_time DESC LIMIT ? OFFSET ?`,
      [...params, limit, offset]
    );

    const kills: KillmailSummary[] = rows.map((r) => ({
      ...r,
      zkb_is_npc: !!r.zkb_is_npc,
      zkb_is_solo: !!r.zkb_is_solo,
      zkb_is_awox: !!r.zkb_is_awox,
    })) as KillmailSummary[];

    return { kills, total };
  }

  async getKill(killmailId: number): Promise<KillmailDetail | null> {
    const [rows] = await this.pool.execute<RowDataPacket[]>(
      "SELECT * FROM killmails WHERE killmail_id = ?",
      [killmailId]
    );

    if (rows.length === 0) return null;

    const row = rows[0];
    row.zkb_labels = JSON.parse(row.zkb_labels ?? "[]");
    row.zkb_is_npc = !!row.zkb_is_npc;
    row.zkb_is_solo = !!row.zkb_is_solo;
    row.zkb_is_awox = !!row.zkb_is_awox;

    const [attackerRows] = await this.pool.execute<RowDataPacket[]>(
      "SELECT * FROM killmail_attackers WHERE killmail_id = ? ORDER BY damage_done DESC",
      [killmailId]
    );
    const attackers: AttackerRow[] = attackerRows.map((a) => ({
      ...a,
      final_blow: !!a.final_blow,
    })) as AttackerRow[];

    const [itemRows] = await this.pool.execute<RowDataPacket[]>(
      "SELECT * FROM killmail_items WHERE killmail_id = ?",
      [killmailId]
    );
    const items = this.buildItemTree(
      itemRows.map((i) => ({ ...i, singleton: !!i.singleton })) as (RowDataPacket & { parent_id: number | null })[]
    );

    return { ...row, attackers, items } as KillmailDetail;
  }

  async getStats(): Promise<Stats> {
    const [summaryRows] = await this.pool.execute<RowDataPacket[]>(
      `SELECT COUNT(*) AS total_kills, COALESCE(SUM(zkb_total_value), 0) AS total_value,
        SUM(zkb_is_npc) AS kills_npc, SUM(zkb_is_solo) AS kills_solo,
        SUM(zkb_is_awox) AS kills_awox FROM killmails`
    );
    const s = summaryRows[0];

    const [shipRows] = await this.pool.execute<RowDataPacket[]>(
      `SELECT victim_ship_type_id AS ship_type_id, COUNT(*) AS count
       FROM killmails GROUP BY victim_ship_type_id ORDER BY count DESC LIMIT 10`
    );

    const [systemRows] = await this.pool.execute<RowDataPacket[]>(
      `SELECT solar_system_id, COUNT(*) AS count
       FROM killmails GROUP BY solar_system_id ORDER BY count DESC LIMIT 10`
    );

    return {
      total_kills: s.total_kills,
      total_value: Number(s.total_value),
      kills_npc: Number(s.kills_npc ?? 0),
      kills_solo: Number(s.kills_solo ?? 0),
      kills_awox: Number(s.kills_awox ?? 0),
      top_ships: shipRows as Stats["top_ships"],
      top_solar_systems: systemRows as Stats["top_solar_systems"],
    };
  }

  private buildItemTree(flatItems: (RowDataPacket & { parent_id: number | null })[]): ItemNode[] {
    const byId = new Map<number, ItemNode>();

    for (const item of flatItems) {
      byId.set(item.id as number, {
        id: item.id as number,
        item_type_id: item.item_type_id as number,
        flag: item.flag as number,
        quantity_destroyed: item.quantity_destroyed as number,
        quantity_dropped: item.quantity_dropped as number,
        singleton: !!item.singleton,
        items: [],
      });
    }

    const roots: ItemNode[] = [];
    for (const item of flatItems) {
      const node = byId.get(item.id as number)!;
      if (item.parent_id == null || !byId.has(item.parent_id)) {
        roots.push(node);
      } else {
        byId.get(item.parent_id)!.items.push(node);
      }
    }

    return roots;
  }
}
