import { io, type Socket } from "socket.io-client";
import jwt from "jsonwebtoken";
import { getJWTSecret } from "../middleware/auth.js";

const BOT_TIMING = {
  introReadyMs: { min: 1500, max: 2500 },
  buzzMs: { min: 3000, max: 7000 },
  answerMs: { min: 800, max: 2000 },
};

function randomBetween(min: number, max: number): number {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

export class BotPlayerService {
  private socket: Socket;
  private botPlayerId: string;
  private roomId: string;
  private introTimer: ReturnType<typeof setTimeout> | null = null;
  private buzzTimer: ReturnType<typeof setTimeout> | null = null;
  private answerTimer: ReturnType<typeof setTimeout> | null = null;
  private onCleanup: (() => void) | undefined;

  constructor(roomId: string, onCleanup?: () => void) {
    this.roomId = roomId;
    this.botPlayerId = `bot_${roomId}`;
    this.onCleanup = onCleanup;

    const token = this.generateBotToken();
    const port = process.env.PORT || 3001;
    const serverUrl = `http://localhost:${port}`;

    this.socket = io(serverUrl, {
      auth: { token },
      transports: ["websocket"],
      reconnection: false,
      timeout: 5000,
    });

    this.setupEventHandlers(token);
  }

  private generateBotToken(): string {
    const issuedAt = Math.floor(Date.now() / 1000);
    const payload = {
      playerId: this.botPlayerId,
      playerName: "🤖 Bot",
      avatarId: null,
      roomId: this.roomId,
      iat: issuedAt,
      exp: issuedAt + 7200,
    };
    return jwt.sign(payload, getJWTSecret(), { algorithm: "HS256" });
  }

  private setupEventHandlers(token: string): void {
    this.socket.on("connect", () => {
      console.log(`[Bot] Connected for room ${this.roomId}`);
      this.socket.emit("join_room", {
        roomId: this.roomId,
        playerId: this.botPlayerId,
        playerName: "🤖 Bot",
        token,
      });
    });

    this.socket.on("state", (data: { state?: { phase?: string } } | null) => {
      const phase = data?.state?.phase;
      if (phase === "LOBBY") {
        this.socket.emit("ready", { roomId: this.roomId, isReady: true });
        console.log(`[Bot] Sent ready from state event (room ${this.roomId})`);
      }
    });

    this.socket.on("connect_error", (err: Error) => {
      console.error(`[Bot] Connection error for room ${this.roomId}: ${err.message}`);
    });

    this.socket.on("error", (err: unknown) => {
      console.error(`[Bot] Socket error for room ${this.roomId}:`, err);
    });

    this.socket.on("event", (data: { event?: { type: string; playerId?: string } }) => {
      if (
        data.event?.type === "PLAYER_LEFT" &&
        data.event?.playerId !== this.botPlayerId
      ) {
        console.log(`[Bot] Human player left room ${this.roomId}, disconnecting`);
        setTimeout(() => this.disconnect(), 300);
      }
    });

    this.socket.on("phase_changed", (data: { phase: string; lockedPlayerId?: string }) => {
      const { phase, lockedPlayerId } = data;
      console.log(`[Bot] Phase changed to ${phase} (room ${this.roomId})`);
      this.clearTimers();

      switch (phase) {
        case "LOBBY": {
          const delay = randomBetween(BOT_TIMING.introReadyMs.min, BOT_TIMING.introReadyMs.max);
          this.introTimer = setTimeout(() => {
            this.introTimer = null;
            this.socket.emit("ready", { roomId: this.roomId, isReady: true });
            console.log(`[Bot] Sent ready for LOBBY (room ${this.roomId})`);
          }, delay);
          break;
        }

        case "INTRO":
        case "WAITING": {
          // Between rounds: no action needed from bot
          break;
        }

        case "QUESTION_ACTIVE": {
          const delay = randomBetween(BOT_TIMING.buzzMs.min, BOT_TIMING.buzzMs.max);
          this.buzzTimer = setTimeout(() => {
            this.socket.emit("buzz", {
              roomId: this.roomId,
              clientTimeMs: Date.now(),
            });
            console.log(`[Bot] Buzzed (room ${this.roomId})`);
          }, delay);
          break;
        }

        case "ANSWER_SELECTION": {
          if (lockedPlayerId === this.botPlayerId) {
            this.scheduleAnswer();
          }
          break;
        }

        case "MATCH_END": {
          console.log(`[Bot] Match ended (room ${this.roomId}), disconnecting`);
          setTimeout(() => this.disconnect(), 500);
          break;
        }

        default:
          break;
      }
    });

    this.socket.on("buzz_winner", (data: { playerId: string }) => {
      if (data.playerId === this.botPlayerId) {
        console.log(`[Bot] Won buzz (room ${this.roomId}), scheduling answer`);
        this.scheduleAnswer();
      }
    });

    this.socket.on("match_ended", () => {
      console.log(`[Bot] match_ended event (room ${this.roomId}), disconnecting`);
      setTimeout(() => this.disconnect(), 500);
    });

    this.socket.on("disconnect", () => {
      console.log(`[Bot] Disconnected (room ${this.roomId})`);
      this.clearTimers();
      this.onCleanup?.();
      this.onCleanup = undefined;
    });
  }

  private scheduleAnswer(): void {
    this.clearTimers();
    const delay = randomBetween(BOT_TIMING.answerMs.min, BOT_TIMING.answerMs.max);
    this.answerTimer = setTimeout(() => {
      const randomIndex = Math.floor(Math.random() * 4);
      this.socket.emit("answer", {
        roomId: this.roomId,
        answer: randomIndex,
      });
      console.log(`[Bot] Answered index ${randomIndex} (room ${this.roomId})`);
    }, delay);
  }

  private clearTimers(): void {
    if (this.introTimer) {
      clearTimeout(this.introTimer);
      this.introTimer = null;
    }
    if (this.buzzTimer) {
      clearTimeout(this.buzzTimer);
      this.buzzTimer = null;
    }
    if (this.answerTimer) {
      clearTimeout(this.answerTimer);
      this.answerTimer = null;
    }
  }

  disconnect(): void {
    this.clearTimers();
    this.socket.disconnect();
    // onCleanup is called by the socket 'disconnect' event listener
  }

  getBotPlayerId(): string {
    return this.botPlayerId;
  }

  isConnected(): boolean {
    return this.socket.connected;
  }
}
