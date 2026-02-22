// ── R2Z2 API response (raw JSON from zKillboard) ──

export interface R2Z2Killmail {
  killmail_id: number;
  hash: string;
  sequence_id: number;
  uploaded_at: number;
  esi: {
    killmail_time: string;
    solar_system_id: number;
    war_id?: number;
    moon_id?: number;
    victim: {
      character_id?: number;
      corporation_id?: number;
      alliance_id?: number;
      faction_id?: number;
      ship_type_id: number;
      damage_taken: number;
      position?: { x: number; y: number; z: number };
      items?: R2Z2Item[];
    };
    attackers?: R2Z2Attacker[];
  };
  zkb: {
    locationID?: number;
    fittedValue?: number;
    droppedValue?: number;
    destroyedValue?: number;
    totalValue?: number;
    points?: number;
    npc?: boolean;
    solo?: boolean;
    awox?: boolean;
    labels?: string[];
    href?: string;
  };
}

export interface R2Z2Item {
  item_type_id: number;
  flag?: number;
  quantity_destroyed?: number;
  quantity_dropped?: number;
  singleton?: number;
  items?: R2Z2Item[];
}

export interface R2Z2Attacker {
  character_id?: number;
  corporation_id?: number;
  alliance_id?: number;
  faction_id?: number;
  ship_type_id?: number;
  weapon_type_id?: number;
  damage_done?: number;
  final_blow?: boolean;
  security_status?: number;
}

// ── API response types ──

export interface KillmailSummary {
  killmail_id: number;
  killmail_time: string;
  solar_system_id: number;
  victim_ship_type_id: number;
  victim_character_id: number | null;
  victim_corporation_id: number | null;
  victim_alliance_id: number | null;
  zkb_total_value: number;
  zkb_is_npc: boolean;
  zkb_is_solo: boolean;
  zkb_is_awox: boolean;
  zkb_attacker_count: number;
}

export interface KillmailDetail extends KillmailSummary {
  hash: string;
  sequence_id: number;
  war_id: number | null;
  moon_id: number | null;
  victim_faction_id: number | null;
  victim_damage_taken: number;
  victim_pos_x: number | null;
  victim_pos_y: number | null;
  victim_pos_z: number | null;
  zkb_location_id: number | null;
  zkb_fitted_value: number;
  zkb_dropped_value: number;
  zkb_destroyed_value: number;
  zkb_points: number;
  zkb_labels: string[];
  zkb_href: string | null;
  uploaded_at: string;
  created_at: string;
  attackers: AttackerRow[];
  items: ItemNode[];
}

export interface AttackerRow {
  id: number;
  character_id: number | null;
  corporation_id: number | null;
  alliance_id: number | null;
  faction_id: number | null;
  ship_type_id: number | null;
  weapon_type_id: number | null;
  damage_done: number;
  final_blow: boolean;
  security_status: number;
}

export interface ItemNode {
  id: number;
  item_type_id: number;
  flag: number;
  quantity_destroyed: number;
  quantity_dropped: number;
  singleton: boolean;
  items: ItemNode[];
}

export interface Stats {
  total_kills: number;
  total_value: number;
  kills_npc: number;
  kills_solo: number;
  kills_awox: number;
  top_ships: { ship_type_id: number; count: number }[];
  top_solar_systems: { solar_system_id: number; count: number }[];
}

export interface KillListQuery {
  limit?: number;
  offset?: number;
  min_value?: number;
  max_value?: number;
  solar_system_id?: number;
  ship_type_id?: number;
  character_id?: number;
  corporation_id?: number;
  alliance_id?: number;
  npc?: boolean;
  solo?: boolean;
  awox?: boolean;
}
