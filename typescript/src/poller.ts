import { existsSync, readFileSync, writeFileSync, renameSync } from "fs";
import { dirname, join } from "path";
import { FilterPipeline } from "./filters/index.js";
import type { R2Z2Killmail } from "./types.js";

const BASE_URL = "https://r2z2.zkillboard.com/ephemeral";
const SLEEP_ON_SUCCESS = 100;
const SLEEP_ON_404 = 6000;
const SLEEP_ON_429 = 2000;
const MAX_RETRIES = 5;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

export class ZKillboardR2Z2 {
  private lastSequenceId = 0;
  private stopped = false;

  constructor(
    private stateFile: string | null = null,
    private filters: FilterPipeline | null = null
  ) {
    if (this.stateFile && existsSync(this.stateFile)) {
      this.lastSequenceId = parseInt(readFileSync(this.stateFile, "utf-8").trim(), 10);
    }
  }

  async getCurrentSequence(): Promise<number> {
    const data = await this.request("/sequence.json");
    return data!.sequence_id;
  }

  async getKillmail(sequenceId: number): Promise<R2Z2Killmail | null> {
    return this.request(`/${sequenceId}.json`, true) as Promise<R2Z2Killmail | null>;
  }

  stop(): void {
    this.stopped = true;
  }

  async poll(callback: (killmail: R2Z2Killmail, sequenceId: number) => Promise<void>): Promise<void> {
    let sequenceId = this.lastSequenceId || (await this.getCurrentSequence());
    console.log(`Poller starting at sequence ${sequenceId}`);

    while (!this.stopped) {
      const killmail = await this.getKillmail(sequenceId);

      if (killmail === null) {
        await sleep(SLEEP_ON_404);
        continue;
      }

      if (this.filters === null || this.filters.evaluate(killmail)) {
        await callback(killmail, sequenceId);
      }

      this.lastSequenceId = sequenceId;
      this.saveState();
      sequenceId++;
      await sleep(SLEEP_ON_SUCCESS);
    }

    console.log(`Poller stopped at sequence ${this.lastSequenceId}`);
  }

  private async request(path: string, allowNotFound = false): Promise<Record<string, any> | null> {
    for (let attempt = 0; attempt <= MAX_RETRIES; attempt++) {
      const res = await fetch(`${BASE_URL}${path}`, {
        headers: {
          Accept: "application/json",
          "User-Agent": "R2Z2-Examples-TypeScript/1.0",
        },
      });

      if (res.status === 404 && allowNotFound) return null;

      if (res.status === 429) {
        if (attempt < MAX_RETRIES) {
          console.warn(`Rate limited (429), retry ${attempt + 1}/${MAX_RETRIES}`);
          await sleep(SLEEP_ON_429);
          continue;
        }
        throw new Error(`Rate limited (429) after ${MAX_RETRIES} retries`);
      }

      if (!res.ok) {
        throw new Error(`HTTP ${res.status} from zKillboard: ${await res.text()}`);
      }

      return res.json();
    }

    throw new Error("Unreachable");
  }

  private saveState(): void {
    if (this.stateFile) {
      const dir = dirname(this.stateFile);
      const tmpFile = join(dir, `.${Date.now()}.tmp`);
      writeFileSync(tmpFile, String(this.lastSequenceId));
      renameSync(tmpFile, this.stateFile);
    }
  }
}
