<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PlayController;
use App\Http\Controllers\DuoController;

Route::get('/solo/next',  [PlayController::class, 'next']);
Route::post('/solo/answer', [PlayController::class, 'answer']);
Route::get('/status', [GameController::class, 'status']);
Route::get('/quests', [GameController::class, 'quests']);
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->prefix('duo')->group(function () {
    Route::get('/', [DuoController::class, 'index']);
    Route::post('/invite', [DuoController::class, 'invitePlayer']);
    Route::post('/find-random', [DuoController::class, 'findRandomOpponent']);
    Route::post('/match/{match}/accept', [DuoController::class, 'acceptMatch']);
    Route::post('/match/{match}/cancel', [DuoController::class, 'cancelMatch']);
    Route::post('/match/{match}/buzz', [DuoController::class, 'buzz']);
    Route::post('/match/{match}/answer', [DuoController::class, 'submitAnswer']);
    Route::post('/match/{match}/finish', [DuoController::class, 'finishMatch']);
    Route::get('/match/{match}', [DuoController::class, 'getMatch']);
    Route::get('/rankings', [DuoController::class, 'getRankings']);
    Route::get('/my-stats', [DuoController::class, 'getMyStats']);
});
