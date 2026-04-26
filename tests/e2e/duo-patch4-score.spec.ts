/**
 * Patch 4/4 — Score grid validation (V3 DUO).
 *
 * What this spec proves
 * ---------------------
 * Patch 4 mandates that Node's `GameOrchestrator` is the sole arbiter of the
 * Duo score delta and that `duo_answer.blade.php`'s `showResult()` renders
 * that delta verbatim — no client-side recomputation, no judgment text.
 *
 * The corresponding code path on the server is:
 *
 *   buzzed + correct + 1st  →  +2  (calculateScore)
 *   buzzed + correct + 2nd+ →  +1
 *   buzzed + wrong          →  -2
 *   buzzed + no answer      →  -2 (default)  /  0 (timeout_forgiveness skill)
 *   no buzz at all          →  no answer_revealed for that player
 *                              (their score stays unchanged → effectively 0)
 *
 * Strategy
 * --------
 * We test each scenario DETERMINISTICALLY by driving two raw socket.io
 * clients (no browser, no Laravel pipeline, no random bot) against the
 * live game-server. For every scenario we:
 *
 *   1. POST /rooms (DUO, hasBot=false, customConfig: 1 question per round,
 *      N rounds per match) → fresh roomId
 *   2. POST /rooms/:roomId/questions with one MCQ question whose
 *      correctIndex is hard-coded by the test (no LLM, no debug backdoor);
 *      `startGame()` skips the pipeline whenever questions are pre-loaded.
 *   3. Connect P1 and P2 over Socket.IO with valid JWTs
 *   4. join_room → ready (both) → INTRO → question_page_ready (both, on
 *      SYNC for rounds 2+) → QUESTION_ACTIVE → drive scripted buzz / answer
 *      behaviour → assert on `answer_revealed.pointsEarned` and
 *      `score_update.totalScore`.
 *
 * Two further tests complete coverage:
 *
 *   • a cumulative-score test (3 rounds, mixed outcomes) verifies the
 *     final `score_update.totalScore` == sum of self pointsEarned;
 *   • static source checks of `resources/views/duo_answer.blade.php`
 *     verify `showResult()` contains none of the banned judgment strings
 *     and never triggers a navigation back to the question page (the
 *     "parasitic Result→Question hop" the original task warned against).
 */

import { test, expect, type APIRequestContext } from '@playwright/test';
import { io as ioClient, type Socket } from 'socket.io-client';
import jwt from 'jsonwebtoken';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';

// ---------------------------------------------------------------------------
// Wire-protocol payload types (subset of what GameOrchestrator emits).
// ---------------------------------------------------------------------------

interface AnswerRevealedPayload {
  playerId: string;
  isCorrect: boolean;
  correctAnswer: number | string | boolean;
  correctIndex?: number;
  pointsEarned: number;
  totalScore: number;
  roundScore: number;
}

interface ScoreUpdatePayload {
  playerId: string;
  totalScore?: number;
  score?: number;
  roundScore?: number;
}

interface PhaseChangedPayload {
  phase?: string;
  toPhase?: string;
  fromPhase?: string;
  questionIndex?: number;
  lockedPlayerId?: string;
}

interface MatchEndedPayload {
  winnerId?: string | null;
  finalScores?: Record<string, number>;
}

// ---------------------------------------------------------------------------
// Game-server config.
// ---------------------------------------------------------------------------

const GAME_SERVER_URL = process.env.GAME_SERVER_URL ?? 'http://localhost:3001';
const JWT_SECRET =
  process.env.GAME_SERVER_JWT_SECRET ??
  'strategybuzzer-game-server-jwt-secret-2026';

const SCENARIO_TIMEOUT_MS = 90_000;

// ---------------------------------------------------------------------------
// Helpers.
// ---------------------------------------------------------------------------

function makePlayerToken(playerId: string, playerName: string): string {
  const issuedAt = Math.floor(Date.now() / 1000);
  return jwt.sign(
    {
      playerId,
      playerName,
      avatarId: null,
      iat: issuedAt,
      exp: issuedAt + 3600,
    },
    JWT_SECRET,
    { algorithm: 'HS256' },
  );
}

interface DriverEvents {
  phases: PhaseChangedPayload[];
  reveals: AnswerRevealedPayload[];
  scoreUpdates: ScoreUpdatePayload[];
  matchEnded: MatchEndedPayload | null;
  buzzWinners: { playerId: string; position?: number }[];
}

interface PlayerDriver {
  socket: Socket;
  events: DriverEvents;
  playerId: string;
  playerName: string;
}

function attachListeners(socket: Socket): DriverEvents {
  const events: DriverEvents = {
    phases: [],
    reveals: [],
    scoreUpdates: [],
    matchEnded: null,
    buzzWinners: [],
  };

  socket.on('phase_changed', (data: PhaseChangedPayload) => {
    events.phases.push(data);
  });
  socket.on('answer_revealed', (data: AnswerRevealedPayload) => {
    events.reveals.push(data);
  });
  socket.on('score_update', (data: ScoreUpdatePayload) => {
    events.scoreUpdates.push(data);
  });
  socket.on('match_ended', (data: MatchEndedPayload) => {
    events.matchEnded = data;
  });
  socket.on('buzz_winner', (data: { playerId: string; position?: number }) => {
    events.buzzWinners.push(data);
  });

  return events;
}

async function connectPlayer(
  roomId: string,
  playerId: string,
  playerName: string,
): Promise<PlayerDriver> {
  const token = makePlayerToken(playerId, playerName);
  const socket = ioClient(GAME_SERVER_URL, {
    auth: { token },
    transports: ['websocket'],
    reconnection: false,
    timeout: 5_000,
    forceNew: true,
  });

  const events = attachListeners(socket);

  await new Promise<void>((resolve, reject) => {
    const onConnectError = (err: Error) => {
      socket.off('connect', onConnect);
      reject(new Error(`socket connect failed: ${err.message}`));
    };
    const onConnect = () => {
      socket.off('connect_error', onConnectError);
      resolve();
    };
    socket.once('connect', onConnect);
    socket.once('connect_error', onConnectError);
  });

  socket.emit('join_room', {
    roomId,
    playerId,
    playerName,
    token,
  });

  // Wait for our PLAYER_JOINED echo (or any state event) so the server
  // has registered the join before we move on.
  await new Promise<void>((resolve, reject) => {
    const t = setTimeout(
      () => reject(new Error(`timeout waiting for join_room echo (${playerId})`)),
      8_000,
    );
    const onState = () => {
      clearTimeout(t);
      socket.off('state', onState);
      resolve();
    };
    socket.once('state', onState);
  });

  return { socket, events, playerId, playerName };
}

async function waitForPhase(
  driver: PlayerDriver,
  phase: string,
  timeoutMs = 15_000,
): Promise<PhaseChangedPayload> {
  // Already received?
  const existing = driver.events.phases.find(
    (p) => p.phase === phase || p.toPhase === phase,
  );
  if (existing) return existing;

  return new Promise<PhaseChangedPayload>((resolve, reject) => {
    const t = setTimeout(
      () =>
        reject(
          new Error(
            `[${driver.playerId}] timeout waiting for phase=${phase}; saw=${JSON.stringify(
              driver.events.phases.map((p) => p.phase ?? p.toPhase),
            )}`,
          ),
        ),
      timeoutMs,
    );
    const onPhase = (data: PhaseChangedPayload) => {
      if (data.phase === phase || data.toPhase === phase) {
        clearTimeout(t);
        driver.socket.off('phase_changed', onPhase);
        resolve(data);
      }
    };
    driver.socket.on('phase_changed', onPhase);
  });
}

async function waitForReveal(
  driver: PlayerDriver,
  expectedPlayerId: string,
  timeoutMs = 15_000,
): Promise<AnswerRevealedPayload> {
  const existing = driver.events.reveals.find(
    (r) => r.playerId === expectedPlayerId,
  );
  if (existing) return existing;

  return new Promise<AnswerRevealedPayload>((resolve, reject) => {
    const t = setTimeout(
      () =>
        reject(
          new Error(
            `[${driver.playerId}] timeout waiting for answer_revealed for ${expectedPlayerId}`,
          ),
        ),
      timeoutMs,
    );
    const onReveal = (data: AnswerRevealedPayload) => {
      if (data.playerId === expectedPlayerId) {
        clearTimeout(t);
        driver.socket.off('answer_revealed', onReveal);
        resolve(data);
      }
    };
    driver.socket.on('answer_revealed', onReveal);
  });
}

async function disconnectAll(drivers: PlayerDriver[]): Promise<void> {
  for (const d of drivers) {
    try {
      d.socket.removeAllListeners();
      d.socket.disconnect();
    } catch {
      // Best-effort cleanup — never let teardown swallow a failing assertion.
    }
  }
}

// ---------------------------------------------------------------------------
// Deterministic question fixtures.
// Every test pre-loads its own questions via POST /rooms/:roomId/questions.
// `startGame()` skips the LLM pipeline whenever questions are already
// present, so the test owns the correctIndex outright — no LLM, no debug
// backdoor, no race against the question pipeline.
// ---------------------------------------------------------------------------

interface SeedQuestion {
  id: string;
  text: string;
  type: 'MCQ';
  choices: string[];
  correctIndex: number;
  difficulty: 1 | 2 | 3 | 4 | 5;
  category: string;
  timeLimitMs: number;
}

function makeSeedQuestion(correctIndex: 0 | 1 | 2 | 3): SeedQuestion {
  return {
    id: randomUUID(),
    text: `Patch4 deterministic MCQ — correctIndex=${correctIndex}`,
    type: 'MCQ',
    choices: ['Choice A', 'Choice B', 'Choice C', 'Choice D'],
    correctIndex,
    difficulty: 3,
    category: 'patch4-test',
    timeLimitMs: 15_000,
  };
}

interface ScenarioRoomOptions {
  maxRounds?: number;
  questionActiveMs?: number;
  introMs?: number;
}

async function setupRoom(
  request: APIRequestContext,
  hostId: string,
  seedQuestions: SeedQuestion[],
  opts: ScenarioRoomOptions = {},
): Promise<string> {
  const maxRounds = opts.maxRounds ?? 1;

  // Custom config: 1 question per round, N rounds. Tight timers keep the
  // test fast.
  const customConfig = {
    questionsPerRound: 1,
    maxRounds,
    roundsToWin: maxRounds + 1, // never wins early
    timers: {
      lobby: 5_000,
      intro: opts.introMs ?? 600,
      sync: 1_000,
      questionActive: opts.questionActiveMs ?? 6_000,
      answerSelection: 4_000,
      answerCollection: 1_500,
      result: 1_200,
      roundScoreboard: 1_000,
      reveal: 1_000,
      waiting: 2_000,
    },
  };

  // RoomManager rejects createRoom() without a lobbyCode (production
  // path requires Laravel to have generated one). Tests own theirs.
  const lobbyCode = `T${Math.random().toString(36).slice(2, 7).toUpperCase()}`;

  // theme/niveau/language are required by the server to populate
  // pipelineConfig (startGame guards on it). They are unused once we
  // pre-load questions, because startGame skips the pipeline.
  const createRes = await request.post(`${GAME_SERVER_URL}/rooms`, {
    data: {
      mode: 'DUO',
      hasBot: false,
      hostId,
      lobbyCode,
      theme: 'general',
      niveau: 5,
      language: 'fr',
      customConfig,
    },
  });
  expect(createRes.ok(), `POST /rooms failed: ${createRes.status()}`).toBe(true);
  const { roomId } = (await createRes.json()) as { roomId: string };

  // Pre-load deterministic questions BEFORE players ready up. startGame()
  // detects pre-loaded questions and skips the LLM pipeline entirely.
  const seedRes = await request.post(
    `${GAME_SERVER_URL}/rooms/${roomId}/questions`,
    { data: { questions: seedQuestions } },
  );
  expect(
    seedRes.ok(),
    `POST /rooms/${roomId}/questions failed: ${seedRes.status()}`,
  ).toBe(true);

  return roomId;
}

// In DUO mode the game-server auto-starts as soon as both players have
// emitted `ready` — there is no public HTTP /start handshake to call.
// We just wait until the engine moves into INTRO/SYNC.
async function readyForAutoStart(
  drivers: PlayerDriver[],
  roomId: string,
): Promise<void> {
  for (const d of drivers) {
    d.socket.emit('ready', { roomId, isReady: true });
  }
}

// Round-handshake helper. The first question of a match goes
// INTRO → QUESTION_ACTIVE (no SYNC). Subsequent questions go through SYNC,
// where every human player must emit `question_page_ready` to early-exit.
// We listen for whichever phase fires first and emit `question_page_ready`
// from both players (the server ignores the event when it's not in SYNC),
// then return — callers wait for QUESTION_ACTIVE themselves.
async function maybeHandshakeSync(
  drivers: PlayerDriver[],
  roomId: string,
): Promise<void> {
  const sawSync = await Promise.race([
    Promise.all(
      drivers.map((d) =>
        waitForPhase(d, 'SYNC', 12_000).then(() => true).catch(() => false),
      ),
    ).then((arr) => arr.every(Boolean)),
    waitForPhase(drivers[0], 'QUESTION_ACTIVE', 30_000)
      .then(() => false)
      .catch(() => false),
  ]);
  if (sawSync) {
    for (const d of drivers) {
      d.socket.emit('question_page_ready', { roomId });
    }
  }
}

// Pick any answer index distinct from `correct` so callers can deliberately
// answer wrong without caring which wrong index they hit.
function anyWrongIndex(correct: number): number {
  return correct === 0 ? 1 : 0;
}

// Used by the cumulative-score test to walk through a multi-round match.
async function playRound(
  driver: PlayerDriver,
  roomId: string,
  behaviour: {
    buzzAfterMs?: number; // omit / negative → don't buzz
    answerIndex?: number; // required if buzzing; omit → don't answer (timeout)
    answerAfterMs?: number;
  },
): Promise<void> {
  if (behaviour.buzzAfterMs == null || behaviour.buzzAfterMs < 0) return;
  await new Promise((r) => setTimeout(r, behaviour.buzzAfterMs));
  driver.socket.emit('buzz', { roomId, clientTimeMs: Date.now() });
  if (behaviour.answerIndex == null) return;
  await new Promise((r) => setTimeout(r, behaviour.answerAfterMs ?? 200));
  driver.socket.emit('answer', { roomId, answer: behaviour.answerIndex });
}

// ---------------------------------------------------------------------------
// Test suite.
// ---------------------------------------------------------------------------

test.describe('Duo Patch 4 — Score grid (Node = single source of truth)', () => {
  // ---- Six deterministic scenarios -----------------------------------------
  // Every scenario uses correctIndex = 2 (chosen out of {0,1,2,3} so neither
  // 0 nor 1 — the trivial-defaults — is the answer).

  const FIXED_CORRECT_INDEX = 2 as const;

  for (const scenario of [
    {
      name: 'S1 — buzz first + correct  → +2',
      expectedSelfPoints: 2,
      drive: async (
        roomId: string,
        p1: PlayerDriver,
        p2: PlayerDriver,
      ): Promise<void> => {
        // P1 buzzes immediately; P2 sits out. P1 answers correctly.
        p1.socket.emit('buzz', { roomId, clientTimeMs: Date.now() });
        await new Promise((r) => setTimeout(r, 250));
        p1.socket.emit('answer', { roomId, answer: FIXED_CORRECT_INDEX });
        void p2;
      },
    },
    {
      name: 'S2 — buzz first + wrong    → -2',
      expectedSelfPoints: -2,
      drive: async (
        roomId: string,
        p1: PlayerDriver,
        p2: PlayerDriver,
      ): Promise<void> => {
        p1.socket.emit('buzz', { roomId, clientTimeMs: Date.now() });
        await new Promise((r) => setTimeout(r, 250));
        p1.socket.emit('answer', {
          roomId,
          answer: anyWrongIndex(FIXED_CORRECT_INDEX),
        });
        void p2;
      },
    },
    {
      name: 'S3 — buzz second + correct → +1',
      expectedSelfPoints: 1,
      drive: async (
        roomId: string,
        p1: PlayerDriver,
        p2: PlayerDriver,
      ): Promise<void> => {
        // P2 buzzes first, P1 buzzes second. Both answer correctly.
        p2.socket.emit('buzz', { roomId, clientTimeMs: Date.now() });
        await new Promise((r) => setTimeout(r, 200));
        p1.socket.emit('buzz', { roomId, clientTimeMs: Date.now() });
        await new Promise((r) => setTimeout(r, 200));
        p2.socket.emit('answer', { roomId, answer: FIXED_CORRECT_INDEX });
        await new Promise((r) => setTimeout(r, 100));
        p1.socket.emit('answer', { roomId, answer: FIXED_CORRECT_INDEX });
      },
    },
    {
      name: 'S4 — buzz second + wrong   → -2',
      expectedSelfPoints: -2,
      drive: async (
        roomId: string,
        p1: PlayerDriver,
        p2: PlayerDriver,
      ): Promise<void> => {
        // P2 buzzes first (and answers correct), P1 buzzes second + wrong.
        p2.socket.emit('buzz', { roomId, clientTimeMs: Date.now() });
        await new Promise((r) => setTimeout(r, 200));
        p1.socket.emit('buzz', { roomId, clientTimeMs: Date.now() });
        await new Promise((r) => setTimeout(r, 200));
        p2.socket.emit('answer', { roomId, answer: FIXED_CORRECT_INDEX });
        await new Promise((r) => setTimeout(r, 100));
        p1.socket.emit('answer', {
          roomId,
          answer: anyWrongIndex(FIXED_CORRECT_INDEX),
        });
      },
    },
    {
      name: 'S5 — buzz then timeout     → -2',
      expectedSelfPoints: -2, // default Node grid (no timeout_forgiveness skill)
      drive: async (
        roomId: string,
        p1: PlayerDriver,
        p2: PlayerDriver,
      ): Promise<void> => {
        // P1 buzzes but never sends an answer; the ANSWER_SELECTION /
        // ANSWER_COLLECTION timer expires and Node assigns the default
        // -2 timeout penalty.
        p1.socket.emit('buzz', { roomId, clientTimeMs: Date.now() });
        // intentionally no answer
        void p2;
      },
    },
  ]) {
    test(scenario.name, async ({ request }) => {
      test.setTimeout(SCENARIO_TIMEOUT_MS);

      const hostId = `p1-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
      const guestId = `p2-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;

      const roomId = await setupRoom(request, hostId, [
        makeSeedQuestion(FIXED_CORRECT_INDEX),
      ]);
      const p1 = await connectPlayer(roomId, hostId, 'Player One');
      const p2 = await connectPlayer(roomId, guestId, 'Player Two');

      try {
        await readyForAutoStart([p1, p2], roomId);
        await maybeHandshakeSync([p1, p2], roomId);
        await waitForPhase(p1, 'QUESTION_ACTIVE', 30_000);

        await scenario.drive(roomId, p1, p2);

        // Wait until RESULT phase fires for P1.
        await waitForPhase(p1, 'RESULT', 25_000);

        // Server emits PHASE_CHANGED *before* the per-buzzer answer_revealed
        // events (see GameOrchestrator.revealAnswer). Wait explicitly for
        // OUR own reveal to land — every drive() in this loop has hostId buzz.
        const selfReveal = await waitForReveal(p1, hostId, 5_000).catch(
          () => undefined,
        );
        expect(
          selfReveal,
          `expected an answer_revealed for ${hostId}; got reveals=${JSON.stringify(p1.events.reveals)}`,
        ).toBeTruthy();
        expect(selfReveal!.pointsEarned).toBe(scenario.expectedSelfPoints);

        // Patch 4 invariant: every Node pointsEarned ∈ {-2, 0, 1, 2}
        const grid = new Set([-2, 0, 1, 2]);
        for (const r of p1.events.reveals) {
          expect(grid.has(r.pointsEarned)).toBe(true);
        }

        // Patch 4 / source-of-truth: latest score_update for self matches
        // the cumulative sum of self pointsEarned (here = single delta).
        const selfScoreUpdate = [...p1.events.scoreUpdates]
          .reverse()
          .find((s) => s.playerId === hostId);
        if (selfScoreUpdate) {
          const total =
            selfScoreUpdate.totalScore ?? selfScoreUpdate.score ?? null;
          expect(total).toBe(scenario.expectedSelfPoints);
        }
      } finally {
        await disconnectAll([p1, p2]);
      }
    });
  }

  // ---- Sixth scenario: no buzz at all → 0 (no event for self) --------------

  test('S6 — no buzz at all → 0 (no answer_revealed for self, score unchanged)', async ({
    request,
  }) => {
    test.setTimeout(SCENARIO_TIMEOUT_MS);

    const hostId = `p1-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
    const guestId = `p2-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;

    const roomId = await setupRoom(request, hostId, [
      makeSeedQuestion(FIXED_CORRECT_INDEX),
    ]);
    const p1 = await connectPlayer(roomId, hostId, 'Player One');
    const p2 = await connectPlayer(roomId, guestId, 'Player Two');

    try {
      await readyForAutoStart([p1, p2], roomId);
      await maybeHandshakeSync([p1, p2], roomId);
      await waitForPhase(p1, 'QUESTION_ACTIVE', 30_000);

      // P2 buzzes correctly, P1 stays silent.
      p2.socket.emit('buzz', { roomId, clientTimeMs: Date.now() });
      await new Promise((r) => setTimeout(r, 200));
      p2.socket.emit('answer', { roomId, answer: FIXED_CORRECT_INDEX });

      await waitForPhase(p1, 'RESULT', 25_000);

      // Reveals are emitted *after* PHASE_CHANGED. Wait for P2's reveal to
      // arrive (we need it for the sanity assertion below) before checking
      // that hostId has none.
      await waitForReveal(p1, guestId, 5_000).catch(() => undefined);

      // Patch 4: no answer_revealed event for the no-buzz player.
      const p1Reveals = p1.events.reveals.filter((r) => r.playerId === hostId);
      expect(
        p1Reveals.length,
        `expected ZERO answer_revealed for the no-buzz player; got ${JSON.stringify(p1Reveals)}`,
      ).toBe(0);

      // P1's totalScore must still be 0 (no points, no penalty).
      const p1ScoreUpdate = [...p1.events.scoreUpdates]
        .reverse()
        .find((s) => s.playerId === hostId);
      if (p1ScoreUpdate) {
        const total = p1ScoreUpdate.totalScore ?? p1ScoreUpdate.score ?? null;
        expect(total).toBe(0);
      }

      // Sanity: P2 should have received +2 (1st buzz + correct).
      const p2Reveal = p1.events.reveals.find((r) => r.playerId === guestId);
      expect(p2Reveal?.pointsEarned).toBe(2);
    } finally {
      await disconnectAll([p1, p2]);
    }
  });

  // ---- Seventh scenario: BOTH players silent → 0 / 0, no reveals ----------
  // Ensures the "no buzz at all" branch holds when nobody buzzes — the
  // QUESTION_ACTIVE timer expires, the engine advances without emitting
  // any answer_revealed, and both totals stay at 0.

  test('S7 — neither player buzzes → 0 / 0 (no reveals at all)', async ({
    request,
  }) => {
    test.setTimeout(SCENARIO_TIMEOUT_MS);

    const hostId = `p1-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
    const guestId = `p2-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;

    const roomId = await setupRoom(request, hostId, [
      makeSeedQuestion(FIXED_CORRECT_INDEX),
    ]);
    const p1 = await connectPlayer(roomId, hostId, 'Player One');
    const p2 = await connectPlayer(roomId, guestId, 'Player Two');

    try {
      await readyForAutoStart([p1, p2], roomId);
      await maybeHandshakeSync([p1, p2], roomId);
      await waitForPhase(p1, 'QUESTION_ACTIVE', 30_000);

      // Neither player buzzes — let QUESTION_ACTIVE time out naturally.
      await waitForPhase(p1, 'RESULT', 25_000);

      // Allow any straggler answer_revealed to land (there should be none).
      await new Promise((r) => setTimeout(r, 1_500));

      // Patch 4 — neither player buzzed: no answer_revealed at all.
      expect(
        p1.events.reveals.length,
        `expected ZERO reveals when neither player buzzed; got ${JSON.stringify(p1.events.reveals)}`,
      ).toBe(0);
      expect(
        p2.events.reveals.length,
        `expected ZERO reveals when neither player buzzed; got ${JSON.stringify(p2.events.reveals)}`,
      ).toBe(0);

      // If any score_update fired for either player, totals must be 0.
      for (const driver of [p1, p2]) {
        for (const su of driver.events.scoreUpdates) {
          const total = su.totalScore ?? su.score ?? null;
          if (total !== null) {
            expect(
              total,
              `score_update.totalScore should be 0 when nobody buzzed; got ${total} for ${su.playerId} (observer=${driver.playerId})`,
            ).toBe(0);
          }
        }
      }
    } finally {
      await disconnectAll([p1, p2]);
    }
  });

  // ---- Cumulative-score test (3 rounds, mixed outcomes) --------------------

  test('Cumulative — Node totalScore == Σ pointsEarned across 3 rounds', async ({
    request,
  }) => {
    test.setTimeout(2 * SCENARIO_TIMEOUT_MS);

    const hostId = `p1-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
    const guestId = `p2-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;

    const roomId = await setupRoom(
      request,
      hostId,
      [
        makeSeedQuestion(FIXED_CORRECT_INDEX),
        makeSeedQuestion(FIXED_CORRECT_INDEX),
        makeSeedQuestion(FIXED_CORRECT_INDEX),
      ],
      { maxRounds: 3 },
    );
    const p1 = await connectPlayer(roomId, hostId, 'Player One');
    const p2 = await connectPlayer(roomId, guestId, 'Player Two');

    try {
      await readyForAutoStart([p1, p2], roomId);

      // Round 1 — P1 buzzes first + correct  → +2
      // Round 2 — P1 buzzes first + wrong    → -2
      // Round 3 — P1 silent (P2 takes it)    →  no self event (0 delta)
      // Expected final P1 totalScore = 0
      type Play = (
        roomId: string,
        p1: PlayerDriver,
        p2: PlayerDriver,
      ) => Promise<void>;
      const plays: Play[] = [
        async (rid, _p1, _p2) => {
          await playRound(_p1, rid, {
            buzzAfterMs: 50,
            answerIndex: FIXED_CORRECT_INDEX,
            answerAfterMs: 200,
          });
        },
        async (rid, _p1, _p2) => {
          await playRound(_p1, rid, {
            buzzAfterMs: 50,
            answerIndex: anyWrongIndex(FIXED_CORRECT_INDEX),
            answerAfterMs: 200,
          });
        },
        async (rid, _p1, _p2) => {
          // P2 takes this one so the round still resolves promptly
          await playRound(_p2, rid, {
            buzzAfterMs: 50,
            answerIndex: FIXED_CORRECT_INDEX,
            answerAfterMs: 200,
          });
        },
      ];

      for (let i = 0; i < plays.length; i += 1) {
        await maybeHandshakeSync([p1, p2], roomId);
        await waitForPhase(p1, 'QUESTION_ACTIVE', 30_000);
        await plays[i](roomId, p1, p2);
        await waitForPhase(p1, 'RESULT', 25_000);
        // Wait until the next SYNC fires (or match ends) before driving the
        // next round. We don't drive that here — advancePastSync at the top
        // of the next iteration handles it.
        await new Promise((r) => setTimeout(r, 50));
      }

      // After the third RESULT, the engine moves through SCOREBOARD →
      // MATCH_END. Allow up to 15s for that final transition.
      await Promise.race([
        waitForPhase(p1, 'MATCH_END', 15_000),
        new Promise((r) => setTimeout(r, 12_000)),
      ]);

      const selfReveals = p1.events.reveals.filter((r) => r.playerId === hostId);
      const expectedSum = selfReveals.reduce((acc, r) => acc + r.pointsEarned, 0);

      // Final score_update for self.
      const lastSelfUpdate = [...p1.events.scoreUpdates]
        .reverse()
        .find((s) => s.playerId === hostId);
      expect(
        lastSelfUpdate,
        `expected at least one score_update for ${hostId}; got=${JSON.stringify(p1.events.scoreUpdates)}`,
      ).toBeTruthy();

      const lastTotal = lastSelfUpdate!.totalScore ?? lastSelfUpdate!.score ?? null;
      expect(
        lastTotal,
        `Node totalScore (${lastTotal}) does not match Σ pointsEarned (${expectedSum}); reveals=${JSON.stringify(selfReveals)}`,
      ).toBe(expectedSum);

      // Grid invariant on every observed reveal (any player).
      const grid = new Set([-2, 0, 1, 2]);
      for (const r of p1.events.reveals) {
        expect(
          grid.has(r.pointsEarned),
          `pointsEarned out of grid: got ${r.pointsEarned} (player=${r.playerId})`,
        ).toBe(true);
      }
    } finally {
      await disconnectAll([p1, p2]);
    }
  });

  // ---- Static source checks (Patch 4 wording invariants) -------------------

  test('Static — showResult() in duo_answer.blade.php contains no banned judgment text', () => {
    const path = join(process.cwd(), 'resources/views/duo_answer.blade.php');
    const src = readFileSync(path, 'utf8');

    // Locate the showResult function body. We use a light brace-counting scan
    // starting at `function showResult(` to extract the function body.
    const startIdx = src.indexOf('function showResult(');
    expect(
      startIdx,
      'could not locate `function showResult(` in duo_answer.blade.php',
    ).toBeGreaterThanOrEqual(0);

    // Find the opening `{` after the parameter list.
    const openBrace = src.indexOf('{', startIdx);
    expect(openBrace).toBeGreaterThan(startIdx);

    let depth = 0;
    let endIdx = -1;
    for (let i = openBrace; i < src.length; i += 1) {
      const ch = src[i];
      if (ch === '{') depth += 1;
      else if (ch === '}') {
        depth -= 1;
        if (depth === 0) {
          endIdx = i;
          break;
        }
      }
    }
    expect(endIdx).toBeGreaterThan(openBrace);

    const body = src.slice(openBrace, endIdx + 1);

    // (a) None of the banned judgment phrases may appear in USER-VISIBLE text
    //     emitted by `showResult()`. The visible text channels are:
    //       - `.textContent = …` / `.innerText = …` / `.innerHTML = …`
    //       - i18n helpers `__('…')` / `trans('…')` / `@lang('…')`
    //     CSS class names like `'correct'` (used in `classList.add(...)` and
    //     `result-overlay correct`) are NOT user-visible text and are allowed.
    const bannedWords = [
      'correct',
      'incorrect',
      'bonne réponse',
      'mauvaise réponse',
      'wrong',
      'right',
    ];

    function findVisibleAssignments(src: string): string[] {
      const out: string[] = [];
      // a.textContent = '...' / "..." / `...`
      const reText =
        /\.(?:textContent|innerText|innerHTML)\s*=\s*(['"`])((?:\\.|(?!\1).)*)\1/g;
      let m: RegExpExecArray | null;
      while ((m = reText.exec(src)) !== null) out.push(m[2]);
      // __('...') / trans('...') / @lang('...')
      const reI18n = /(?:__|trans|@lang)\s*\(\s*(['"`])((?:\\.|(?!\1).)*)\1/g;
      while ((m = reI18n.exec(src)) !== null) out.push(m[2]);
      return out;
    }

    const visibleStrings = findVisibleAssignments(body);
    for (const s of visibleStrings) {
      const lower = s.toLowerCase();
      for (const banned of bannedWords) {
        expect(
          lower.includes(banned),
          `showResult() emits banned visible text "${banned}" via "${s}"`,
        ).toBe(false);
      }
    }

    // (b) Patch 4 — `pointsEarned` must be the sole input that drives the
    //     numeric badge. Verify the function body references it.
    expect(
      /pointsEarned/.test(body),
      'showResult() must read `pointsEarned` from the answer_revealed payload',
    ).toBe(true);

    // (c) showResult() must not navigate back to the question page (the
    //     "parasitic Result→Question hop" the original review flagged).
    const navToQuestion =
      /(window\.location|location\.href|history\.(?:push|replace)State)[^;]{0,200}\/duo\/question/i;
    expect(
      navToQuestion.test(body),
      'showResult() must not redirect to /duo/question (parasitic hop)',
    ).toBe(false);
  });
});
