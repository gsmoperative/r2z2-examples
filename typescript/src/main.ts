import { Hono } from "hono";
import { serve } from "hono/bun";
import { config } from "./config.js";
import { pool } from "./database.js";
import { FilterPipeline } from "./filters/index.js";
import { NpcFilter, SecurityFilter } from "./filters/level1.js";
import { MinValueFilter } from "./filters/level2.js";
import { KillmailRepository } from "./repository.js";
import { ZKillboardR2Z2 } from "./poller.js";
import type { KillListQuery, R2Z2Killmail } from "./types.js";

const app = new Hono();
const repo = new KillmailRepository(pool);

function parseIntParam(value: string | undefined): number | undefined {
  if (value === undefined) return undefined;
  const n = parseInt(value, 10);
  if (isNaN(n)) return undefined;
  return n;
}

function parsePositiveInt(value: string | undefined): number | undefined {
  const n = parseIntParam(value);
  if (n !== undefined && n < 1) return undefined;
  return n;
}

function parseFloatParam(value: string | undefined): number | undefined {
  if (value === undefined) return undefined;
  const n = parseFloat(value);
  if (isNaN(n)) return undefined;
  return n;
}

// ── Routes ──────────────────────────────────────────────────────────

app.get("/health", async (c) => {
  try {
    const conn = await pool.getConnection();
    await conn.query("SELECT 1");
    conn.release();
    return c.json({ status: "ok" });
  } catch {
    return c.json({ status: "error", detail: "Database unavailable" }, 503);
  }
});

app.get("/kills", async (c) => {
  const q = c.req.query();

  const limit = parseIntParam(q.limit);
  const offset = parseIntParam(q.offset);

  if (limit !== undefined && (limit < 1 || limit > 1000)) {
    return c.json({ error: "limit must be between 1 and 1000" }, 400);
  }
  if (offset !== undefined && offset < 0) {
    return c.json({ error: "offset must be >= 0" }, 400);
  }

  const query: KillListQuery = {
    limit,
    offset,
    min_value: parseFloatParam(q.min_value),
    max_value: parseFloatParam(q.max_value),
    solar_system_id: parsePositiveInt(q.solar_system_id),
    ship_type_id: parsePositiveInt(q.ship_type_id),
    character_id: parsePositiveInt(q.character_id),
    corporation_id: parsePositiveInt(q.corporation_id),
    alliance_id: parsePositiveInt(q.alliance_id),
    npc: q.npc !== undefined ? q.npc === "true" : undefined,
    solo: q.solo !== undefined ? q.solo === "true" : undefined,
    awox: q.awox !== undefined ? q.awox === "true" : undefined,
  };

  const result = await repo.listKills(query);
  return c.json(result);
});

app.get("/kills/:id", async (c) => {
  const killmailId = parseInt(c.req.param("id"), 10);
  if (isNaN(killmailId) || killmailId < 1) {
    return c.json({ error: "Invalid killmail ID" }, 400);
  }

  const kill = await repo.getKill(killmailId);
  if (!kill) return c.json({ error: "Killmail not found" }, 404);
  return c.json(kill);
});

app.get("/stats", async (c) => {
  const stats = await repo.getStats();
  return c.json(stats);
});

// ── Poller ──────────────────────────────────────────────────────────

let zkill: ZKillboardR2Z2 | null = null;

if (config.poller.enabled) {
  const pipeline = new FilterPipeline();

  if (config.poller.excludeNpc) {
    pipeline.addLevel1(new NpcFilter(true));
  }
  if (config.poller.securityZones.length) {
    pipeline.addLevel1(new SecurityFilter(config.poller.securityZones));
  }
  if (config.poller.minValue) {
    pipeline.addLevel2(new MinValueFilter(config.poller.minValue));
  }

  zkill = new ZKillboardR2Z2(config.poller.stateFile, pipeline);

  zkill.poll(async (killmail: R2Z2Killmail, sequenceId: number) => {
    try {
      const saved = await repo.save(killmail);
      const value = (killmail.zkb.totalValue ?? 0).toLocaleString();
      const status = saved ? "saved" : "skipped (duplicate)";
      console.log(`[#${sequenceId}] Kill ${killmail.killmail_id} | ${value} ISK | ${status}`);
    } catch (e) {
      console.error(`Error saving killmail at sequence ${sequenceId}:`, e);
    }
  });

  console.log("Poller started");
}

// ── Shutdown ────────────────────────────────────────────────────────

function handleShutdown() {
  console.log("Shutting down...");
  if (zkill) zkill.stop();
  pool.end().catch(() => {});
  process.exit(0);
}

process.on("SIGTERM", handleShutdown);
process.on("SIGINT", handleShutdown);

// ── Server ──────────────────────────────────────────────────────────

console.log(`Server starting on port ${config.port}`);

serve({
  fetch: app.fetch,
  port: config.port,
});

export default app;
