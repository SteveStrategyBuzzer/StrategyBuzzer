import type { Express, Request, Response } from "express";
import type { RoomManager } from "../services/RoomManager.js";
import type { GameOrchestrator } from "../services/GameOrchestrator.js";
import { DEFAULT_DUO_CONFIG, DEFAULT_LEAGUE_INDIVIDUAL_CONFIG, DEFAULT_LEAGUE_TEAM_CONFIG, DEFAULT_MASTER_CONFIG } from "@strategybuzzer/shared";
import type { GameConfig, Mode } from "@strategybuzzer/shared";
import { rehydrateRoom, canRecoverRoom } from "../services/RoomRecovery.js";
import { MetricsService } from "../services/MetricsService.js";
import { BotPlayerService } from "../services/BotPlayerService.js";

const activeBots = new Map<string, BotPlayerService>();

function getConfigForMode(mode: Mode): GameConfig {
  switch (mode) {
    case "DUO":
      return DEFAULT_DUO_CONFIG;
    case "LEAGUE_INDIVIDUAL":
      return DEFAULT_LEAGUE_INDIVIDUAL_CONFIG;
    case "LEAGUE_TEAM":
      return DEFAULT_LEAGUE_TEAM_CONFIG;
    case "MASTER":
      return DEFAULT_MASTER_CONFIG;
    default:
      return DEFAULT_DUO_CONFIG;
  }
}

export function setupHttpRoutes(app: Express, roomManager: RoomManager, gameOrchestrator: GameOrchestrator): void {
  app.get("/health", (_req: Request, res: Response) => {
    res.json({
      status: "ok",
      rooms: roomManager.getRoomCount(),
      players: roomManager.getActivePlayerCount(),
      uptime: process.uptime(),
    });
  });

  app.get("/metrics", (_req: Request, res: Response) => {
    const metrics = MetricsService.getMetrics(
      roomManager.getRoomCount(),
      roomManager.getActivePlayerCount(),
      roomManager.getRoomsByPhase()
    );
    res.json(metrics);
  });

  app.post("/rooms", (req: Request, res: Response) => {
    try {
      const { mode = "DUO", hostId, customConfig, theme = "general", niveau = 5, language = "fr" } = req.body;
      
      const baseConfig = getConfigForMode(mode as Mode);
      const config: GameConfig = customConfig ? { ...baseConfig, ...customConfig } : baseConfig;
      
      const { roomId, lobbyCode } = roomManager.createRoom(config, hostId, req.body.lobbyCode);
      
      const room = roomManager.getRoom(roomId);
      if (room) {
        room.pipelineConfig = {
          theme,
          niveau,
          language,
          maxRounds: config.maxRounds,
        };
        console.log(`[HTTP] Stored pipeline config for room ${roomId}:`, room.pipelineConfig);
      }
      
      const wsUrl = `ws://${req.headers.host}`;
      
      res.status(201).json({
        success: true,
        roomId,
        lobbyCode,
        wsUrl,
        config,
        pipelineConfig: room?.pipelineConfig,
      });
    } catch (error) {
      console.error("[HTTP] Error creating room:", error);
      res.status(500).json({
        success: false,
        error: "Failed to create room",
      });
    }
  });

  app.get("/rooms/:roomId", (req: Request, res: Response) => {
    const { roomId } = req.params;
    const state = roomManager.getState(roomId);
    
    if (!state) {
      return res.status(404).json({
        success: false,
        error: "Room not found",
      });
    }
    
    const sanitizedState = {
      ...state,
      questions: state.questions.map(q => ({
        ...q,
        correctIndex: undefined,
        correctBool: undefined,
        correctText: undefined,
      })),
    };
    
    res.json({
      success: true,
      state: sanitizedState,
    });
  });

  app.get("/rooms/code/:code", (req: Request, res: Response) => {
    const { code } = req.params;
    const roomId = roomManager.getRoomIdByCode(code.toUpperCase());
    
    if (!roomId) {
      return res.status(404).json({
        success: false,
        error: "Room not found",
      });
    }
    
    const state = roomManager.getState(roomId);
    
    res.json({
      success: true,
      roomId,
      state: state ? {
        sessionId: state.sessionId,
        lobbyCode: state.lobbyCode,
        phase: state.phase,
        playerCount: Object.keys(state.players).length,
        maxPlayers: state.config.maxPlayers,
        mode: state.config.mode,
      } : null,
    });
  });

  app.post("/rooms/:roomId/start", async (req: Request, res: Response) => {
    const { roomId } = req.params;
    const { hostId } = req.body;
    
    const state = roomManager.getState(roomId);
    if (!state) {
      return res.status(404).json({
        success: false,
        error: "Room not found",
      });
    }
    
    const host = Object.values(state.players).find(p => p.isHost);
    if (host && hostId && host.id !== hostId) {
      return res.status(403).json({
        success: false,
        error: "Only the host can start the game",
      });
    }
    
    const room = roomManager.getRoom(roomId);
    if (!room?.pipelineConfig) {
      return res.status(400).json({
        success: false,
        error: "Pipeline config not set. Please recreate the room with theme, niveau, and language.",
      });
    }
    
    const startResult = await gameOrchestrator.startGame(roomId);
    
    if (!startResult.success) {
      return res.status(500).json({
        success: false,
        error: startResult.error || "Failed to start game",
      });
    }
    
    res.json({
      success: true,
      state: roomManager.getState(roomId),
    });
  });

  app.get("/rooms/:roomId/events", (req: Request, res: Response) => {
    const { roomId } = req.params;
    const fromEventId = parseInt(req.query.from as string) || 0;
    
    const events = roomManager.getEvents(roomId, fromEventId);
    
    res.json({
      success: true,
      events,
    });
  });

  app.post("/rooms/:roomId/questions", (req: Request, res: Response) => {
    const { roomId } = req.params;
    const { questions } = req.body;
    
    const room = roomManager.getRoom(roomId);
    if (!room) {
      return res.status(404).json({
        success: false,
        error: "Room not found",
      });
    }
    
    gameOrchestrator.setQuestions(roomId, questions);
    
    res.json({
      success: true,
      questionCount: questions.length,
    });
  });

  app.post("/rooms/:roomId/questions/append", (req: Request, res: Response) => {
    const { roomId } = req.params;
    const { questions } = req.body;
    
    const room = roomManager.getRoom(roomId);
    if (!room) {
      return res.status(404).json({
        success: false,
        error: "Room not found",
      });
    }
    
    const result = gameOrchestrator.appendQuestions(roomId, questions);
    
    res.json({
      success: result.success,
      appendedCount: questions.length,
      totalCount: result.totalCount,
    });
  });

  app.delete("/rooms/:roomId", (req: Request, res: Response) => {
    const { roomId } = req.params;
    
    const room = roomManager.getRoom(roomId);
    if (!room) {
      return res.status(404).json({
        success: false,
        error: "Room not found",
      });
    }
    
    roomManager.destroyRoom(roomId);
    
    res.json({
      success: true,
    });
  });

  if (process.env.APP_ENV !== "production") {
    app.post("/rooms/:roomId/bot", (req: Request, res: Response) => {
      const internalHeader = req.headers["x-internal-bot"];
      if (internalHeader !== "1") {
        return res.status(403).json({ success: false, error: "Forbidden" });
      }

      const { roomId } = req.params;

      const room = roomManager.getRoom(roomId);
      if (!room) {
        return res.status(404).json({ success: false, error: "Room not found" });
      }

      if (room.state.config.mode !== "DUO") {
        return res.status(400).json({ success: false, error: "Bot only available in DUO mode" });
      }

      const humanPlayers = Object.values(room.state.players).filter(
        (p) => !p.id.startsWith("bot_")
      );

      if (humanPlayers.length !== 1) {
        return res.status(400).json({
          success: false,
          error: humanPlayers.length === 0
            ? "No human player in room"
            : "Room already has a second player",
        });
      }

      if (activeBots.has(roomId)) {
        const existing = activeBots.get(roomId)!;
        if (existing.isConnected()) {
          return res.status(400).json({ success: false, error: "Bot already active in this room" });
        }
        activeBots.delete(roomId);
      }

      try {
        const bot = new BotPlayerService(roomId);
        activeBots.set(roomId, bot);

        setTimeout(() => {
          if (activeBots.has(roomId) && !activeBots.get(roomId)!.isConnected()) {
            activeBots.delete(roomId);
          }
        }, 10000);

        res.status(201).json({
          success: true,
          botPlayerId: bot.getBotPlayerId(),
        });
      } catch (error) {
        console.error("[HTTP] Error spawning bot:", error);
        res.status(500).json({ success: false, error: "Failed to spawn bot" });
      }
    });

    app.delete("/rooms/:roomId/bot", (req: Request, res: Response) => {
      const internalHeader = req.headers["x-internal-bot"];
      if (internalHeader !== "1") {
        return res.status(403).json({ success: false, error: "Forbidden" });
      }

      const { roomId } = req.params;
      const bot = activeBots.get(roomId);
      if (bot) {
        bot.disconnect();
        activeBots.delete(roomId);
      }

      res.json({ success: true });
    });
  }

  app.post("/webhook/match-complete", (req: Request, res: Response) => {
    const { sessionId, scores, events } = req.body;
    
    console.log(`[Webhook] Match complete: ${sessionId}`, scores);
    
    res.json({
      success: true,
      received: true,
    });
  });

  app.get("/rooms/:roomId/recover", async (req: Request, res: Response) => {
    const { roomId } = req.params;
    
    const existingRoom = roomManager.getRoom(roomId);
    if (existingRoom) {
      return res.json({
        success: true,
        recovered: false,
        message: "Room already exists in memory",
        state: {
          sessionId: existingRoom.state.sessionId,
          lobbyCode: existingRoom.state.lobbyCode,
          phase: existingRoom.state.phase,
          playerCount: Object.keys(existingRoom.state.players).length,
        },
      });
    }
    
    const canRecover = await canRecoverRoom(roomId);
    if (!canRecover) {
      return res.status(404).json({
        success: false,
        error: "Room not found and cannot be recovered",
      });
    }
    
    try {
      const room = await rehydrateRoom(roomManager, roomId);
      if (!room) {
        return res.status(500).json({
          success: false,
          error: "Failed to recover room",
        });
      }
      
      res.json({
        success: true,
        recovered: true,
        state: {
          sessionId: room.state.sessionId,
          lobbyCode: room.state.lobbyCode,
          phase: room.state.phase,
          playerCount: Object.keys(room.state.players).length,
          currentRound: room.state.currentRound,
          questionIndex: room.state.questionIndex,
        },
      });
    } catch (error) {
      console.error("[HTTP] Error recovering room:", error);
      res.status(500).json({
        success: false,
        error: "Error during room recovery",
      });
    }
  });

  app.get("/rooms/:roomId/can-recover", async (req: Request, res: Response) => {
    const { roomId } = req.params;
    
    const existingRoom = roomManager.getRoom(roomId);
    if (existingRoom) {
      return res.json({
        success: true,
        canRecover: true,
        reason: "Room exists in memory",
      });
    }
    
    const canRecover = await canRecoverRoom(roomId);
    res.json({
      success: true,
      canRecover,
      reason: canRecover ? "Room data found in Redis" : "No room data available",
    });
  });
}
