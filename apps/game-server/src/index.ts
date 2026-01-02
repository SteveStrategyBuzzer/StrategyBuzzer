import express from "express";
import { createServer } from "http";
import { Server as SocketIOServer } from "socket.io";
import cors from "cors";
import { RoomManager } from "./services/RoomManager.js";
import { GameOrchestrator } from "./services/GameOrchestrator.js";
import { setupSocketHandlers } from "./ws/handlers.js";
import { setupHttpRoutes } from "./http/routes.js";
import { verifyJWT } from "./middleware/auth.js";

const PORT = process.env.GAME_SERVER_PORT || 3001;
const LARAVEL_ORIGIN = process.env.LARAVEL_ORIGIN || "http://localhost:5000";

const app = express();
app.use(cors({
  origin: [LARAVEL_ORIGIN, "http://localhost:5000", "http://0.0.0.0:5000"],
  methods: ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
  credentials: true,
  allowedHeaders: ["Content-Type", "Authorization", "X-Requested-With"],
}));
app.use(express.json());

const httpServer = createServer(app);

const io = new SocketIOServer(httpServer, {
  cors: {
    origin: [LARAVEL_ORIGIN, "http://localhost:5000", "http://0.0.0.0:5000", "*"],
    methods: ["GET", "POST"],
    credentials: true,
  },
  pingTimeout: 60000,
  pingInterval: 25000,
});

io.use((socket, next) => {
  const token = socket.handshake.auth?.token || socket.handshake.query?.token;
  
  if (token && typeof token === "string") {
    const payload = verifyJWT(token);
    if (payload) {
      (socket as any).playerData = payload;
      console.log(`[Auth] Socket authenticated for player: ${payload.playerName}`);
      next();
    } else {
      console.log(`[Auth] Invalid token provided, rejecting connection`);
      socket.disconnect(true);
      next(new Error("Invalid or expired token"));
    }
  } else {
    console.log(`[Auth] No token provided, rejecting connection`);
    socket.disconnect(true);
    next(new Error("Authentication required"));
  }
});

const roomManager = new RoomManager();
const gameOrchestrator = new GameOrchestrator(io, roomManager);

setupHttpRoutes(app, roomManager, gameOrchestrator);

setupSocketHandlers(io, roomManager, gameOrchestrator);

httpServer.listen(PORT, () => {
  console.log(`[GameServer] Running on port ${PORT}`);
  console.log(`[GameServer] WebSocket ready at ws://0.0.0.0:${PORT}`);
  console.log(`[GameServer] HTTP API ready at http://0.0.0.0:${PORT}`);
  console.log(`[GameServer] CORS configured for Laravel backend at ${LARAVEL_ORIGIN}`);
});

process.on("uncaughtException", (error) => {
  console.error("[GameServer] Uncaught exception:", error);
});

process.on("unhandledRejection", (reason, promise) => {
  console.error("[GameServer] Unhandled rejection at:", promise, "reason:", reason);
});
