import express from "express";
import { createServer } from "http";
import { Server as SocketIOServer } from "socket.io";
import cors from "cors";
import { RoomManager } from "./services/RoomManager.js";
import { setupSocketHandlers } from "./ws/handlers.js";
import { setupHttpRoutes } from "./http/routes.js";

const PORT = process.env.GAME_SERVER_PORT || 3001;

const app = express();
app.use(cors());
app.use(express.json());

const httpServer = createServer(app);

const io = new SocketIOServer(httpServer, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"],
  },
  pingTimeout: 60000,
  pingInterval: 25000,
});

const roomManager = new RoomManager();

setupHttpRoutes(app, roomManager);

setupSocketHandlers(io, roomManager);

httpServer.listen(PORT, () => {
  console.log(`[GameServer] Running on port ${PORT}`);
  console.log(`[GameServer] WebSocket ready at ws://0.0.0.0:${PORT}`);
  console.log(`[GameServer] HTTP API ready at http://0.0.0.0:${PORT}`);
});

process.on("uncaughtException", (error) => {
  console.error("[GameServer] Uncaught exception:", error);
});

process.on("unhandledRejection", (reason, promise) => {
  console.error("[GameServer] Unhandled rejection at:", promise, "reason:", reason);
});
