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

// ── Routes ──────────────────────────────────────────────────────────

app.get("/kills", async (c) => {
  const q = c.req.query();

  const query: KillListQuery = {
    limit: q.limit ? parseInt(q.limit) : undefined,
    offset: q.offset ? parseInt(q.offset) : undefined,
    min_value: q.min_value ? parseFloat(q.min_value) : undefined,
    max_value: q.max_value ? parseFloat(q.max_value) : undefined,
    solar_system_id: q.solar_system_id ? parseInt(q.solar_system_id) : undefined,
    ship_type_id: q.ship_type_id ? parseInt(q.ship_type_id) : undefined,
    character_id: q.character_id ? parseInt(q.character_id) : undefined,
    corporation_id: q.corporation_id ? parseInt(q.corporation_id) : undefined,
    alliance_id: q.alliance_id ? parseInt(q.alliance_id) : undefined,
    npc: q.npc !== undefined ? q.npc === "true" : undefined,
    solo: q.solo !== undefined ? q.solo === "true" : undefined,
    awox: q.awox !== undefined ? q.awox === "true" : undefined,
  };

  const result = await repo.listKills(query);
  return c.json(result);
});

app.get("/kills/:id", async (c) => {
  const killmailId = parseInt(c.req.param("id"));
  const kill = await repo.getKill(killmailId);

  if (!kill) return c.json({ error: "Killmail not found" }, 404);
  return c.json(kill);
});

app.get("/stats", async (c) => {
  const stats = await repo.getStats();
  return c.json(stats);
});

// ── Poller ──────────────────────────────────────────────────────────

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

  const zkill = new ZKillboardR2Z2(config.poller.stateFile, pipeline);

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

// ── Server ──────────────────────────────────────────────────────────

console.log(`Server starting on port ${config.port}`);

serve({
  fetch: app.fetch,
  port: config.port,
});

export default app;
