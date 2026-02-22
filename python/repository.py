import json
from datetime import datetime, timezone

from sqlalchemy import text
from sqlalchemy.orm import Session


class KillmailRepository:
    def __init__(self, session: Session):
        self.session = session

    # ── Write (poller) ──────────────────────────────────────────────

    def save(self, killmail: dict) -> bool:
        killmail_id = killmail["killmail_id"]
        esi = killmail["esi"]
        zkb = killmail["zkb"]
        victim = esi["victim"]
        position = victim.get("position") or {}

        result = self.session.execute(
            text("""
                INSERT IGNORE INTO killmails (
                    killmail_id, hash, killmail_time, solar_system_id, sequence_id,
                    war_id, moon_id,
                    victim_character_id, victim_corporation_id, victim_alliance_id,
                    victim_faction_id, victim_ship_type_id, victim_damage_taken,
                    victim_pos_x, victim_pos_y, victim_pos_z,
                    zkb_location_id, zkb_fitted_value, zkb_dropped_value,
                    zkb_destroyed_value, zkb_total_value, zkb_points,
                    zkb_is_npc, zkb_is_solo, zkb_is_awox,
                    zkb_labels, zkb_href, zkb_attacker_count, uploaded_at
                ) VALUES (
                    :killmail_id, :hash, :killmail_time, :solar_system_id, :sequence_id,
                    :war_id, :moon_id,
                    :victim_character_id, :victim_corporation_id, :victim_alliance_id,
                    :victim_faction_id, :victim_ship_type_id, :victim_damage_taken,
                    :victim_pos_x, :victim_pos_y, :victim_pos_z,
                    :zkb_location_id, :zkb_fitted_value, :zkb_dropped_value,
                    :zkb_destroyed_value, :zkb_total_value, :zkb_points,
                    :zkb_is_npc, :zkb_is_solo, :zkb_is_awox,
                    :zkb_labels, :zkb_href, :zkb_attacker_count, :uploaded_at
                )
            """),
            {
                "killmail_id": killmail_id,
                "hash": killmail["hash"],
                "killmail_time": esi["killmail_time"],
                "solar_system_id": esi["solar_system_id"],
                "sequence_id": killmail["sequence_id"],
                "war_id": esi.get("war_id"),
                "moon_id": esi.get("moon_id"),
                "victim_character_id": victim.get("character_id"),
                "victim_corporation_id": victim.get("corporation_id"),
                "victim_alliance_id": victim.get("alliance_id"),
                "victim_faction_id": victim.get("faction_id"),
                "victim_ship_type_id": victim["ship_type_id"],
                "victim_damage_taken": victim["damage_taken"],
                "victim_pos_x": position.get("x"),
                "victim_pos_y": position.get("y"),
                "victim_pos_z": position.get("z"),
                "zkb_location_id": zkb.get("locationID"),
                "zkb_fitted_value": zkb.get("fittedValue", 0),
                "zkb_dropped_value": zkb.get("droppedValue", 0),
                "zkb_destroyed_value": zkb.get("destroyedValue", 0),
                "zkb_total_value": zkb.get("totalValue", 0),
                "zkb_points": zkb.get("points", 0),
                "zkb_is_npc": int(zkb.get("npc", False)),
                "zkb_is_solo": int(zkb.get("solo", False)),
                "zkb_is_awox": int(zkb.get("awox", False)),
                "zkb_labels": json.dumps(zkb.get("labels", [])),
                "zkb_href": zkb.get("href"),
                "zkb_attacker_count": len(esi.get("attackers", [])),
                "uploaded_at": datetime.fromtimestamp(
                    killmail["uploaded_at"], tz=timezone.utc
                ).strftime("%Y-%m-%d %H:%M:%S"),
            },
        )

        if result.rowcount == 0:
            self.session.rollback()
            return False

        self._insert_attackers(killmail_id, esi.get("attackers", []))
        self._insert_items(killmail_id, victim.get("items", []))
        self.session.commit()
        return True

    def _insert_attackers(self, killmail_id: int, attackers: list[dict]) -> None:
        if not attackers:
            return
        for attacker in attackers:
            self.session.execute(
                text("""
                    INSERT INTO killmail_attackers (
                        killmail_id, character_id, corporation_id, alliance_id, faction_id,
                        ship_type_id, weapon_type_id, damage_done, final_blow, security_status
                    ) VALUES (
                        :killmail_id, :character_id, :corporation_id, :alliance_id, :faction_id,
                        :ship_type_id, :weapon_type_id, :damage_done, :final_blow, :security_status
                    )
                """),
                {
                    "killmail_id": killmail_id,
                    "character_id": attacker.get("character_id"),
                    "corporation_id": attacker.get("corporation_id"),
                    "alliance_id": attacker.get("alliance_id"),
                    "faction_id": attacker.get("faction_id"),
                    "ship_type_id": attacker.get("ship_type_id"),
                    "weapon_type_id": attacker.get("weapon_type_id"),
                    "damage_done": attacker.get("damage_done", 0),
                    "final_blow": int(attacker.get("final_blow", False)),
                    "security_status": attacker.get("security_status", 0),
                },
            )

    def _insert_items(
        self, killmail_id: int, items: list[dict], parent_id: int | None = None
    ) -> None:
        if not items:
            return
        for item in items:
            result = self.session.execute(
                text("""
                    INSERT INTO killmail_items (
                        killmail_id, parent_id, item_type_id, flag,
                        quantity_destroyed, quantity_dropped, singleton
                    ) VALUES (
                        :killmail_id, :parent_id, :item_type_id, :flag,
                        :quantity_destroyed, :quantity_dropped, :singleton
                    )
                """),
                {
                    "killmail_id": killmail_id,
                    "parent_id": parent_id,
                    "item_type_id": item["item_type_id"],
                    "flag": item.get("flag", 0),
                    "quantity_destroyed": item.get("quantity_destroyed", 0),
                    "quantity_dropped": item.get("quantity_dropped", 0),
                    "singleton": item.get("singleton", 0),
                },
            )
            if item.get("items"):
                inserted_id = result.lastrowid
                self._insert_items(killmail_id, item["items"], inserted_id)

    # ── Read (API) ──────────────────────────────────────────────────

    def list_kills(
        self,
        *,
        limit: int = 50,
        offset: int = 0,
        min_value: float | None = None,
        max_value: float | None = None,
        solar_system_id: int | None = None,
        ship_type_id: int | None = None,
        character_id: int | None = None,
        corporation_id: int | None = None,
        alliance_id: int | None = None,
        npc: bool | None = None,
        solo: bool | None = None,
        awox: bool | None = None,
    ) -> tuple[list[dict], int]:
        clauses = []
        params: dict = {}

        if min_value is not None:
            clauses.append("k.zkb_total_value >= :min_value")
            params["min_value"] = min_value
        if max_value is not None:
            clauses.append("k.zkb_total_value <= :max_value")
            params["max_value"] = max_value
        if solar_system_id is not None:
            clauses.append("k.solar_system_id = :solar_system_id")
            params["solar_system_id"] = solar_system_id
        if ship_type_id is not None:
            clauses.append("k.victim_ship_type_id = :ship_type_id")
            params["ship_type_id"] = ship_type_id
        if character_id is not None:
            clauses.append(
                "(k.victim_character_id = :character_id"
                " OR EXISTS (SELECT 1 FROM killmail_attackers a"
                " WHERE a.killmail_id = k.killmail_id"
                " AND a.character_id = :character_id))"
            )
            params["character_id"] = character_id
        if corporation_id is not None:
            clauses.append(
                "(k.victim_corporation_id = :corporation_id"
                " OR EXISTS (SELECT 1 FROM killmail_attackers a"
                " WHERE a.killmail_id = k.killmail_id"
                " AND a.corporation_id = :corporation_id))"
            )
            params["corporation_id"] = corporation_id
        if alliance_id is not None:
            clauses.append(
                "(k.victim_alliance_id = :alliance_id"
                " OR EXISTS (SELECT 1 FROM killmail_attackers a"
                " WHERE a.killmail_id = k.killmail_id"
                " AND a.alliance_id = :alliance_id))"
            )
            params["alliance_id"] = alliance_id
        if npc is not None:
            clauses.append("k.zkb_is_npc = :npc")
            params["npc"] = int(npc)
        if solo is not None:
            clauses.append("k.zkb_is_solo = :solo")
            params["solo"] = int(solo)
        if awox is not None:
            clauses.append("k.zkb_is_awox = :awox")
            params["awox"] = int(awox)

        where = " AND ".join(clauses) if clauses else "1=1"

        total = self.session.execute(
            text(f"SELECT COUNT(*) FROM killmails k WHERE {where}"), params
        ).scalar()

        rows = self.session.execute(
            text(
                f"SELECT k.* FROM killmails k WHERE {where}"
                " ORDER BY k.killmail_time DESC LIMIT :limit OFFSET :offset"
            ),
            {**params, "limit": limit, "offset": offset},
        )

        kills = []
        for r in rows:
            row = dict(r._mapping)
            row["zkb_labels"] = json.loads(row["zkb_labels"]) if row.get("zkb_labels") else []
            kills.append(row)

        return kills, total

    def get_kill(self, killmail_id: int) -> dict | None:
        row = self.session.execute(
            text("SELECT * FROM killmails WHERE killmail_id = :id"),
            {"id": killmail_id},
        ).first()

        if row is None:
            return None

        kill = dict(row._mapping)
        kill["zkb_labels"] = json.loads(kill["zkb_labels"]) if kill.get("zkb_labels") else []

        attackers = self.session.execute(
            text("SELECT * FROM killmail_attackers WHERE killmail_id = :id ORDER BY damage_done DESC"),
            {"id": killmail_id},
        )
        kill["attackers"] = [dict(r._mapping) for r in attackers]

        items = self.session.execute(
            text("SELECT * FROM killmail_items WHERE killmail_id = :id"),
            {"id": killmail_id},
        )
        kill["items"] = self._build_item_tree([dict(r._mapping) for r in items])

        return kill

    def get_stats(self) -> dict:
        row = self.session.execute(
            text(
                "SELECT COUNT(*) AS total_kills, COALESCE(SUM(zkb_total_value), 0) AS total_value,"
                " SUM(zkb_is_npc) AS kills_npc, SUM(zkb_is_solo) AS kills_solo,"
                " SUM(zkb_is_awox) AS kills_awox FROM killmails"
            )
        ).first()

        stats = dict(row._mapping)

        ships = self.session.execute(
            text(
                "SELECT victim_ship_type_id AS ship_type_id, COUNT(*) AS count"
                " FROM killmails GROUP BY victim_ship_type_id ORDER BY count DESC LIMIT 10"
            )
        )
        stats["top_ships"] = [dict(r._mapping) for r in ships]

        systems = self.session.execute(
            text(
                "SELECT solar_system_id, COUNT(*) AS count"
                " FROM killmails GROUP BY solar_system_id ORDER BY count DESC LIMIT 10"
            )
        )
        stats["top_solar_systems"] = [dict(r._mapping) for r in systems]

        return stats

    @staticmethod
    def _build_item_tree(flat_items: list[dict]) -> list[dict]:
        by_id = {item["id"]: {**item, "items": []} for item in flat_items}
        roots = []
        for item_id, item in by_id.items():
            parent = item.pop("parent_id", None)
            if parent is None or parent not in by_id:
                roots.append(item)
            else:
                by_id[parent]["items"].append(item)
        return roots
