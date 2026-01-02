import type { RoomManager } from "./RoomManager.js";

type PrefetchConfig = {
  mode: string;
  theme: string;
  language: string;
};

type PrefetchedQuestion = {
  questionIndex: number;
  question: unknown;
  fetchedAt: number;
};

const LARAVEL_ENDPOINT = "http://localhost:5000/api/game-server/prefetch-question";

const prefetchedQuestions: Map<string, PrefetchedQuestion[]> = new Map();

export async function prefetchNextQuestion(
  roomId: string,
  questionIndex: number,
  config: PrefetchConfig
): Promise<void> {
  try {
    console.log(`[QuestionService] Prefetching question ${questionIndex} for room ${roomId}`);

    const response = await fetch(LARAVEL_ENDPOINT, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
      },
      body: JSON.stringify({
        room_id: roomId,
        question_index: questionIndex,
        mode: config.mode,
        theme: config.theme,
        language: config.language,
      }),
    });

    if (!response.ok) {
      console.error(`[QuestionService] Prefetch failed with status ${response.status}`);
      return;
    }

    const data = await response.json();
    
    const roomQuestions = prefetchedQuestions.get(roomId) || [];
    roomQuestions.push({
      questionIndex,
      question: data,
      fetchedAt: Date.now(),
    });
    prefetchedQuestions.set(roomId, roomQuestions);

    console.log(`[QuestionService] Successfully prefetched question ${questionIndex} for room ${roomId}`);
  } catch (error) {
    console.error("[QuestionService] Error prefetching question:", error);
  }
}

export function getPrefetchedQuestion(roomId: string, questionIndex: number): unknown | null {
  const roomQuestions = prefetchedQuestions.get(roomId);
  if (!roomQuestions) return null;

  const found = roomQuestions.find(q => q.questionIndex === questionIndex);
  return found?.question || null;
}

export function clearPrefetchedQuestions(roomId: string): void {
  prefetchedQuestions.delete(roomId);
  console.log(`[QuestionService] Cleared prefetched questions for room ${roomId}`);
}

export function storePrefetchedQuestionInRoom(
  roomManager: RoomManager,
  roomId: string,
  questionIndex: number,
  question: unknown
): void {
  const room = roomManager.getRoom(roomId);
  if (room) {
    if (!room.state.prefetchedQuestions) {
      (room.state as any).prefetchedQuestions = new Map();
    }
    (room.state as any).prefetchedQuestions.set(questionIndex, question);
  }
}
