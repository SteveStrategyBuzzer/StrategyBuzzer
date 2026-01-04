<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GameServerQuestionPipeline;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GameServerController extends Controller
{
    private GameServerQuestionPipeline $pipeline;

    public function __construct()
    {
        $this->pipeline = new GameServerQuestionPipeline();
    }

    public function init(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'roomId' => 'required|string',
            'theme' => 'required|string',
            'niveau' => 'required|integer|min:1|max:10',
            'language' => 'required|string',
            'maxRounds' => 'required|integer|min:1',
        ]);

        $firstQuestion = $this->pipeline->initMatch(
            $validated['roomId'],
            $validated['theme'],
            $validated['niveau'],
            $validated['language'],
            $validated['maxRounds']
        );

        if (!$firstQuestion) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to initialize match',
            ], 500);
        }

        $totalQuestions = $this->pipeline->getTotalNeeded($validated['maxRounds']);

        return response()->json([
            'success' => true,
            'firstQuestion' => $firstQuestion,
            'totalQuestions' => $totalQuestions,
        ]);
    }

    public function nextBlock(Request $request, string $roomId): JsonResponse
    {
        $count = (int) $request->query('count', 4);
        
        $questions = $this->pipeline->getNextQuestions($roomId, $count);
        $config = $this->pipeline->getMatchConfig($roomId);
        
        if (!$config) {
            return response()->json([
                'success' => false,
                'error' => 'Room not found',
            ], 404);
        }

        return response()->json([
            'questions' => $questions,
            'available' => $this->pipeline->getQuestionCount($roomId),
            'totalNeeded' => $config['total_needed'],
        ]);
    }

    public function status(string $roomId): JsonResponse
    {
        $config = $this->pipeline->getMatchConfig($roomId);
        
        if (!$config) {
            return response()->json([
                'success' => false,
                'error' => 'Room not found',
            ], 404);
        }

        $available = $this->pipeline->getQuestionCount($roomId);
        $totalNeeded = $config['total_needed'];

        return response()->json([
            'available' => $available,
            'totalNeeded' => $totalNeeded,
            'ready' => $available >= $totalNeeded,
        ]);
    }

    public function cleanup(string $roomId): JsonResponse
    {
        $this->pipeline->cleanup($roomId);

        return response()->json([
            'success' => true,
        ]);
    }
}
