import express from "express";
import { createServer } from "http";
import { Server as SocketIOServer } from "socket.io";
import { createAdapter } from "@socket.io/redis-adapter";
import cors from "cors";
import { RoomManager } from "./services/RoomManager.js";
import { GameOrchestrator } from "./services/GameOrchestrator.js";
import { setupSocketHandlers } from "./ws/handlers.js";
import { setupHttpRoutes } from "./http/routes.js";
import { verifyJWT } from "./middleware/auth.js";
import { redisPub, redisSub, ping as redisPing } from "./services/RedisService.js";

const PORT = Number(process.env.PORT);
if (!PORT || Number.isNaN(PORT)) {
  console.error("[FATAL] Missing PORT env. Refusing to start.");
  process.exit(1);
}
const LARAVEL_ORIGIN = process.env.LARAVEL_ORIGIN || "http://localhost:5000";

const app = express();
app.use(cors({
  origin: true,
  methods: ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
  credentials: true,
  allowedHeaders: ["Content-Type", "Authorization", "X-Requested-With", "X-Internal-Bot"],
}));
app.use(express.json());

const httpServer = createServer(app);

const io = new SocketIOServer(httpServer, {
  cors: {
    origin: true,
    methods: ["GET", "POST"],
    credentials: true,
  },
  pingTimeout: 60000,
  pingInterval: 25000,
});

io.adapter(createAdapter(redisPub, redisSub));

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

app.get("/health/redis", async (_req, res) => {
  const healthy = await redisPing();
  if (healthy) {
    res.json({ status: "ok", redis: "connected" });
  } else {
    res.status(503).json({ status: "error", redis: "disconnected" });
  }
});

app.get("/", (_req, res) => res.status(200).send("ok"));

httpServer.listen(PORT, () => {
  console.log(`[GameServer] Running on port ${PORT}`);
  console.log(`[GameServer] WebSocket ready at ws://0.0.0.0:${PORT}`);
  console.log(`[GameServer] HTTP API ready at http://0.0.0.0:${PORT}`);
  console.log(`[GameServer] CORS configured for Laravel backend at ${LARAVEL_ORIGIN}`);
  console.log(`[GameServer] Redis adapter configured for multi-instance sync`);
});

process.on("uncaughtException", (error) => {
  console.error("[GameServer] Uncaught exception:", error);
});

process.on("unhandledRejection", (reason, promise) => {
  console.error("[GameServer] Unhandled rejection at:", promise, "reason:", reason);
});
