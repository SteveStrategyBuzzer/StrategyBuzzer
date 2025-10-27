<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SoloController;
use App\Http\Controllers\ProfileRegenController;
use App\Http\Controllers\QuestController;

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

/* ===== Quêtes ===== */
Route::middleware('auth')->group(function () {
    Route::get('/quests', [QuestController::class, 'index'])->name('quests.index');
    Route::get('/quests/{rarity}', [QuestController::class, 'getQuestsByRarity'])->name('quests.rarity');
    Route::get('/quests/progress/all', [QuestController::class, 'getUserProgress'])->name('quests.progress');
});

/* ===== Statistiques ===== */
Route::middleware('auth')->group(function () {
    Route::get('/stats', [App\Http\Controllers\StatisticsController::class, 'index'])->name('stats.index');
});

/* ===== Pièces d'or (Stripe) ===== */
Route::middleware('auth')->group(function () {
    Route::post('/coins/checkout', [App\Http\Controllers\CoinsController::class, 'checkout'])->name('coins.checkout');
    Route::get('/coins/success', [App\Http\Controllers\CoinsController::class, 'success'])->name('coins.success');
    Route::get('/coins/cancel', [App\Http\Controllers\CoinsController::class, 'cancel'])->name('coins.cancel');
});

/* ===== Mode Maître du Jeu (Stripe) ===== */
Route::middleware('auth')->group(function () {
    Route::post('/master/checkout', [BoutiqueController::class, 'masterCheckout'])->name('master.checkout');
    Route::get('/master/success', [BoutiqueController::class, 'masterSuccess'])->name('master.success');
    Route::get('/master/cancel', [BoutiqueController::class, 'masterCancel'])->name('master.cancel');
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

/* Email / Apple / Phone Authentication */
Route::get('/auth/email',             [AuthController::class, 'showEmailLogin'])->name('email.login');
Route::get('/auth/email/login',       [AuthController::class, 'showEmailLogin'])->name('email.login.form');
Route::post('/auth/email/login',      [AuthController::class, 'handleEmailLogin'])->name('email.login.submit');
Route::get('/auth/email/register',    [AuthController::class, 'showEmailRegister'])->name('email.register');
Route::post('/auth/email/register',   [AuthController::class, 'handleEmailRegister'])->name('email.register.submit');
Route::get('/auth/apple',             [AuthController::class, 'redirectToApple'])->name('auth.apple');
Route::get('/auth/apple/callback',    [AuthController::class, 'handleAppleCallback'])->name('apple.callback');
Route::get('/auth/phone',             [AuthController::class, 'showPhoneLogin'])->name('auth.phone');
Route::post('/auth/phone/login',      [AuthController::class, 'handlePhoneLogin'])->name('phone.login.submit');

/* Déconnexion */
Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/logout-cleanup');
})->name('logout');

Route::get('/logout-cleanup', function () {
    return view('logout_cleanup');
})->name('logout.cleanup');

/* ===== SOLO ===== */
Route::prefix('solo')->name('solo.')->group(function () {
    Route::get('/',        [SoloController::class, 'index'])->name('index');
    Route::post('/start',  [SoloController::class, 'start'])->name('start');
    
    // Fallback GET for /solo/start (happens when opening in new tab/external browser)
    Route::get('/start', fn() => redirect()->route('menu'));
    
    Route::get('/resume',  [SoloController::class, 'resume'])->name('resume');
    Route::get('/prepare', [SoloController::class, 'prepare'])->name('prepare');
    Route::get('/game',    [SoloController::class, 'game'])->name('game');
    Route::get('/timeout', [SoloController::class, 'timeout'])->name('timeout');
    Route::post('/buzz',   [SoloController::class, 'buzz'])->name('buzz');
    Route::match(['get', 'post'], '/answer', [SoloController::class, 'answer'])->name('answer');
    Route::post('/use-skill', [SoloController::class, 'useSkill'])->name('use-skill');
    Route::get('/next',    [SoloController::class, 'nextQuestion'])->name('next');
    Route::get('/round-result', [SoloController::class, 'roundResult'])->name('round-result');
    Route::get('/victory', [SoloController::class, 'victory'])->name('victory');
    Route::get('/defeat',  [SoloController::class, 'defeat'])->name('defeat');
});

/* ===== DUO ===== */
Route::get('/duo', fn() => redirect()->route('duo.lobby'))->name('duo');

Route::prefix('duo')->name('duo.')->middleware('auth')->group(function () {
    Route::get('/lobby', [App\Http\Controllers\DuoController::class, 'lobby'])->name('lobby');
    Route::post('/matchmaking/random', [App\Http\Controllers\DuoController::class, 'createMatch'])->name('matchmaking.random');
    Route::post('/invite', [App\Http\Controllers\DuoController::class, 'invitePlayer'])->name('invite');
    Route::get('/invitations', [App\Http\Controllers\DuoController::class, 'getInvitations'])->name('invitations');
    Route::get('/matchmaking', [App\Http\Controllers\DuoController::class, 'matchmaking'])->name('matchmaking');
    Route::get('/game/{match}', [App\Http\Controllers\DuoController::class, 'game'])->name('game');
    Route::get('/result/{match}', [App\Http\Controllers\DuoController::class, 'result'])->name('result');
    Route::get('/rankings', [App\Http\Controllers\DuoController::class, 'rankings'])->name('rankings');
});

/* ===== LIGUE INDIVIDUEL ===== */
Route::prefix('league/individual')->name('league.individual.')->middleware('auth')->group(function () {
    Route::get('/', [App\Http\Controllers\LeagueIndividualController::class, 'index'])->name('index');
    Route::get('/lobby', [App\Http\Controllers\LeagueIndividualController::class, 'index'])->name('lobby');
    Route::get('/game/{match}', function (App\Models\LeagueIndividualMatch $match) {
        $userId = Auth::id();
        if ($match->player1_id !== $userId && $match->player2_id !== $userId) {
            abort(403, 'Unauthorized access to this match');
        }
        return view('league_individual_game', compact('match'));
    })->name('game');
    Route::get('/results/{match}', function (App\Models\LeagueIndividualMatch $match) {
        $userId = Auth::id();
        if ($match->player1_id !== $userId && $match->player2_id !== $userId) {
            abort(403, 'Unauthorized access to this match');
        }
        $match->load(['player1', 'player2']);
        $gameState = $match->game_state;
        /** @var App\Models\User $user */
        $user = Auth::user();
        $pointsEarned = $match->player1_id == $user->id ? $match->player1_points_earned : $match->player2_points_earned;
        $stats = $user->leagueIndividualStat;
        $division = $user->playerDivisions()->where('mode', 'league_individual')->first();
        return view('league_individual_results', compact('match', 'gameState', 'pointsEarned', 'stats', 'division'));
    })->name('results');
    Route::get('/rankings', function () {
        /** @var App\Models\User $user */
        $user = Auth::user();
        $myStats = $user->leagueIndividualStat;
        $myDivision = $user->playerDivisions()->where('mode', 'league_individual')->first();
        return view('league_individual_rankings', compact('myStats', 'myDivision'));
    })->name('rankings');
});

/* ===== LIGUE ÉQUIPE ===== */
Route::prefix('league/team')->name('league.team.')->middleware('auth')->group(function () {
    Route::get('/management', [App\Http\Controllers\LeagueTeamController::class, 'showTeamManagement'])->name('management');
    Route::get('/lobby', [App\Http\Controllers\LeagueTeamController::class, 'showLobby'])->name('lobby');
    Route::get('/game/{match}', [App\Http\Controllers\LeagueTeamController::class, 'showGame'])->name('game');
    Route::get('/results/{match}', [App\Http\Controllers\LeagueTeamController::class, 'showResults'])->name('results');
});

/* ===== MAÎTRE DU JEU ===== */
Route::middleware('auth')->prefix('master')->name('master.')->group(function () {
    // Page d'accueil avec bouton "Créer un Quizz"
    Route::get('/', [App\Http\Controllers\MasterGameController::class, 'index'])->name('index');
    
    // Rejoindre une partie (depuis profil)
    Route::post('/join', [App\Http\Controllers\MasterGameController::class, 'join'])->name('join');
    
    // Créer un Quiz (après transition)
    Route::get('/create', [App\Http\Controllers\MasterGameController::class, 'create'])->name('create');
    Route::post('/store', [App\Http\Controllers\MasterGameController::class, 'store'])->name('store');
    
    // Flux de jeu
    Route::get('/{gameId}/compose', [App\Http\Controllers\MasterGameController::class, 'compose'])->name('compose');
    Route::get('/{gameId}/question/{questionNumber}/edit', [App\Http\Controllers\MasterGameController::class, 'editQuestion'])->name('question.edit');
    Route::post('/{gameId}/question/{questionNumber}/save', [App\Http\Controllers\MasterGameController::class, 'saveQuestion'])->name('question.save');
    Route::post('/{gameId}/question/{questionNumber}/regenerate', [App\Http\Controllers\MasterGameController::class, 'regenerateQuestion'])->name('question.regenerate');
    Route::get('/{gameId}/codes', [App\Http\Controllers\MasterGameController::class, 'codes'])->name('codes');
    Route::get('/{gameId}/lobby', [App\Http\Controllers\MasterGameController::class, 'lobby'])->name('lobby');
});

/* ===== LIGUE (page de sélection) ===== */
Route::get('/ligue', function () {
    return view('ligue');
})->middleware('auth')->name('ligue');

/* ===== RÈGLEMENTS ===== */
Route::view('/reglements', 'reglements')->name('reglements');

/* ===== QUÊTES & BADGES ===== */
Route::get('/quetes', [App\Http\Controllers\QuestesController::class, 'index'])->middleware('auth')->name('quetes');
Route::post('/quetes/claim/{questId}', [App\Http\Controllers\QuestesController::class, 'claim'])->middleware('auth')->name('quetes.claim');
Route::get('/badges', [App\Http\Controllers\BadgesController::class, 'index'])->middleware('auth')->name('badges');

// (Optionnel) Fallback 404 propre
// Route::fallback(fn() => response()->view('notfound', [], 404));
