<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PlayController;
use App\Http\Controllers\DuoController;
use App\Http\Controllers\PlayerGroupController;

Route::get('/solo/next',  [PlayController::class, 'next']);
Route::match(['get', 'post'], '/solo/answer', [PlayController::class, 'answer']);
Route::get('/status', [GameController::class, 'status']);
Route::get('/quests', [GameController::class, 'quests']);
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->prefix('contacts')->group(function () {
    Route::get('/', [DuoController::class, 'getContacts']);
    Route::delete('/{contactId}', [DuoController::class, 'deleteContact']);
    Route::post('/add', [DuoController::class, 'addContact']);
    Route::get('/groups', [PlayerGroupController::class, 'index']);
    Route::post('/groups', [PlayerGroupController::class, 'store']);
    Route::get('/groups/{groupId}', [PlayerGroupController::class, 'show']);
    Route::put('/groups/{groupId}', [PlayerGroupController::class, 'update']);
    Route::delete('/groups/{groupId}', [PlayerGroupController::class, 'destroy']);
    Route::post('/groups/{groupId}/members', [PlayerGroupController::class, 'addMembers']);
    Route::delete('/groups/{groupId}/members', [PlayerGroupController::class, 'removeMembers']);
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

Route::middleware('auth:sanctum')->get('/player/stats', function (Request $request) {
    $user = $request->user();
    $duoStats = \App\Models\MatchPerformance::getLast10Stats($user->id, 'duo');
    $soloStats = \App\Models\MatchPerformance::getLast10Stats($user->id, 'solo');
    
    $duoDivision = \App\Models\PlayerDivision::where('user_id', $user->id)
        ->where('mode', 'duo')
        ->first();
    
    $efficiency = $duoStats['global_efficiency'] ?? 0;
    $wins = $duoStats['wins'] ?? 0;
    $losses = $duoStats['losses'] ?? 0;
    
    if (($duoStats['count'] ?? 0) == 0) {
        if ($duoDivision && $duoDivision->initial_efficiency > 0) {
            $efficiency = $duoDivision->initial_efficiency;
        } else {
            $efficiency = $soloStats['global_efficiency'] ?? 0;
        }
        $wins = $soloStats['wins'] ?? 0;
        $losses = $soloStats['losses'] ?? 0;
    }
    
    $odds = 1.0 + ($efficiency / 100);
    
    return response()->json([
        'global_efficiency' => round($efficiency, 1),
        'wins' => $wins,
        'losses' => $losses,
        'count' => ($duoStats['count'] ?? 0) + ($soloStats['count'] ?? 0),
        'odds' => round($odds, 2),
        'division' => $duoDivision ? $duoDivision->division : null,
    ]);
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
