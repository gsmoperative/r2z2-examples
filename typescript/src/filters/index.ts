import type { R2Z2Killmail } from "../types.js";

export interface KillmailFilter {
  filter(killmail: R2Z2Killmail): boolean;
}

export class FilterPipeline {
  private level1: KillmailFilter[] = [];
  private level2: KillmailFilter[] = [];

  addLevel1(f: KillmailFilter): this {
    this.level1.push(f);
    return this;
  }

  addLevel2(f: KillmailFilter): this {
    this.level2.push(f);
    return this;
  }

  evaluate(killmail: R2Z2Killmail): boolean {
    for (const f of this.level1) {
      if (!f.filter(killmail)) return false;
    }
    for (const f of this.level2) {
      if (!f.filter(killmail)) return false;
    }
    return true;
  }
}
