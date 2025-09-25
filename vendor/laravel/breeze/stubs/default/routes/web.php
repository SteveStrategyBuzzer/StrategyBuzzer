<?php

use App\Http\Controllers\ProfileeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profilee', [ProfileeController::class, 'edit'])->name('profilee.edit');
    Route::patch('/profilee', [ProfileeController::class, 'update'])->name('profilee.update');
    Route::delete('/profilee', [ProfileeController::class, 'destroy'])->name('profilee.destroy');
});

require __DIR__.'/auth.php';
