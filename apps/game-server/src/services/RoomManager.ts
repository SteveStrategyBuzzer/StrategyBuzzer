import { v4 as uuidv4 } from "uuid";
import type { GameState, GameConfig, DEFAULT_DUO_CONFIG } from "../../../../packages/shared/src/types.js";
import type { GameEvent, PlayerJoinedEvent, GameStartedEvent, PhaseChangedEvent, BuzzReceivedEvent } from "../../../../packages/shared/src/events.js";
import { createInitialState, applyEvent } from "../../../../packages/game-engine/src/reducer.js";
import { getPhaseTimeout, getNextPhase } from "../../../../packages/game-engine/src/state-machine.js";

export type RoomPipelineConfig = {
  theme: string;
  niveau: number;
  language: string;
  maxRounds: number;
};

export type Room = {
  state: GameState;
  events: GameEvent[];
  phaseTimer?: NodeJS.Timeout;
  questionGenerationTimer?: NodeJS.Timeout;
  pipelineConfig?: RoomPipelineConfig;
  usedQuestionIds?: Set<string>;
};

export class RoomManager {
  private rooms: Map<string, Room> = new Map();
  private playerToRoom: Map<string, string> = new Map();

  createRoom(config: GameConfig, hostId?: string): { roomId: string; lobbyCode: string } {
    const roomId = uuidv4();
    const lobbyCode = this.generateLobbyCode();
    
    const state = createInitialState(roomId, lobbyCode, config);
    
    const room: Room = {
      state,
      events: [],
    };
    
    this.rooms.set(roomId, room);
    console.log(`[RoomManager] Created room ${roomId} with code ${lobbyCode}`);
    
    return { roomId, lobbyCode };
  }

  getRoom(roomId: string): Room | undefined {
    return this.rooms.get(roomId);
  }

  getRoomByCode(code: string): Room | undefined {
    for (const room of this.rooms.values()) {
      if (room.state.lobbyCode === code) {
        return room;
      }
    }
    return undefined;
  }

  getRoomIdByCode(code: string): string | undefined {
    for (const [roomId, room] of this.rooms.entries()) {
      if (room.state.lobbyCode === code) {
        return roomId;
      }
    }
    return undefined;
  }

  getPlayerRoom(playerId: string): string | undefined {
    return this.playerToRoom.get(playerId);
  }

  joinRoom(roomId: string, playerId: string, name: string, options: Partial<PlayerJoinedEvent> = {}): GameEvent | null {
    const room = this.rooms.get(roomId);
    if (!room) return null;
    
    if (room.state.phase !== "LOBBY") {
      console.log(`[RoomManager] Cannot join room ${roomId}: game already started`);
      return null;
    }
    
    if (Object.keys(room.state.players).length >= room.state.config.maxPlayers) {
      console.log(`[RoomManager] Cannot join room ${roomId}: room is full`);
      return null;
    }
    
    const event: PlayerJoinedEvent = {
      id: room.state.lastEventId + 1,
      type: "PLAYER_JOINED",
      atMs: Date.now(),
      sessionId: roomId,
      playerId,
      name,
      avatarId: options.avatarId,
      strategicAvatarId: options.strategicAvatarId,
      isBot: options.isBot,
      isHost: Object.keys(room.state.players).length === 0,
      teamId: options.teamId,
      division: options.division,
    };
    
    room.state = applyEvent(room.state, event);
    room.events.push(event);
    this.playerToRoom.set(playerId, roomId);
    
    console.log(`[RoomManager] Player ${name} (${playerId}) joined room ${roomId}`);
    return event;
  }

  leaveRoom(roomId: string, playerId: string): GameEvent | null {
    const room = this.rooms.get(roomId);
    if (!room) return null;
    
    const event: GameEvent = {
      id: room.state.lastEventId + 1,
      type: "PLAYER_LEFT",
      atMs: Date.now(),
      sessionId: roomId,
      playerId,
      reason: "disconnect",
    };
    
    room.state = applyEvent(room.state, event);
    room.events.push(event);
    this.playerToRoom.delete(playerId);
    
    console.log(`[RoomManager] Player ${playerId} left room ${roomId}`);
    
    if (Object.keys(room.state.players).length === 0) {
      this.destroyRoom(roomId);
    }
    
    return event;
  }

  startGame(roomId: string): GameEvent | null {
    const room = this.rooms.get(roomId);
    if (!room) return null;
    
    if (room.state.phase !== "LOBBY") {
      console.log(`[RoomManager] Cannot start game: not in LOBBY phase`);
      return null;
    }
    
    const playerCount = Object.keys(room.state.players).length;
    if (playerCount < 2) {
      console.log(`[RoomManager] Cannot start game: need at least 2 players`);
      return null;
    }
    
    const event: GameStartedEvent = {
      id: room.state.lastEventId + 1,
      type: "GAME_STARTED",
      atMs: Date.now(),
      sessionId: roomId,
      config: {
        mode: room.state.config.mode,
        questionsPerRound: room.state.config.questionsPerRound,
        roundsToWin: room.state.config.roundsToWin,
      },
    };
    
    room.state = applyEvent(room.state, event);
    room.events.push(event);
    
    console.log(`[RoomManager] Game started in room ${roomId}`);
    return event;
  }

  registerBuzz(roomId: string, playerId: string, clientBuzzTimeMs: number): GameEvent | null {
    const room = this.rooms.get(roomId);
    if (!room) return null;
    
    if (room.state.phase !== "QUESTION_ACTIVE") {
      console.log(`[RoomManager] Cannot buzz: not in QUESTION_ACTIVE phase`);
      return null;
    }
    
    if (room.state.buzzQueue.some(b => b.playerId === playerId)) {
      console.log(`[RoomManager] Player ${playerId} already buzzed`);
      return null;
    }
    
    const serverTimeMs = Date.now();
    const latencyMs = serverTimeMs - clientBuzzTimeMs;
    
    const event: BuzzReceivedEvent = {
      id: room.state.lastEventId + 1,
      type: "BUZZ_RECEIVED",
      atMs: serverTimeMs,
      sessionId: roomId,
      playerId,
      buzzTimeMs: clientBuzzTimeMs,
      serverReceivedAtMs: serverTimeMs,
      latencyMs,
      position: room.state.buzzQueue.length + 1,
    };
    
    room.state = applyEvent(room.state, event);
    room.events.push(event);
    
    console.log(`[RoomManager] Buzz from ${playerId} at position ${event.position}, latency: ${latencyMs}ms`);
    return event;
  }

  transitionPhase(roomId: string, toPhase: GameState["phase"]): GameEvent | null {
    const room = this.rooms.get(roomId);
    if (!room) return null;
    
    const event: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase,
      phaseEndsAtMs: Date.now() + getPhaseTimeout({ ...room.state, phase: toPhase }),
      questionIndex: toPhase === "QUESTION_ACTIVE" ? room.state.questionIndex : undefined,
      roundNumber: room.state.currentRound,
    };
    
    room.state = applyEvent(room.state, event);
    room.events.push(event);
    
    console.log(`[RoomManager] Phase changed: ${event.fromPhase} -> ${event.toPhase}`);
    return event;
  }

  getState(roomId: string): GameState | null {
    const room = this.rooms.get(roomId);
    return room?.state ?? null;
  }

  getEvents(roomId: string, fromEventId: number = 0): GameEvent[] {
    const room = this.rooms.get(roomId);
    if (!room) return [];
    return room.events.filter(e => e.id > fromEventId);
  }

  destroyRoom(roomId: string): void {
    const room = this.rooms.get(roomId);
    if (room) {
      if (room.phaseTimer) clearTimeout(room.phaseTimer);
      if (room.questionGenerationTimer) clearTimeout(room.questionGenerationTimer);
      
      for (const playerId of Object.keys(room.state.players)) {
        this.playerToRoom.delete(playerId);
      }
      
      this.rooms.delete(roomId);
      console.log(`[RoomManager] Destroyed room ${roomId}`);
    }
  }

  private generateLobbyCode(): string {
    const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    let code = "";
    for (let i = 0; i < 6; i++) {
      code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return code;
  }

  getRoomCount(): number {
    return this.rooms.size;
  }

  getActivePlayerCount(): number {
    let count = 0;
    for (const room of this.rooms.values()) {
      count += Object.values(room.state.players).filter(p => p.isConnected).length;
    }
    return count;
  }
}
