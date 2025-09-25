<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PlayController;

Route::get('/solo/next',  [PlayController::class, 'next']);
Route::post('/solo/answer', [PlayController::class, 'answer']);
Route::get('/status', [GameController::class, 'status']);
Route::get('/quests', [GameController::class, 'quests']);
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
