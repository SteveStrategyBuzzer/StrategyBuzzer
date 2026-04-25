import { Client } from 'pg';

export interface DuoMatchRow {
  id: number;
  status: string;
  player1_id: number;
  player2_id: number;
  player1_score: number;
  player2_score: number;
  is_finalized: boolean;
  started_at: string | null;
  finished_at: string | null;
  game_state: unknown;
}

function buildConnString(): string {
  if (process.env.DATABASE_URL) return process.env.DATABASE_URL;
  const host = process.env.PGHOST || 'localhost';
  const port = process.env.PGPORT || '5432';
  const user = process.env.PGUSER || 'postgres';
  const password = process.env.PGPASSWORD || '';
  const db = process.env.PGDATABASE || 'postgres';
  return `postgres://${user}:${encodeURIComponent(password)}@${host}:${port}/${db}`;
}

export async function getLatestDuoMatchForUser(
  userId: number,
): Promise<DuoMatchRow | null> {
  const client = new Client({ connectionString: buildConnString() });
  await client.connect();
  try {
    const r = await client.query(
      `SELECT id, status, player1_id, player2_id, player1_score, player2_score,
              (finished_at IS NOT NULL) AS is_finalized,
              started_at, finished_at, game_state
         FROM duo_matches
        WHERE player1_id = $1 OR player2_id = $1
        ORDER BY id DESC
        LIMIT 1`,
      [userId],
    );
    return (r.rows[0] as DuoMatchRow) || null;
  } finally {
    await client.end();
  }
}

export async function pollForFinalizedDuoMatch(
  userId: number,
  options: { timeoutMs?: number; intervalMs?: number; minId?: number } = {},
): Promise<DuoMatchRow | null> {
  const timeoutMs = options.timeoutMs ?? 90_000;
  const intervalMs = options.intervalMs ?? 5_000;
  const minId = options.minId ?? 0;
  const deadline = Date.now() + timeoutMs;
  let last: DuoMatchRow | null = null;
  while (Date.now() < deadline) {
    last = await getLatestDuoMatchForUser(userId);
    if (last && last.id >= minId && last.is_finalized) return last;
    await new Promise((r) => setTimeout(r, intervalMs));
  }
  return last;
}
