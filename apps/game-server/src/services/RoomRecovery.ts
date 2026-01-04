import type { GameState, GameConfig, DEFAULT_DUO_CONFIG } from "../../../../packages/shared/src/types.js";
import type { GameEvent } from "../../../../packages/shared/src/events.js";
import { applyEvent, createInitialState } from "../../../../packages/game-engine/src/reducer.js";
import { getEventLog, getRoomState, setRoomState, cleanupRoom } from "./RedisService.js";
import type { RoomManager, Room, RoomPipelineConfig } from "./RoomManager.js";

export type RecoveryMetadata = {
  pipelineConfig?: RoomPipelineConfig;
  usedQuestionIds?: string[];
};

function createRecoveryInitialState(
  sessionId: string,
  lobbyCode: string,
  config: GameConfig
): GameState {
  return createInitialState(sessionId, lobbyCode, config);
}

function restoreRoomFromState(
  roomManager: RoomManager,
  roomId: string,
  state: GameState,
  events: GameEvent[],
  metadata?: RecoveryMetadata
): Room {
  const room: Room = {
    state,
    events,
    pipelineConfig: metadata?.pipelineConfig,
    usedQuestionIds: metadata?.usedQuestionIds ? new Set(metadata.usedQuestionIds) : undefined,
  };

  roomManager.restoreRoom(roomId, room);
  return room;
}

export async function rehydrateRoom(
  roomManager: RoomManager,
  roomId: string
): Promise<Room | null> {
  const cachedData = await getRoomState(roomId);
  if (cachedData && typeof cachedData === 'object' && 'state' in cachedData) {
    const { state, events, metadata } = cachedData as { 
      state: GameState; 
      events: GameEvent[]; 
      metadata?: RecoveryMetadata;
    };
    const room = restoreRoomFromState(roomManager, roomId, state, events || [], metadata);
    console.log(`[RoomRecovery] Restored room ${roomId} from cached state`);
    return room;
  }

  const events = await getEventLog(roomId);
  if (events.length === 0) {
    console.log(`[RoomRecovery] No events found for room ${roomId}`);
    return null;
  }

  const firstEvent = events[0] as GameEvent;
  if (!firstEvent || !firstEvent.sessionId) {
    console.log(`[RoomRecovery] Invalid events for room ${roomId}`);
    return null;
  }

  let state: GameState | null = null;
  
  for (const rawEvent of events) {
    const event = rawEvent as GameEvent;
    
    if (event.type === "PLAYER_JOINED" && !state) {
      const { DEFAULT_DUO_CONFIG } = await import("../../../../packages/shared/src/types.js");
      state = createRecoveryInitialState(roomId, "", DEFAULT_DUO_CONFIG);
    }
    
    if (event.type === "GAME_STARTED" && !state) {
      const { DEFAULT_DUO_CONFIG } = await import("../../../../packages/shared/src/types.js");
      state = createRecoveryInitialState(roomId, "", DEFAULT_DUO_CONFIG);
    }
    
    if (state) {
      try {
        state = applyEvent(state, event);
      } catch (err) {
        console.error(`[RoomRecovery] Error applying event ${event.id} (${event.type}):`, err);
        break;
      }
    }
  }

  if (!state) {
    console.log(`[RoomRecovery] Could not reconstruct state for room ${roomId}`);
    return null;
  }

  const room = restoreRoomFromState(roomManager, roomId, state, events as GameEvent[]);

  await setRoomState(roomId, { state, events, metadata: {} });

  console.log(`[RoomRecovery] Rehydrated room ${roomId} from ${events.length} events`);
  return room;
}

export async function canRecoverRoom(roomId: string): Promise<boolean> {
  const cachedData = await getRoomState(roomId);
  if (cachedData) return true;
  
  const events = await getEventLog(roomId);
  return events.length > 0;
}

export async function cleanupOldRoom(roomId: string): Promise<void> {
  await cleanupRoom(roomId);
  console.log(`[RoomRecovery] Cleaned up room ${roomId}`);
}

export async function saveRoomSnapshot(
  roomId: string,
  state: GameState,
  events: GameEvent[],
  metadata?: RecoveryMetadata
): Promise<void> {
  await setRoomState(roomId, { state, events, metadata });
}
