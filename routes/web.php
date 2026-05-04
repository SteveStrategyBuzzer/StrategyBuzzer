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
Route::post('/boutique/purchase',   [BoutiqueController::class, 'purchase'])->name('boutique.purchase')->middleware('throttle:10,1');

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
Route::get('/api/now', function () {
    return response()->json([
        'serverTime' => (int)(microtime(true) * 1000),
        'timestamp' => time()
    ]);
})->name('api.now');
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
Route::post('/auth/email/login',      [AuthController::class, 'handleEmailLogin'])->name('email.login.submit')->middleware('throttle:5,1');
Route::get('/auth/email/register',    [AuthController::class, 'showEmailRegister'])->name('email.register');
Route::post('/auth/email/register',   [AuthController::class, 'handleEmailRegister'])->name('email.register.submit')->middleware('throttle:5,1');
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
    Route::post('/set-teammate', [SoloController::class, 'setTeammate'])->name('set-teammate');
    
    Route::get('/resume',  [SoloController::class, 'resume'])->name('resume');
    Route::get('/prepare', [SoloController::class, 'prepare'])->name('prepare');
    Route::get('/preparation', [SoloController::class, 'prepare'])->name('preparation');
    Route::get('/game',    [SoloController::class, 'game'])->name('game');
    Route::get('/timeout', [SoloController::class, 'timeout'])->name('timeout');
    Route::post('/buzz',   [SoloController::class, 'buzz'])->name('buzz');
    Route::match(['get', 'post'], '/answer', [SoloController::class, 'answer'])->name('answer');
    Route::post('/use-skill', [SoloController::class, 'useSkill'])->name('use-skill');
    Route::post('/cancel-error', [SoloController::class, 'cancelError'])->name('cancel-error');
    Route::post('/use-scroll-skill', [SoloController::class, 'useScrollSkill'])->name('use-scroll-skill');
    Route::post('/reduce-time', [SoloController::class, 'reduceTime'])->name('reduce-time');
    Route::post('/shuffle-answers', [SoloController::class, 'shuffleAnswers'])->name('shuffle-answers');
    Route::get('/bonus-question', [SoloController::class, 'bonusQuestion'])->name('bonus-question');
    Route::post('/answer-bonus', [SoloController::class, 'answerBonus'])->name('answer-bonus');
    Route::get('/next',    [SoloController::class, 'nextQuestion'])->name('next');
    Route::post('/fetch-question', [SoloController::class, 'fetchQuestionApi'])->name('fetch-question');
    Route::post('/submit-answer', [SoloController::class, 'submitAnswerApi'])->name('submit-answer');
    Route::post('/generate-batch', [SoloController::class, 'generateBatch'])->name('generate-batch');
    Route::post('/generate-block', [SoloController::class, 'generateBlock'])->name('generate-block'); // NOUVEAU: génération progressive
    // #88 — progressive-queue route removed: it routed live gameplay through
    // the AI provider. Matches now read from the persistent question bank.
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
    Route::get('/open', [LobbyController::class, 'getOpenLobbies'])->name('open');
    Route::post('/{code}/close', [LobbyController::class, 'closeLobby'])->name('close');
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
    Route::post('/{code}/match-players', [LobbyController::class, 'matchPlayersByLevel'])->name('match-players');
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
    Route::get('/question/{match}', [App\Http\Controllers\DuoController::class, 'question'])->name('question');
    Route::get('/answer/{match}', [App\Http\Controllers\DuoController::class, 'answer'])->name('answer');
    Route::get('/result/{match}', [App\Http\Controllers\DuoController::class, 'result'])->name('result');
    Route::get('/rankings', [App\Http\Controllers\DuoController::class, 'rankings'])->name('rankings');
    Route::get('/contacts', [App\Http\Controllers\DuoController::class, 'getContacts'])->name('contacts');
    Route::post('/contacts/add', [App\Http\Controllers\DuoController::class, 'addContact'])->name('contacts.add');
    Route::get('/contacts/lookup/{playerCode}', [App\Http\Controllers\DuoController::class, 'contactLookup'])->name('contacts.lookup');
    Route::delete('/contacts/{contactId}', [App\Http\Controllers\DuoController::class, 'deleteContact'])->name('contacts.delete');
    Route::get('/contacts/groups', [App\Http\Controllers\PlayerGroupController::class, 'index'])->name('contacts.groups');
    Route::post('/contacts/groups', [App\Http\Controllers\PlayerGroupController::class, 'store'])->name('contacts.groups.store');
    Route::get('/contacts/groups/{groupId}', [App\Http\Controllers\PlayerGroupController::class, 'show'])->name('contacts.groups.show');
    Route::put('/contacts/groups/{groupId}', [App\Http\Controllers\PlayerGroupController::class, 'update'])->name('contacts.groups.update');
    Route::delete('/contacts/groups/{groupId}', [App\Http\Controllers\PlayerGroupController::class, 'destroy'])->name('contacts.groups.destroy');
    Route::post('/contacts/groups/{groupId}/members', [App\Http\Controllers\PlayerGroupController::class, 'addMembers'])->name('contacts.groups.addMembers');
    Route::delete('/contacts/groups/{groupId}/members', [App\Http\Controllers\PlayerGroupController::class, 'removeMembers'])->name('contacts.groups.removeMembers');
    Route::post('/queue/join', [App\Http\Controllers\DuoController::class, 'joinQueue'])->name('queue.join');
    Route::post('/queue/leave', [App\Http\Controllers\DuoController::class, 'leaveQueue'])->name('queue.leave');
    Route::get('/queue/opponents', [App\Http\Controllers\DuoController::class, 'getQueueOpponents'])->name('queue.opponents');
    Route::post('/queue/create-match', [App\Http\Controllers\DuoController::class, 'createQueueMatch'])->name('queue.createMatch');
    Route::post('/matches/{match}/create-room', [App\Http\Controllers\DuoController::class, 'createGameServerRoom'])->name('matches.create-room');
    
    Route::post('/match/{match}/skill', [App\Http\Controllers\DuoController::class, 'activateSkill'])->name('match.skill');
    Route::post('/match/{match}/hint', [App\Http\Controllers\DuoController::class, 'getHint'])->name('match.hint');
    Route::post('/match/{match}/ai-suggest', [App\Http\Controllers\DuoController::class, 'getAISuggestion'])->name('match.ai-suggest');
    Route::post('/match/{match}/preview-questions', [App\Http\Controllers\DuoController::class, 'getPreviewQuestions'])->name('match.preview-questions');

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
    
    // Sélection de structure de jeu
    Route::get('/{gameId}/structure', [App\Http\Controllers\MasterGameController::class, 'showStructure'])->name('structure');
    Route::post('/{gameId}/structure', [App\Http\Controllers\MasterGameController::class, 'saveStructure'])->name('structure.save');
    
    // Configuration des équipes (pour modes équipe)
    Route::get('/{gameId}/teams', [App\Http\Controllers\MasterGameController::class, 'showTeams'])->name('teams');
    Route::post('/{gameId}/teams', [App\Http\Controllers\MasterGameController::class, 'saveTeams'])->name('teams.save');
    
    Route::get('/codes', [App\Http\Controllers\MasterGameController::class, 'codes'])->name('codes');
    Route::get('/{gameId}/codes', [App\Http\Controllers\MasterGameController::class, 'codes'])->name('codes.show');
    Route::get('/{gameId}/preview', [App\Http\Controllers\MasterGameController::class, 'preview'])->name('preview');
    Route::post('/{gameId}/select', [App\Http\Controllers\MasterGameController::class, 'selectQuiz'])->name('select');
    Route::delete('/{gameId}', [App\Http\Controllers\MasterGameController::class, 'destroyQuiz'])->name('destroy');
    Route::get('/{gameId}/lobby', [App\Http\Controllers\MasterGameController::class, 'lobby'])->name('lobby');
    
    // Secure player join page (no gameId in URL - players enter code only)
    Route::get('/join', [App\Http\Controllers\MasterGameController::class, 'showJoinForm'])->name('join.form');
    Route::post('/join', [App\Http\Controllers\MasterGameController::class, 'processJoin'])->name('join.process');
    
    // Invite page (for Master to invite contacts)
    Route::get('/{gameId}/invite', [App\Http\Controllers\MasterGameController::class, 'showInvite'])->name('invite');
    Route::post('/{gameId}/invite', [App\Http\Controllers\MasterGameController::class, 'sendInvites'])->name('invite.send');
});

/* ===== LIGUE (page de sélection) ===== */
Route::get('/ligue', [App\Http\Controllers\LeagueTeamController::class, 'showLigue'])->middleware('auth')->name('ligue');

/* ===== GUIDE DU JOUEUR ===== */
Route::get('/guide', [App\Http\Controllers\GuideController::class, 'index'])->name('guide.index');
Route::get('/guide/{mode}', [App\Http\Controllers\GuideController::class, 'show'])->name('guide.show');
Route::get('/reglements', fn() => redirect()->route('guide.index'))->name('reglements');

/* ===== INTERFACE DE JEU DUO (Socket.IO) ===== */
Route::prefix('game/duo')->name('game.duo.')->middleware('auth')->group(function () {
    Route::post('/start', [App\Http\Controllers\DuoController::class, 'startGame'])->name('start');
    Route::get('/intro', [App\Http\Controllers\DuoController::class, 'showIntro'])->name('intro');
    Route::get('/resume', [App\Http\Controllers\DuoController::class, 'showResume'])->name('resume');
    Route::get('/question', [App\Http\Controllers\DuoController::class, 'showQuestion'])->name('question');
    Route::get('/answer', [App\Http\Controllers\DuoController::class, 'showAnswer'])->name('answer');
    Route::get('/result', [App\Http\Controllers\DuoController::class, 'showResult'])->name('result');
    Route::get('/round-scoreboard', [App\Http\Controllers\DuoController::class, 'showRoundScoreboard'])->name('round-scoreboard');
    Route::post('/fetch-question', [App\Http\Controllers\DuoController::class, 'fetchQuestionJson'])->name('fetch-question');
    Route::post('/use-skill', [App\Http\Controllers\DuoController::class, 'useSkill'])->name('use-skill');
    Route::get('/match-result', [App\Http\Controllers\DuoController::class, 'showMatchResult'])->name('match-result');
    Route::post('/forfeit', [App\Http\Controllers\DuoController::class, 'handleForfeit'])->name('forfeit');
    Route::post('/match/{match}/finish-socketio', [App\Http\Controllers\DuoController::class, 'finishMatchSocketIO'])->name('finish-socketio');
});

/* ===== ENDPOINTS INTERNES SERVEUR-À-SERVEUR (Node game-server → Laravel) =====
   Pas de middleware auth/web : authentification via JWT dans Authorization header
   (signé avec GAME_SERVER_JWT_SECRET, claim purpose='internal_finalize').
   Exclu de CSRF dans VerifyCsrfToken middleware. */
Route::prefix('internal/duo')->name('internal.duo.')->group(function () {
    Route::post('/match/finalize', [App\Http\Controllers\DuoController::class, 'internalFinalize'])->name('match.finalize');
});

/* Same JWT-signed server-to-server contract for League Team mode (Task #50). */
Route::prefix('internal/league/team')->name('internal.league.team.')->group(function () {
    Route::post('/match/finalize', [App\Http\Controllers\LeagueTeamController::class, 'internalFinalize'])->name('match.finalize');
});

/* Match snapshot checkpoint — Node game server → Laravel (T-D). */
Route::post('/internal/match/snapshot', [App\Http\Controllers\InternalMatchController::class, 'storeSnapshot'])
    ->name('internal.match.snapshot');

/* ===== INTERFACE DE JEU LEAGUE (Socket.IO) ===== */
Route::prefix('game/league')->name('game.league.')->middleware('auth')->group(function () {
    Route::post('/start', [App\Http\Controllers\LeagueIndividualController::class, 'startGame'])->name('start');
    Route::get('/resume', [App\Http\Controllers\LeagueIndividualController::class, 'showResume'])->name('resume');
    Route::get('/question', [App\Http\Controllers\LeagueIndividualController::class, 'showQuestion'])->name('question');
    Route::get('/answer', [App\Http\Controllers\LeagueIndividualController::class, 'showAnswer'])->name('answer');
    Route::get('/result', [App\Http\Controllers\LeagueIndividualController::class, 'showResult'])->name('result');
    Route::post('/fetch-question', [App\Http\Controllers\LeagueIndividualController::class, 'fetchQuestionJson'])->name('fetch-question');
    Route::post('/use-skill', [App\Http\Controllers\LeagueIndividualController::class, 'useSkill'])->name('use-skill');
    Route::get('/match-result', [App\Http\Controllers\LeagueIndividualController::class, 'showMatchResult'])->name('match-result');
    Route::post('/forfeit', [App\Http\Controllers\LeagueIndividualController::class, 'handleForfeit'])->name('forfeit');
});

/* ===== INTERFACE DE JEU MASTER (Socket.IO - jusqu'à 40 joueurs) ===== */
Route::prefix('game/master')->name('game.master.')->middleware('auth')->group(function () {
    Route::post('/start', [App\Http\Controllers\MasterGameController::class, 'startGameFromLobby'])->name('start');
    Route::get('/resume', [App\Http\Controllers\MasterGameController::class, 'showResume'])->name('resume');
    Route::get('/question', [App\Http\Controllers\MasterGameController::class, 'showQuestionPage'])->name('question');
    Route::get('/answer', [App\Http\Controllers\MasterGameController::class, 'showAnswerPage'])->name('answer');
    Route::get('/result', [App\Http\Controllers\MasterGameController::class, 'showResultPage'])->name('result');
    Route::post('/fetch-question', [App\Http\Controllers\MasterGameController::class, 'fetchQuestionJsonApi'])->name('fetch-question');
    Route::get('/match-result', [App\Http\Controllers\MasterGameController::class, 'showMatchResultPage'])->name('match-result');
});

/* ===== PUBLICITÉS (REWARDED ADS) ===== */
Route::middleware('auth')->group(function () {
    Route::post('/ads/reward', [App\Http\Controllers\AdController::class, 'reward'])->name('ads.reward')->middleware('throttle:5,1');
    Route::get('/ads/status', [App\Http\Controllers\AdController::class, 'status'])->name('ads.status')->middleware('throttle:20,1');
});

/* ===== QUÊTES & QUÊTES QUOTIDIENNES ===== */
Route::get('/quetes', [App\Http\Controllers\QuestesController::class, 'index'])->middleware('auth')->name('quetes');
Route::post('/quetes/claim/{questId}', [App\Http\Controllers\QuestesController::class, 'claim'])->middleware('auth')->name('quetes.claim');
Route::get('/quetes-quotidiennes', [App\Http\Controllers\DailyQuestsController::class, 'index'])->middleware('auth')->name('quetes-quotidiennes');
Route::post('/quetes-quotidiennes/action', [App\Http\Controllers\DailyQuestsController::class, 'triggerAction'])->middleware('auth')->name('quetes-quotidiennes.action');

// (Optionnel) Fallback 404 propre
// Route::fallback(fn() => response()->view('notfound', [], 404));

/*
|--------------------------------------------------------------------------
| DEBUG ONLY (REMOVE AFTER TEST)
|--------------------------------------------------------------------------
| GET /__ip?t=TOKEN
*/
Route::get('/__ip', function (\Illuminate\Http\Request $request) {
    $token = env("STRIPE_WEBHOOK_TOKEN") ?: "debug";
    if ($request->query('t') !== $token) {
        abort(403);
    }

    return response()->json([
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'ip' => $request->ip(),
        'ips' => $request->ips(),
        'headers' => [
            'host' => $request->header('host'),
            'x-real-ip' => $request->header('x-real-ip'),
            'x-forwarded-for' => $request->header('x-forwarded-for'),
            'x-forwarded-proto' => $request->header('x-forwarded-proto'),
            'x-forwarded-host' => $request->header('x-forwarded-host'),
            'cf-connecting-ip' => $request->header('cf-connecting-ip'),
            'cf-ipcountry' => $request->header('cf-ipcountry'),
        ],
    ]);
});

/*
|-------------------------------------------------------------------------- 
| DEBUG ONLY (REMOVE AFTER TEST)
|-------------------------------------------------------------------------- 
| GET /__currency?t=TOKEN
*/
Route::get('/__currency', function (\Illuminate\Http\Request $request) {
    $token = env("STRIPE_WEBHOOK_TOKEN") ?: "debug";
    if ($request->query('t') !== $token) {
        abort(403);
    }

    return response()->json([
        'ip' => $request->attributes->get('geo_ip'),
        'country' => $request->attributes->get('geo_country'),
        'currency' => $request->attributes->get('geo_currency'),
        'source' => $request->attributes->get('geo_source'),
        'session_currency' => $request->session()->get('currency'),
        'headers' => [
            'x-real-ip' => $request->header('x-real-ip'),
            'x-forwarded-for' => $request->header('x-forwarded-for'),
        ],
    ]);
});

Route::get('/privacy-policy', function () {
    return view('privacy');
});

// #109 Admin observability page for admin_question_audit_log (#94 audit table).
// Same shared-secret auth as /api/admin/questions/health (QB_HEALTH_TOKEN,
// timing-safe hash_equals, fail-closed). Read-only: no AI logic, no gameplay.
Route::get('/admin/questions/audit-log', \App\Http\Controllers\Admin\QuestionBankAuditLogController::class)
    ->name('admin.questions.audit-log');

Route::view('/data-deletion', 'data-deletion')->name('data.deletion');

/* ===== DEV/TEST-ONLY SUPPORT ROUTES =====
   Gated at registration time by APP_ENV !== 'production' AND defended again
   inside each controller method. CSRF is excluded for the `__test/*` prefix
   in App\Http\Middleware\VerifyCsrfToken. These routes exist so Playwright /
   the testing skill can bypass Firebase OAuth-only login and exercise the
   full multiplayer (Duo) browser flow. They prevent regressions like the
   recent `VALIDATION_ERROR: Invalid join_room payload` incident. */
if (! app()->environment('production')) {
    Route::prefix('__test')->name('test.')->group(function () {
        Route::post('/login', [App\Http\Controllers\TestSupportController::class, 'login'])
            ->name('login');

        Route::post('/duo/setup-bot-match', [App\Http\Controllers\TestSupportController::class, 'setupDuoBotMatch'])
            ->middleware('auth')
            ->name('duo.setup-bot-match');

        Route::post('/master/setup-bot-match', [App\Http\Controllers\TestSupportController::class, 'setupMasterBotMatch'])
            ->middleware('auth')
            ->name('master.setup-bot-match');

        Route::post('/league/individual/setup-bot-match', [App\Http\Controllers\TestSupportController::class, 'setupLeagueIndividualBotMatch'])
            ->middleware('auth')
            ->name('league.individual.setup-bot-match');
    });
}

// === DEBUG TEMPORAIRE — à supprimer après diagnostic ===
Route::get('/who-am-i', function () {
    $user = Auth::user();
    if (!$user) return response()->json(['authenticated' => false]);
    return response()->json([
        'authenticated' => true,
        'id'                => $user->id,
        'email'             => $user->email,
        'name'              => $user->name,
        'player_code'       => $user->player_code,
        'coins'             => $user->coins,
        'competence_coins'  => $user->competence_coins,
        'profile_completed' => $user->profile_completed,
        'connection'        => $user->getConnectionName(),
        'profile_settings_pseudonym' => data_get(
            is_string($user->profile_settings)
                ? json_decode($user->profile_settings, true)
                : ($user->profile_settings ?? []),
            'pseudonym'
        ),
    ]);
})->middleware('auth');
