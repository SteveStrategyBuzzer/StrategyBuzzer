
cd /home/stevegroupe/StrategyBuzzer/docs/audits && cat << 'EOF' > duo_invitation_lobby_room_sync_recap.md
Voici un récapitulatif précis et complet de toutes les modifications apportées lors de la session précédente.

Ce qui a été corrigé : La chaîne de 4 bugs du flux d'invitation Duo

Contexte du problème
Quand le Joueur 1 invitait le Joueur 2 à un match Duo via invitation, les deux joueurs arrivaient dans un lobby sans connexion Socket.IO active, et la page de jeu n'avait pas non plus de token JWT. Résultat : aucune synchronisation en temps réel ne démarrait.

Bug #1 — app/Services/DuoMatchmakingService.php — Mauvaise transition de statut

Avant :
public function acceptMatch(DuoMatch $match): DuoMatch
{
    $match->status = 'playing';
    $match->started_at = now();
    $match->save();
}

Après :
public function acceptMatch(DuoMatch $match): DuoMatch
{
    $match->status = 'lobby';
    $match->save();
}

Pourquoi :
Quand le Joueur 2 acceptait l'invitation, le match passait immédiatement à playing avec un started_at, alors que le jeu n'avait pas encore démarré. Ce statut prématuré cassait tous les filtres en aval.

Bug #2 — app/Http/Controllers/LobbyController.php — Filtre de statut trop restrictif

Avant :
$duoMatch = DuoMatch::where('lobby_code', $code)
    ->whereIn('status', ['pending', 'waiting', 'lobby', 'in_progress', 'active'])
    ->first();

Après :
$duoMatch = DuoMatch::where('lobby_code', $code)
    ->whereIn('status', ['waiting', 'lobby', 'playing'])
    ->orderByDesc('created_at')
    ->first();

Pourquoi :
Le filtre ne contenait pas 'playing', donc après acceptMatch(), $duoMatch était toujours null. La suite du code ne pouvait pas injecter les tokens.

Bug #3 — app/Http/Controllers/LobbyController.php — Mauvaise source de token

Avant :
if ($duoMatch && $duoMatch->room_id) {
    $playerToken = $gameServerService->generatePlayerToken($user->id, $duoMatch->room_id);
    $gameServerUrl = $gameServerService->getSocketUrl();
}

Après :
La source principale est maintenant le cache du lobby (peuplé dès createLobby), avec trois niveaux de fallback :

// Source principale : cache du lobby
$lobbyData = $lobbyState['lobby'] ?? [];
$gameServerData = $lobbyData['game_server'] ?? [];

$cachedToken = $gameServerData['player_tokens'][$user->id] ?? null;
$cachedRoomId = $gameServerData['roomId'] ?? null;
$cachedUrl = $gameServerData['wsUrl'] ?? $gameServerData['socket_url'] ?? null;

if ($cachedToken && $cachedRoomId) {
    $playerToken = $cachedToken;
    $gameServerUrl = $cachedUrl ?? $gameServerService->getSocketUrl();
} elseif ($duoMatch && $duoMatch->room_id) {
    // Fallback : room_id en base (après startGame)
    $playerToken = $gameServerService->generatePlayerToken($user->id, $duoMatch->room_id);
    $gameServerUrl = $gameServerService->getSocketUrl();
} elseif ($cachedRoomId) {
    // Fallback : re-générer depuis le room_id du cache
    $playerToken = $gameServerService->generatePlayerToken($user->id, $cachedRoomId);
    $gameServerUrl = $cachedUrl ?? $gameServerService->getSocketUrl();
}

Pourquoi :
$duoMatch->room_id était toujours null. Les tokens pré-générés existent déjà dans le cache du lobby dès la création du salon — il fallait simplement les utiliser.

Bug #4 — app/Services/LobbyService.php — room_id jamais persisté en base

Dans startGame(), après le démarrage réussi du jeu sur le Game Server :

try {
    DuoMatch::where('lobby_code', strtoupper($code))
        ->whereIn('status', ['waiting', 'lobby'])
        ->update([
            'room_id' => $roomId,
            'status' => 'playing',
            'started_at' => now(),
        ]);
} catch (\Exception $e) {
    Log::warning("[LobbyService] Could not persist room_id", [
        'error' => $e->getMessage(),
    ]);
}

Import ajouté :
use App\Models\DuoMatch;

Pourquoi :
startGame() créait la room et les tokens, mais ne persistait jamais room_id dans duo_matches. Résultat : la page de jeu ne recevait jamais de JWT valide.

Bonus — app/Services/DuoMatchmakingService.php — Double-matchmaking guard

Ajout de 'lobby' dans les filtres anti-doublon :

->whereDoesntHave('duoMatchesAsPlayer1', function ($query) {
    $query->whereIn('status', ['waiting', 'lobby', 'playing']);
})

Pourquoi :
Sans ça, un joueur déjà en lobby pouvait être re-matché par le matchmaking.

Cycle de vie correct après correction :

waiting → lobby (acceptMatch) → playing (startGame DB persist) → finished
