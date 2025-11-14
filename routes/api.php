<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PlayController;
use App\Http\Controllers\DuoController;

Route::get('/solo/next',  [PlayController::class, 'next']);
Route::match(['get', 'post'], '/solo/answer', [PlayController::class, 'answer']);
Route::get('/status', [GameController::class, 'status']);
Route::get('/quests', [GameController::class, 'quests']);
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->prefix('duo')->group(function () {
    Route::get('/', [DuoController::class, 'index']);
    Route::post('/invite', [DuoController::class, 'invitePlayer']);
    Route::post('/find-random', [DuoController::class, 'findRandomOpponent']);
    Route::get('/contacts', [DuoController::class, 'getContacts']);
    Route::post('/match/{match}/accept', [DuoController::class, 'acceptMatch']);
    Route::post('/match/{match}/cancel', [DuoController::class, 'cancelMatch']);
    Route::post('/match/{match}/buzz', [DuoController::class, 'buzz']);
    Route::post('/match/{match}/answer', [DuoController::class, 'submitAnswer']);
    Route::post('/match/{match}/finish', [DuoController::class, 'finishMatch']);
    Route::get('/match/{match}', [DuoController::class, 'getMatch']);
    Route::get('/match/{match}/sync', [DuoController::class, 'syncGameState']);
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
    Route::get('/match/{match}/sync', [App\Http\Controllers\LeagueIndividualController::class, 'syncGameState']);
    Route::get('/rankings', [App\Http\Controllers\LeagueIndividualController::class, 'getRankings']);
    Route::get('/my-stats', [App\Http\Controllers\LeagueIndividualController::class, 'getMyStats']);
});

Route::middleware('auth:sanctum')->prefix('league/team')->group(function () {
    Route::post('/create-team', [App\Http\Controllers\LeagueTeamController::class, 'createTeam']);
    Route::post('/invite-player', [App\Http\Controllers\LeagueTeamController::class, 'invitePlayer']);
    Route::post('/invitation/{invitation}/accept', [App\Http\Controllers\LeagueTeamController::class, 'acceptInvitation']);
    Route::post('/invitation/{invitation}/decline', [App\Http\Controllers\LeagueTeamController::class, 'declineInvitation']);
    Route::post('/leave-team', [App\Http\Controllers\LeagueTeamController::class, 'leaveTeam']);
    Route::post('/kick-member', [App\Http\Controllers\LeagueTeamController::class, 'kickMember']);
    Route::post('/start-matchmaking', [App\Http\Controllers\LeagueTeamController::class, 'startMatchmaking']);
    Route::get('/match/{match}/question', [App\Http\Controllers\LeagueTeamController::class, 'getQuestion']);
    Route::post('/match/{match}/buzz', [App\Http\Controllers\LeagueTeamController::class, 'buzz']);
    Route::post('/match/{match}/submit-answer', [App\Http\Controllers\LeagueTeamController::class, 'submitAnswer']);
    Route::get('/match/{match}/sync', [App\Http\Controllers\LeagueTeamController::class, 'syncGameState']);
    Route::get('/rankings/{division}', [App\Http\Controllers\LeagueTeamController::class, 'getRankings']);
});

Route::middleware('auth:sanctum')->prefix('master')->group(function () {
    Route::post('/game/{gameId}/validate-quiz', [App\Http\Controllers\MasterGameController::class, 'validateQuiz']);
    Route::post('/game/{gameId}/join', [App\Http\Controllers\MasterGameController::class, 'joinGame']);
    Route::post('/game/{gameId}/leave', [App\Http\Controllers\MasterGameController::class, 'leaveGame']);
    Route::post('/game/{gameId}/start', [App\Http\Controllers\MasterGameController::class, 'startGame']);
    Route::post('/game/{gameId}/next-question', [App\Http\Controllers\MasterGameController::class, 'nextQuestion']);
    Route::post('/game/{gameId}/answer', [App\Http\Controllers\MasterGameController::class, 'submitAnswer']);
    Route::post('/game/{gameId}/finish', [App\Http\Controllers\MasterGameController::class, 'finishGame']);
    Route::post('/game/{gameId}/cancel', [App\Http\Controllers\MasterGameController::class, 'cancelGame']);
    Route::get('/game/{gameId}/sync', [App\Http\Controllers\MasterGameController::class, 'syncGameState']);
});
