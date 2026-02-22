import type { R2Z2Killmail } from "../types.js";
import type { KillmailFilter } from "./index.js";

export class MinValueFilter implements KillmailFilter {
  constructor(private minValue: number) {}

  filter(killmail: R2Z2Killmail): boolean {
    return (killmail.zkb.totalValue ?? 0) >= this.minValue;
  }
}

export class MaxValueFilter implements KillmailFilter {
  constructor(private maxValue: number) {}

  filter(killmail: R2Z2Killmail): boolean {
    return (killmail.zkb.totalValue ?? 0) <= this.maxValue;
  }
}

export class ShipTypeFilter implements KillmailFilter {
  private typeIds: Set<number>;

  constructor(typeIds: number[], private mode: "include" | "exclude" = "include") {
    this.typeIds = new Set(typeIds);
  }

  filter(killmail: R2Z2Killmail): boolean {
    const match = this.typeIds.has(killmail.esi.victim.ship_type_id);
    return this.mode === "include" ? match : !match;
  }
}

export class CharacterFilter implements KillmailFilter {
  private ids: Set<number>;

  constructor(characterIds: number[], private mode: "include" | "exclude" = "include") {
    this.ids = new Set(characterIds);
  }

  filter(killmail: R2Z2Killmail): boolean {
    const match = this.matchesAny(killmail);
    return this.mode === "include" ? match : !match;
  }

  private matchesAny(km: R2Z2Killmail): boolean {
    if (km.esi.victim.character_id !== undefined && this.ids.has(km.esi.victim.character_id)) return true;
    for (const a of km.esi.attackers ?? []) {
      if (a.character_id !== undefined && this.ids.has(a.character_id)) return true;
    }
    return false;
  }
}

export class CorporationFilter implements KillmailFilter {
  private ids: Set<number>;

  constructor(corporationIds: number[], private mode: "include" | "exclude" = "include") {
    this.ids = new Set(corporationIds);
  }

  filter(killmail: R2Z2Killmail): boolean {
    const match = this.matchesAny(killmail);
    return this.mode === "include" ? match : !match;
  }

  private matchesAny(km: R2Z2Killmail): boolean {
    if (km.esi.victim.corporation_id !== undefined && this.ids.has(km.esi.victim.corporation_id)) return true;
    for (const a of km.esi.attackers ?? []) {
      if (a.corporation_id !== undefined && this.ids.has(a.corporation_id)) return true;
    }
    return false;
  }
}

export class AllianceFilter implements KillmailFilter {
  private ids: Set<number>;

  constructor(allianceIds: number[], private mode: "include" | "exclude" = "include") {
    this.ids = new Set(allianceIds);
  }

  filter(killmail: R2Z2Killmail): boolean {
    const match = this.matchesAny(killmail);
    return this.mode === "include" ? match : !match;
  }

  private matchesAny(km: R2Z2Killmail): boolean {
    if (km.esi.victim.alliance_id !== undefined && this.ids.has(km.esi.victim.alliance_id)) return true;
    for (const a of km.esi.attackers ?? []) {
      if (a.alliance_id !== undefined && this.ids.has(a.alliance_id)) return true;
    }
    return false;
  }
}

export class SolarSystemFilter implements KillmailFilter {
  private ids: Set<number>;

  constructor(systemIds: number[], private mode: "include" | "exclude" = "include") {
    this.ids = new Set(systemIds);
  }

  filter(killmail: R2Z2Killmail): boolean {
    const match = this.ids.has(killmail.esi.solar_system_id);
    return this.mode === "include" ? match : !match;
  }
}

export class RegionFilter implements KillmailFilter {
  private regionLabels: Set<string>;

  constructor(regionIds: number[], private mode: "include" | "exclude" = "include") {
    this.regionLabels = new Set(regionIds.map((id) => `reg:${id}`));
  }

  filter(killmail: R2Z2Killmail): boolean {
    const labels = killmail.zkb.labels ?? [];
    const match = labels.some((l) => this.regionLabels.has(l));
    return this.mode === "include" ? match : !match;
  }
}
