<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SoloController;
use App\Http\Controllers\ProfileRegenController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| - /avatars et /avatars/strategic : pages de sélection (GET)
| - /avatar/select (et alias /avatars/select) : sélectionner un avatar (POST)
| - /avatar/buy, /avatar/unlock : actions annexes
| - /profile : page profil (retour après sélection)
| - /boutique : boutique
*/

/* ===== Accueil ===== */
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('menu')
        : view('start');
})->name('start');

/* ===== Profil ===== */
Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');

/* ===== Avatars (pages de catalogue) ===== */
Route::get('/avatars',             [AvatarController::class, 'index'])->name('avatars');
Route::get('/avatars/strategic',   [AvatarController::class, 'strategic'])->name('avatars.strategic');

/* (Alias facultatif en singulier si tu as d’anciens liens /avatar) */
Route::get('/avatar',              [AvatarController::class, 'index'])->name('avatar');

/* ===== Avatars (actions) ===== */
Route::prefix('/avatar')->name('avatar.')->group(function () {
    Route::post('/select', [AvatarController::class, 'select'])->name('select'); // choisir un avatar
    Route::post('/buy',    [AvatarController::class, 'buy'])->name('buy');       // acheter (coins)

    // compat : certains projets ont unlockByQuest; sinon 'unlock'
    $unlockMethod = method_exists(AvatarController::class, 'unlock')
        ? 'unlock'
        : (method_exists(AvatarController::class, 'unlockByQuest') ? 'unlockByQuest' : null);

    if ($unlockMethod) {
        Route::post('/unlock', [AvatarController::class, $unlockMethod])->name('unlock');
    }
});

/* (Alias POST en pluriel si une vue utilise route('avatars.select')) */
Route::post('/avatars/select', [AvatarController::class, 'select'])->name('avatars.select');

/* ===== Boutique ===== */
Route::get('/boutique',             [BoutiqueController::class, 'index'])->name('boutique');
Route::post('/boutique/purchase',   [BoutiqueController::class, 'purchase'])->name('boutique.purchase');

/* Aliases boutique (anciens liens) */
Route::get('/avatar/boutique', fn () => redirect()->route('boutique'))->name('avatar.boutique');
Route::get('/shop',            fn () => redirect()->route('boutique'))->name('shop.alias');

/* ===== Pièces d'or (Stripe) ===== */
Route::middleware('auth')->group(function () {
    Route::post('/coins/checkout', [App\Http\Controllers\CoinsController::class, 'checkout'])->name('coins.checkout');
    Route::get('/coins/success', [App\Http\Controllers\CoinsController::class, 'success'])->name('coins.success');
    Route::get('/coins/cancel', [App\Http\Controllers\CoinsController::class, 'cancel'])->name('coins.cancel');
});

/* Stripe Webhook (no CSRF) */
Route::post('/stripe/webhook', [App\Http\Controllers\StripeWebhookController::class, 'handle'])->name('stripe.webhook');

/* ===== Menu / Auth ===== */
Route::view('/menu', 'menu')->name('menu');
Route::view('/login', 'login')->name('login');
Route::get('/connexion', fn() => redirect('/login'))->name('connexion');
Route::middleware('auth')->group(function () {
Route::post('/profile/regen', ProfileRegenController::class)->name('profile.regen');
});
/* OAuth Google / Facebook */
Route::get('/auth/google',            [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback',   [AuthController::class, 'handleGoogleCallback'])->name('google.callback');
Route::get('/auth/facebook',          [AuthController::class, 'redirectToFacebook'])->name('auth.facebook');
Route::get('/auth/facebook/callback', [AuthController::class, 'handleFacebookCallback'])->name('facebook.callback');

/* ===== SOLO ===== */
Route::prefix('solo')->name('solo.')->group(function () {
    Route::get('/',        [SoloController::class, 'index'])->name('index');
    Route::post('/start',  [SoloController::class, 'start'])->name('start');
    Route::get('/resume',  [SoloController::class, 'resume'])->name('resume');
    Route::get('/game',    [SoloController::class, 'game'])->name('game');
    Route::post('/answer', [SoloController::class, 'answer'])->name('answer');
    Route::get('/stat',    [SoloController::class, 'stat'])->name('stat');
});

/* ===== Quêtes (si les vues existent) ===== */
if (view()->exists('quests'))  Route::view('/quests', 'quests')->name('quests');
if (view()->exists('quetes'))  Route::view('/quetes', 'quetes')->name('quetes');

// (Optionnel) Fallback 404 propre
// Route::fallback(fn() => response()->view('notfound', [], 404));
