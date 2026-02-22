from __future__ import annotations

from datetime import datetime
from decimal import Decimal

from pydantic import BaseModel


class AttackerResponse(BaseModel):
    id: int
    character_id: int | None
    corporation_id: int | None
    alliance_id: int | None
    faction_id: int | None
    ship_type_id: int | None
    weapon_type_id: int | None
    damage_done: int
    final_blow: bool
    security_status: float


class ItemResponse(BaseModel):
    id: int
    item_type_id: int
    flag: int
    quantity_destroyed: int
    quantity_dropped: int
    singleton: bool
    items: list[ItemResponse] = []


class KillmailSummary(BaseModel):
    killmail_id: int
    killmail_time: datetime
    solar_system_id: int
    victim_ship_type_id: int
    victim_character_id: int | None
    victim_corporation_id: int | None
    victim_alliance_id: int | None
    zkb_total_value: Decimal
    zkb_is_npc: bool
    zkb_is_solo: bool
    zkb_is_awox: bool
    zkb_attacker_count: int


class KillmailDetail(KillmailSummary):
    hash: str
    sequence_id: int
    war_id: int | None
    moon_id: int | None
    victim_faction_id: int | None
    victim_damage_taken: int
    victim_pos_x: float | None
    victim_pos_y: float | None
    victim_pos_z: float | None
    zkb_location_id: int | None
    zkb_fitted_value: Decimal
    zkb_dropped_value: Decimal
    zkb_destroyed_value: Decimal
    zkb_points: int
    zkb_labels: list[str]
    zkb_href: str | None
    uploaded_at: datetime
    created_at: datetime
    attackers: list[AttackerResponse]
    items: list[ItemResponse]


class KillmailListResponse(BaseModel):
    total: int
    kills: list[KillmailSummary]


class StatsResponse(BaseModel):
    total_kills: int
    total_value: Decimal
    kills_npc: int
    kills_solo: int
    kills_awox: int
    top_ships: list[dict]
    top_solar_systems: list[dict]


ItemResponse.model_rebuild()
