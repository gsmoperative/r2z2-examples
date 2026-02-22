import type { R2Z2Killmail } from "../types.js";
import type { KillmailFilter } from "./index.js";

export class NpcFilter implements KillmailFilter {
  constructor(private exclude: boolean = false) {}

  filter(killmail: R2Z2Killmail): boolean {
    const isNpc = killmail.zkb.npc ?? false;
    return this.exclude ? !isNpc : isNpc;
  }
}

export class SoloFilter implements KillmailFilter {
  constructor(private exclude: boolean = false) {}

  filter(killmail: R2Z2Killmail): boolean {
    const isSolo = killmail.zkb.solo ?? false;
    return this.exclude ? !isSolo : isSolo;
  }
}

export class AwoxFilter implements KillmailFilter {
  constructor(private exclude: boolean = false) {}

  filter(killmail: R2Z2Killmail): boolean {
    const isAwox = killmail.zkb.awox ?? false;
    return this.exclude ? !isAwox : isAwox;
  }
}

export class SecurityFilter implements KillmailFilter {
  private allow: string[];

  constructor(allow: string[]) {
    this.allow = allow.map((z) =>
      z.startsWith("loc:") ? z : `loc:${z}`
    );
  }

  filter(killmail: R2Z2Killmail): boolean {
    const labels = killmail.zkb.labels ?? [];
    return labels.some((label) => this.allow.includes(label));
  }
}
