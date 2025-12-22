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
use App\Http\Controllers\LobbyController;
use App\Http\Controllers\MenuController;

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
Route::get('/boutique/buzzers/{subcategory}', [BoutiqueController::class, 'buzzerSubcategory'])->name('boutique.buzzer.subcategory');
Route::get('/boutique/{category}',  [BoutiqueController::class, 'category'])->name('boutique.category');
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

/* ===== Modes de Jeux (Stripe) ===== */
Route::middleware('auth')->group(function () {
    Route::post('/modes/checkout/{mode}', [BoutiqueController::class, 'modeCheckout'])->name('modes.checkout');
    Route::get('/modes/success', [BoutiqueController::class, 'modeSuccess'])->name('modes.success');
    Route::get('/modes/cancel', [BoutiqueController::class, 'modeCancel'])->name('modes.cancel');
    Route::post('/master/checkout', [BoutiqueController::class, 'masterCheckout'])->name('master.checkout');
    Route::get('/master/success', [BoutiqueController::class, 'masterSuccess'])->name('master.success');
    Route::get('/master/cancel', [BoutiqueController::class, 'masterCancel'])->name('master.cancel');
});

/* Stripe Webhook (no CSRF) */
Route::post('/stripe/webhook', [App\Http\Controllers\StripeWebhookController::class, 'handle'])->name('stripe.webhook');

/* ===== Menu / Auth ===== */
Route::view('/menu', 'menu')->name('menu');
Route::get('/api/notifications', [MenuController::class, 'notifications'])->name('api.notifications')->middleware('auth');
Route::get('/home', fn() => redirect('/menu'))->name('home');
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
Route::prefix('solo')->name('solo.')->middleware('auth')->group(function () {
    Route::get('/',        [SoloController::class, 'index'])->name('index');
    Route::post('/start',  [SoloController::class, 'start'])->name('start');
    
    // Fallback GET for /solo/start (happens when opening in new tab/external browser)
    Route::get('/start', fn() => redirect()->route('menu'));
    
    Route::get('/opponents', [SoloController::class, 'opponents'])->name('opponents');
    Route::post('/select-opponent/{level}', [SoloController::class, 'selectOpponent'])->name('select-opponent');
    
    Route::get('/resume',  [SoloController::class, 'resume'])->name('resume');
    Route::get('/prepare', [SoloController::class, 'prepare'])->name('prepare');
    Route::get('/preparation', [SoloController::class, 'prepare'])->name('preparation');
    Route::get('/game',    [SoloController::class, 'game'])->name('game');
    Route::get('/timeout', [SoloController::class, 'timeout'])->name('timeout');
    Route::post('/buzz',   [SoloController::class, 'buzz'])->name('buzz');
    Route::match(['get', 'post'], '/answer', [SoloController::class, 'answer'])->name('answer');
    Route::post('/use-skill', [SoloController::class, 'useSkill'])->name('use-skill');
    Route::post('/cancel-error', [SoloController::class, 'cancelError'])->name('cancel-error');
    Route::get('/bonus-question', [SoloController::class, 'bonusQuestion'])->name('bonus-question');
    Route::post('/answer-bonus', [SoloController::class, 'answerBonus'])->name('answer-bonus');
    Route::get('/next',    [SoloController::class, 'nextQuestion'])->name('next');
    Route::post('/fetch-question', [SoloController::class, 'fetchQuestionApi'])->name('fetch-question');
    Route::post('/submit-answer', [SoloController::class, 'submitAnswerApi'])->name('submit-answer');
    Route::post('/generate-batch', [SoloController::class, 'generateBatch'])->name('generate-batch');
    Route::post('/generate-block', [SoloController::class, 'generateBlock'])->name('generate-block'); // NOUVEAU: génération progressive
    Route::post('/generate-queue', [SoloController::class, 'generateQueue'])->name('generate-queue');
    Route::get('/round-result', [SoloController::class, 'roundResult'])->name('round-result');
    Route::get('/victory', [SoloController::class, 'victory'])->name('victory');
    Route::get('/defeat',  [SoloController::class, 'defeat'])->name('defeat');
    
    // JEU DÉCISIF (Tiebreaker)
    Route::get('/tiebreaker-choice', [SoloController::class, 'tiebreakerChoice'])->name('tiebreaker-choice');
    Route::get('/tiebreaker-bonus', [SoloController::class, 'tiebreakerBonus'])->name('tiebreaker-bonus');
    Route::post('/tiebreaker-bonus-answer', [SoloController::class, 'tiebreakerBonusAnswer'])->name('tiebreaker-bonus-answer');
    Route::get('/tiebreaker-efficiency', [SoloController::class, 'tiebreakerEfficiency'])->name('tiebreaker-efficiency');
    Route::get('/tiebreaker-sudden-death', [SoloController::class, 'tiebreakerSuddenDeath'])->name('tiebreaker-sudden-death');
    Route::post('/tiebreaker-sudden-death-answer', [SoloController::class, 'tiebreakerSuddenDeathAnswer'])->name('tiebreaker-sudden-death-answer');
});

/* ===== LOBBY (Salon d'attente multijoueur) ===== */
Route::prefix('lobby')->name('lobby.')->middleware('auth')->group(function () {
    Route::post('/create', [LobbyController::class, 'create'])->name('create');
    Route::post('/join', [LobbyController::class, 'join'])->name('join');
    Route::get('/player-stats/{playerId}', [LobbyController::class, 'getPlayerStats'])->name('player-stats');
    Route::get('/{code}', [LobbyController::class, 'show'])->name('show');
    Route::get('/{code}/state', [LobbyController::class, 'getState'])->name('state');
    Route::post('/{code}/ready', [LobbyController::class, 'setReady'])->name('ready');
    Route::post('/{code}/color', [LobbyController::class, 'setColor'])->name('color');
    Route::post('/{code}/team', [LobbyController::class, 'setTeam'])->name('team');
    Route::post('/{code}/create-team', [LobbyController::class, 'createTeam'])->name('create-team');
    Route::post('/{code}/settings', [LobbyController::class, 'updateSettings'])->name('settings');
    Route::post('/{code}/bet/propose', [LobbyController::class, 'proposeBet'])->name('bet.propose');
    Route::post('/{code}/bet/respond', [LobbyController::class, 'respondToBet'])->name('bet.respond');
    Route::post('/{code}/bet/cancel', [LobbyController::class, 'cancelBet'])->name('bet.cancel');
    Route::post('/{code}/bet/refund', [LobbyController::class, 'refundBets'])->name('bet.refund');
    Route::post('/{code}/start', [LobbyController::class, 'start'])->name('start');
    Route::post('/{code}/leave', [LobbyController::class, 'leave'])->name('leave');
    Route::post('/{code}/remove-player', [LobbyController::class, 'removePlayer'])->name('remove-player');
    Route::post('/{code}/game-mode', [LobbyController::class, 'setGameMode'])->name('game-mode');
    Route::post('/{code}/match-players', [LobbyController::class, 'matchPlayersByLevel'])->name('match-players');
    Route::post('/{code}/player-order', [LobbyController::class, 'setPlayerOrder'])->name('player-order');
});

Route::post('/api/strategic-avatar', [LobbyController::class, 'setStrategicAvatar'])->middleware('auth')->name('api.strategic-avatar');

/* ===== DUO ===== */
Route::get('/duo/splash', [App\Http\Controllers\DuoController::class, 'showSplash'])->middleware('auth')->name('duo.splash');
Route::get('/duo', fn() => redirect()->route('duo.lobby'))->name('duo');

Route::prefix('duo')->name('duo.')->middleware('auth')->group(function () {
    Route::get('/lobby', [App\Http\Controllers\DuoController::class, 'lobby'])->name('lobby');
    Route::post('/matchmaking/random', [App\Http\Controllers\DuoController::class, 'createMatch'])->name('matchmaking.random');
    Route::post('/invite', [App\Http\Controllers\DuoController::class, 'invitePlayer'])->name('invite');
    Route::get('/invitations', [App\Http\Controllers\DuoController::class, 'getInvitations'])->name('invitations');
    Route::post('/matches/{match}/accept', [App\Http\Controllers\DuoController::class, 'acceptMatch'])->name('matches.accept');
    Route::post('/matches/{match}/decline', [App\Http\Controllers\DuoController::class, 'declineMatch'])->name('matches.decline');
    Route::post('/matches/{match}/cancel', [App\Http\Controllers\DuoController::class, 'cancelMatch'])->name('matches.cancel');
    Route::get('/matchmaking', [App\Http\Controllers\DuoController::class, 'matchmaking'])->name('matchmaking');
    Route::get('/game/{match}', [App\Http\Controllers\DuoController::class, 'game'])->name('game');
    Route::get('/result/{match}', [App\Http\Controllers\DuoController::class, 'result'])->name('result');
    Route::get('/rankings', [App\Http\Controllers\DuoController::class, 'rankings'])->name('rankings');
    Route::get('/contacts', [App\Http\Controllers\DuoController::class, 'getContacts'])->name('contacts');
    Route::post('/contacts/add', [App\Http\Controllers\DuoController::class, 'addContact'])->name('contacts.add');
    Route::delete('/contacts/{contactId}', [App\Http\Controllers\DuoController::class, 'deleteContact'])->name('contacts.delete');
    Route::get('/contacts/groups', [App\Http\Controllers\PlayerGroupController::class, 'index'])->name('contacts.groups');
    Route::post('/contacts/groups', [App\Http\Controllers\PlayerGroupController::class, 'store'])->name('contacts.groups.store');
    Route::get('/contacts/groups/{groupId}', [App\Http\Controllers\PlayerGroupController::class, 'show'])->name('contacts.groups.show');
    Route::put('/contacts/groups/{groupId}', [App\Http\Controllers\PlayerGroupController::class, 'update'])->name('contacts.groups.update');
    Route::delete('/contacts/groups/{groupId}', [App\Http\Controllers\PlayerGroupController::class, 'destroy'])->name('contacts.groups.destroy');
    Route::post('/contacts/groups/{groupId}/members', [App\Http\Controllers\PlayerGroupController::class, 'addMembers'])->name('contacts.groups.addMembers');
    Route::delete('/contacts/groups/{groupId}/members', [App\Http\Controllers\PlayerGroupController::class, 'removeMembers'])->name('contacts.groups.removeMembers');
});

/* ===== CHAT (Messages entre joueurs) ===== */
Route::prefix('chat')->name('chat.')->middleware('auth')->group(function () {
    Route::post('/send', [App\Http\Controllers\ChatController::class, 'sendMessage'])->name('send');
    Route::get('/conversation/{contactId}', [App\Http\Controllers\ChatController::class, 'getConversation'])->name('conversation');
    Route::get('/unread', [App\Http\Controllers\ChatController::class, 'getUnreadCount'])->name('unread');
    Route::post('/mark-read/{contactId}', [App\Http\Controllers\ChatController::class, 'markAsRead'])->name('mark-read');
    Route::get('/recent', [App\Http\Controllers\ChatController::class, 'getRecentConversations'])->name('recent');
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
    Route::get('/results/{match}', [App\Http\Controllers\LeagueIndividualController::class, 'result'])->name('results');
    Route::get('/temporary-access', [App\Http\Controllers\LeagueIndividualController::class, 'getTemporaryAccessInfo'])->name('temporary-access');
    Route::post('/purchase-access', [App\Http\Controllers\LeagueIndividualController::class, 'purchaseTemporaryAccess'])->name('purchase-access');
    Route::get('/rankings', function () {
        /** @var App\Models\User $user */
        $user = Auth::user();
        $myStats = $user->leagueIndividualStat;
        $myDivision = $user->playerDivisions()->where('mode', 'league_individual')->first();
        return view('league_individual_rankings', compact('myStats', 'myDivision'));
    })->name('rankings');
});

/* ===== LIGUE INDIVIDUEL API (web middleware for session auth) ===== */
Route::prefix('api/league/individual')->middleware('auth')->group(function () {
    Route::get('/', [App\Http\Controllers\LeagueIndividualController::class, 'index']);
    Route::post('/initialize', [App\Http\Controllers\LeagueIndividualController::class, 'initialize']);
    Route::get('/check-initialized', [App\Http\Controllers\LeagueIndividualController::class, 'checkInitialized']);
    Route::post('/create-match', [App\Http\Controllers\LeagueIndividualController::class, 'createMatch']);
    Route::get('/rankings', [App\Http\Controllers\LeagueIndividualController::class, 'getRankings']);
    Route::get('/match/{match}/game-state', [App\Http\Controllers\LeagueIndividualController::class, 'getGameState']);
    Route::post('/match/{match}/buzz', [App\Http\Controllers\LeagueIndividualController::class, 'buzz']);
    Route::post('/match/{match}/submit-answer', [App\Http\Controllers\LeagueIndividualController::class, 'submitAnswer']);
    Route::post('/match/{match}/finish', [App\Http\Controllers\LeagueIndividualController::class, 'finishMatch']);
    Route::get('/match/{match}/sync', [App\Http\Controllers\LeagueIndividualController::class, 'syncGameState']);
    Route::get('/my-stats', [App\Http\Controllers\LeagueIndividualController::class, 'getMyStats']);
});

/* ===== LIGUE ÉQUIPE ===== */
Route::get('/league/entry', function() {
    return redirect()->route('league.team.management');
})->middleware('auth')->name('league.entry');

Route::prefix('league/team')->name('league.team.')->middleware('auth')->group(function () {
    Route::get('/management/{teamId?}', [App\Http\Controllers\LeagueTeamController::class, 'showTeamManagement'])->name('management');
    Route::get('/create', [App\Http\Controllers\LeagueTeamController::class, 'showCreateTeam'])->name('create');
    Route::post('/create', [App\Http\Controllers\LeagueTeamController::class, 'createTeam'])->name('create.submit');
    Route::get('/search', [App\Http\Controllers\LeagueTeamController::class, 'searchTeams'])->name('search');
    Route::get('/search/api', [App\Http\Controllers\LeagueTeamController::class, 'searchTeamsApi'])->name('search.api');
    Route::get('/contacts/api', [App\Http\Controllers\LeagueTeamController::class, 'getContacts'])->name('contacts.api');
    Route::get('/details/{teamId}', [App\Http\Controllers\LeagueTeamController::class, 'showTeamDetails'])->name('details');
    Route::get('/captain/{teamId?}', [App\Http\Controllers\LeagueTeamController::class, 'showCaptainPanel'])->name('captain');
    Route::post('/invite', [App\Http\Controllers\LeagueTeamController::class, 'invitePlayer'])->name('invite');
    Route::post('/request/{teamId}', [App\Http\Controllers\LeagueTeamController::class, 'requestJoin'])->name('request');
    Route::delete('/request/{teamId}', [App\Http\Controllers\LeagueTeamController::class, 'cancelRequest'])->name('request.cancel');
    Route::post('/join-request/{requestId}/accept', [App\Http\Controllers\LeagueTeamController::class, 'acceptJoinRequest'])->name('join-request.accept');
    Route::post('/join-request/{requestId}/reject', [App\Http\Controllers\LeagueTeamController::class, 'rejectJoinRequest'])->name('join-request.reject');
    Route::post('/toggle-recruiting', [App\Http\Controllers\LeagueTeamController::class, 'toggleRecruiting'])->name('toggle-recruiting');
    Route::post('/leave', [App\Http\Controllers\LeagueTeamController::class, 'leaveTeam'])->name('leave');
    Route::post('/kick', [App\Http\Controllers\LeagueTeamController::class, 'kickMember'])->name('kick');
    Route::post('/transfer-captain', [App\Http\Controllers\LeagueTeamController::class, 'transferCaptain'])->name('transfer-captain');
    Route::get('/lobby/{teamId?}', [App\Http\Controllers\LeagueTeamController::class, 'showLobby'])->name('lobby');
    Route::get('/game/{match}', [App\Http\Controllers\LeagueTeamController::class, 'showGame'])->name('game');
    Route::get('/results/{match}', [App\Http\Controllers\LeagueTeamController::class, 'showResults'])->name('results');
    Route::post('/invitation/{invitationId}/accept', [App\Http\Controllers\LeagueTeamController::class, 'acceptInvitation'])->name('invitation.accept');
    Route::post('/invitation/{invitationId}/decline', [App\Http\Controllers\LeagueTeamController::class, 'declineInvitation'])->name('invitation.decline');
    Route::post('/{teamId}/toggle-recruiting', [App\Http\Controllers\LeagueTeamController::class, 'toggleRecruitingById'])->name('toggle-recruiting-by-id');
    Route::post('/{teamId}/gather', [App\Http\Controllers\LeagueTeamController::class, 'gatherTeam'])->name('gather');
    Route::get('/{teamId}/gathering/{sessionId}', [App\Http\Controllers\LeagueTeamController::class, 'showGathering'])->name('gathering');
    Route::get('/gathering/{sessionId}/members', [App\Http\Controllers\LeagueTeamController::class, 'getGatheringMembers'])->name('gathering.members');
});

Route::prefix('api/league/team')->middleware('auth')->group(function () {
    Route::post('/find-opponents', [App\Http\Controllers\LeagueTeamController::class, 'findOpponents']);
    Route::post('/start-match', [App\Http\Controllers\LeagueTeamController::class, 'startMatch']);
    Route::get('/timed-access', [App\Http\Controllers\LeagueTeamController::class, 'getTimedAccess']);
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
Route::get('/ligue', [App\Http\Controllers\LeagueTeamController::class, 'showLigue'])->middleware('auth')->name('ligue');

/* ===== GUIDE DU JOUEUR ===== */
Route::get('/guide', [App\Http\Controllers\GuideController::class, 'index'])->name('guide.index');
Route::get('/guide/{mode}', [App\Http\Controllers\GuideController::class, 'show'])->name('guide.show');
Route::get('/reglements', fn() => redirect()->route('guide.index'))->name('reglements');

/* ===== INTERFACE DE JEU UNIFIÉE ===== */
Route::prefix('game')->name('game.')->middleware('auth')->group(function () {
    Route::post('/{mode}/start', [App\Http\Controllers\UnifiedGameController::class, 'startGame'])->name('start');
    Route::get('/{mode}/resume', [App\Http\Controllers\UnifiedGameController::class, 'showResume'])->name('resume');
    Route::get('/{mode}/preparation', [App\Http\Controllers\UnifiedGameController::class, 'showPreparation'])->name('preparation');
    Route::get('/{mode}/question', [App\Http\Controllers\UnifiedGameController::class, 'showQuestion'])->name('question');
    Route::post('/{mode}/buzz', [App\Http\Controllers\UnifiedGameController::class, 'handleBuzz'])->name('buzz');
    Route::get('/{mode}/answers', [App\Http\Controllers\UnifiedGameController::class, 'showAnswers'])->name('answers');
    Route::post('/{mode}/answer', [App\Http\Controllers\UnifiedGameController::class, 'submitAnswer'])->name('answer');
    Route::get('/{mode}/transition', [App\Http\Controllers\UnifiedGameController::class, 'showTransition'])->name('transition');
    Route::get('/{mode}/next-question', [App\Http\Controllers\UnifiedGameController::class, 'advanceToNextQuestion'])->name('next-question');
    Route::get('/{mode}/round-result', [App\Http\Controllers\UnifiedGameController::class, 'showRoundResult'])->name('round-result');
    Route::post('/{mode}/next-round', [App\Http\Controllers\UnifiedGameController::class, 'startNextRound'])->name('next-round');
    Route::get('/{mode}/match-result', [App\Http\Controllers\UnifiedGameController::class, 'showMatchResult'])->name('match-result');
    Route::get('/{mode}/state', [App\Http\Controllers\UnifiedGameController::class, 'getGameState'])->name('state');
    Route::post('/{mode}/sync', [App\Http\Controllers\UnifiedGameController::class, 'syncFromFirebase'])->name('sync');
    Route::post('/{mode}/use-skill', [App\Http\Controllers\UnifiedGameController::class, 'useSkill'])->name('use-skill');
    Route::post('/{mode}/fetch-question', [App\Http\Controllers\UnifiedGameController::class, 'fetchQuestionJson'])->name('fetch-question');
    
    // Tiebreaker routes
    Route::get('/{mode}/tiebreaker-choice', [App\Http\Controllers\UnifiedGameController::class, 'tiebreakerChoice'])->name('tiebreaker-choice');
    Route::post('/{mode}/tiebreaker-select', [App\Http\Controllers\UnifiedGameController::class, 'tiebreakerSelect'])->name('tiebreaker-select');
    Route::get('/{mode}/tiebreaker-bonus', [App\Http\Controllers\UnifiedGameController::class, 'tiebreakerBonus'])->name('tiebreaker-bonus');
    Route::post('/{mode}/tiebreaker-bonus-answer', [App\Http\Controllers\UnifiedGameController::class, 'tiebreakerBonusAnswer'])->name('tiebreaker-bonus-answer');
    Route::get('/{mode}/tiebreaker-efficiency', [App\Http\Controllers\UnifiedGameController::class, 'tiebreakerEfficiency'])->name('tiebreaker-efficiency');
    Route::get('/{mode}/tiebreaker-sudden-death', [App\Http\Controllers\UnifiedGameController::class, 'tiebreakerSuddenDeath'])->name('tiebreaker-sudden-death');
    Route::post('/{mode}/tiebreaker-sudden-death-answer', [App\Http\Controllers\UnifiedGameController::class, 'tiebreakerSuddenDeathAnswer'])->name('tiebreaker-sudden-death-answer');
    
    // Forfeit/Disconnect handling
    Route::post('/{mode}/forfeit', [App\Http\Controllers\UnifiedGameController::class, 'handleForfeit'])->name('forfeit');
    Route::get('/{mode}/forfeit-result', [App\Http\Controllers\UnifiedGameController::class, 'showForfeitResult'])->name('forfeit-result');
});

/* ===== QUÊTES & QUÊTES QUOTIDIENNES ===== */
Route::get('/quetes', [App\Http\Controllers\QuestesController::class, 'index'])->middleware('auth')->name('quetes');
Route::post('/quetes/claim/{questId}', [App\Http\Controllers\QuestesController::class, 'claim'])->middleware('auth')->name('quetes.claim');
Route::get('/quetes-quotidiennes', [App\Http\Controllers\DailyQuestsController::class, 'index'])->middleware('auth')->name('quetes-quotidiennes');

// (Optionnel) Fallback 404 propre
// Route::fallback(fn() => response()->view('notfound', [], 404));
