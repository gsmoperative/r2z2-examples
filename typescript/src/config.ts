function env(key: string, fallback: string): string {
  return process.env[key] ?? fallback;
}

function envBool(key: string, fallback: boolean): boolean {
  const v = process.env[key];
  if (v === undefined) return fallback;
  return v === "true" || v === "1";
}

function envNum(key: string, fallback: number): number {
  const v = process.env[key];
  return v !== undefined ? Number(v) : fallback;
}

function envList(key: string, fallback: string[]): string[] {
  const v = process.env[key];
  return v !== undefined ? v.split(",").map((s) => s.trim()) : fallback;
}

export const config = {
  db: {
    host: env("DB_HOST", "127.0.0.1"),
    port: envNum("DB_PORT", 3306),
    user: env("DB_USER", "root"),
    password: env("DB_PASS", ""),
    database: env("DB_NAME", "zkillboard"),
  },
  poller: {
    enabled: envBool("POLLER_ENABLED", true),
    stateFile: env("POLLER_STATE_FILE", "zkill_sequence.txt"),
    excludeNpc: envBool("POLLER_EXCLUDE_NPC", true),
    securityZones: envList("POLLER_SECURITY_ZONES", ["nullsec", "lowsec"]),
    minValue: envNum("POLLER_MIN_VALUE", 10_000_000),
  },
  port: envNum("PORT", 3000),
};
