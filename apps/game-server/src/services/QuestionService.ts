import type { Question } from "../../../../packages/shared/src/types.js";

const LARAVEL_BASE = "http://localhost:5000/api/game-server";

export interface QuestionPipelineConfig {
  roomId: string;
  theme: string;
  niveau: number;
  language: string;
  maxRounds: number;
}

interface InitPipelineResponse {
  success: boolean;
  firstQuestion?: Question;
  totalQuestions?: number;
  error?: string;
}

interface NextBlockResponse {
  questions?: Question[];
  available?: number;
  totalNeeded?: number;
}

interface PipelineStatusResponse {
  available?: number;
  totalNeeded?: number;
  ready?: boolean;
}

export async function initQuestionPipeline(config: QuestionPipelineConfig): Promise<{
  success: boolean;
  firstQuestion?: Question;
  totalQuestions?: number;
  error?: string;
}> {
  try {
    console.log(`[QuestionService] Initializing pipeline for room ${config.roomId}`);

    const response = await fetch(`${LARAVEL_BASE}/init`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
      },
      body: JSON.stringify({
        roomId: config.roomId,
        theme: config.theme,
        niveau: config.niveau,
        language: config.language,
        maxRounds: config.maxRounds,
      }),
    });

    if (!response.ok) {
      const errorText = await response.text();
      console.error(`[QuestionService] Init failed with status ${response.status}: ${errorText}`);
      return {
        success: false,
        error: `API error: ${response.status}`,
      };
    }

    const data = await response.json() as InitPipelineResponse;

    if (!data.success) {
      return {
        success: false,
        error: data.error || "Unknown error",
      };
    }

    console.log(`[QuestionService] Pipeline initialized, total questions: ${data.totalQuestions}`);

    return {
      success: true,
      firstQuestion: data.firstQuestion,
      totalQuestions: data.totalQuestions,
    };
  } catch (error) {
    console.error("[QuestionService] Error initializing pipeline:", error);
    return {
      success: false,
      error: error instanceof Error ? error.message : "Unknown error",
    };
  }
}

export async function fetchNextBlock(roomId: string, count = 4): Promise<{
  questions: Question[];
  available: number;
  totalNeeded: number;
}> {
  try {
    console.log(`[QuestionService] Fetching next block of ${count} questions for room ${roomId}`);

    const response = await fetch(`${LARAVEL_BASE}/next-block/${roomId}?count=${count}`, {
      method: "GET",
      headers: {
        "Accept": "application/json",
      },
    });

    if (!response.ok) {
      console.error(`[QuestionService] Fetch next block failed with status ${response.status}`);
      return {
        questions: [],
        available: 0,
        totalNeeded: 0,
      };
    }

    const data = await response.json() as NextBlockResponse;

    console.log(`[QuestionService] Fetched ${data.questions?.length || 0} questions, available: ${data.available}`);

    return {
      questions: data.questions || [],
      available: data.available || 0,
      totalNeeded: data.totalNeeded || 0,
    };
  } catch (error) {
    console.error("[QuestionService] Error fetching next block:", error);
    return {
      questions: [],
      available: 0,
      totalNeeded: 0,
    };
  }
}

export async function getPipelineStatus(roomId: string): Promise<{
  available: number;
  totalNeeded: number;
  ready: boolean;
}> {
  try {
    const response = await fetch(`${LARAVEL_BASE}/status/${roomId}`, {
      method: "GET",
      headers: {
        "Accept": "application/json",
      },
    });

    if (!response.ok) {
      console.error(`[QuestionService] Get status failed with status ${response.status}`);
      return {
        available: 0,
        totalNeeded: 0,
        ready: false,
      };
    }

    const data = await response.json() as PipelineStatusResponse;

    return {
      available: data.available || 0,
      totalNeeded: data.totalNeeded || 0,
      ready: data.ready || false,
    };
  } catch (error) {
    console.error("[QuestionService] Error getting pipeline status:", error);
    return {
      available: 0,
      totalNeeded: 0,
      ready: false,
    };
  }
}

export async function cleanupPipeline(roomId: string): Promise<void> {
  try {
    console.log(`[QuestionService] Cleaning up pipeline for room ${roomId}`);

    const response = await fetch(`${LARAVEL_BASE}/cleanup/${roomId}`, {
      method: "POST",
      headers: {
        "Accept": "application/json",
      },
    });

    if (!response.ok) {
      console.error(`[QuestionService] Cleanup failed with status ${response.status}`);
      return;
    }

    console.log(`[QuestionService] Pipeline cleanup complete for room ${roomId}`);
  } catch (error) {
    console.error("[QuestionService] Error cleaning up pipeline:", error);
  }
}
