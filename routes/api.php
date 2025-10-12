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

Route::middleware('auth:sanctum')->prefix('league/individual')->group(function () {
    Route::get('/', [App\Http\Controllers\LeagueIndividualController::class, 'index']);
    Route::post('/initialize', [App\Http\Controllers\LeagueIndividualController::class, 'initialize']);
    Route::get('/check-initialized', [App\Http\Controllers\LeagueIndividualController::class, 'checkInitialized']);
    Route::post('/create-match', [App\Http\Controllers\LeagueIndividualController::class, 'createMatch']);
    Route::get('/match/{match}/game-state', [App\Http\Controllers\LeagueIndividualController::class, 'getGameState']);
    Route::post('/match/{match}/buzz', [App\Http\Controllers\LeagueIndividualController::class, 'buzz']);
    Route::post('/match/{match}/submit-answer', [App\Http\Controllers\LeagueIndividualController::class, 'submitAnswer']);
    Route::post('/match/{match}/finish', [App\Http\Controllers\LeagueIndividualController::class, 'finishMatch']);
    Route::get('/rankings', [App\Http\Controllers\LeagueIndividualController::class, 'getRankings']);
    Route::get('/my-stats', [App\Http\Controllers\LeagueIndividualController::class, 'getMyStats']);
});
