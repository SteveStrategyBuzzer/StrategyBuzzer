<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;

class TestFirebaseController extends Controller
{
    public function testConnection()
    {
        $firebase = FirebaseService::getInstance();
        
        if (!$firebase->isInitialized()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Firebase is not initialized. Check logs for details.',
            ], 500);
        }
        
        $testGameId = 'test-game-' . time();
        $testData = [
            'game_id' => $testGameId,
            'mode' => 'duo',
            'status' => 'waiting',
            'players' => [
                ['id' => 1, 'name' => 'Player 1', 'score' => 0],
                ['id' => 2, 'name' => 'Player 2', 'score' => 0],
            ],
            'created_at' => now()->toIso8601String(),
        ];
        
        $created = $firebase->createGameSession($testGameId, $testData);
        
        if (!$created) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create test game session',
            ], 500);
        }
        
        $retrieved = $firebase->getGameState($testGameId);
        
        $firebase->deleteGameSession($testGameId);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Firebase connection successful!',
            'test_data_created' => $testData,
            'test_data_retrieved' => $retrieved,
            'firebase_initialized' => true,
        ]);
    }
    
    public function testBuzz()
    {
        $firebase = FirebaseService::getInstance();
        
        if (!$firebase->isInitialized()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Firebase not initialized',
            ], 500);
        }
        
        $testGameId = 'buzz-test-' . time();
        $firebase->createGameSession($testGameId, ['status' => 'active']);
        
        $firebase->recordBuzz($testGameId, 1, microtime(true));
        sleep(1);
        $firebase->recordBuzz($testGameId, 2, microtime(true));
        
        $buzzes = $firebase->getBuzzes($testGameId);
        
        $firebase->deleteGameSession($testGameId);
        
        return response()->json([
            'status' => 'success',
            'buzzes_recorded' => count($buzzes),
            'buzzes' => $buzzes,
        ]);
    }
}
