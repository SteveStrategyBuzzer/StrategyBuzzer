@extends('layouts.app')

@section('content')
@php
    $playerDisplayName = $playerName ?? ($player_info['name'] ?? __('Joueur'));
    $opponentDisplayName = $opponentName ?? ($opponent_info['name'] ?? __('Adversaire'));

    $playerAvatar = $playerAvatarPath
        ?? ($player_info['avatar'] ?? asset('images/avatars/standard/standard1.png'));

    $opponentAvatar = $opponentAvatarPath
        ?? ($opponent_info['avatar'] ?? asset('images/avatars/standard/standard1.png'));

    $playerScoreValue = $playerScore ?? ($player_info['score'] ?? 0);
    $opponentScoreValue = $opponentScore ?? ($opponent_info['score'] ?? 0);

    $roundValue = $currentRound ?? $currentQuestion ?? 1;
    $totalValue = $totalQuestions ?? 10;

    $playerWasCorrect = (bool) ($wasCorrect ?? false);
    $pointsValue = (int) ($pointsEarned ?? 0);
@endphp

<style>
    body {
        background:
            radial-gradient(circle at top, rgba(78, 205, 196, 0.18), transparent 35%),
            linear-gradient(135deg, #0b1220 0%, #16213e 45%, #1a1a2e 100%);
        color: #fff;
        min-height: 100vh;
        margin: 0;
        padding: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow-x: hidden;
    }

    .waiting-shell {
        width: 100%;
        max-width: 900px;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .waiting-header {
        text-align: center;
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid rgba(78, 205, 196, 0.28);
        border-radius: 24px;
        padding: 20px 18px;
        box-shadow: 0 18px 50px rgba(0, 0, 0, 0.28);
        backdrop-filter: blur(10px);
    }

    .waiting-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        background: rgba(78, 205, 196, 0.14);
        border: 1px solid rgba(78, 205, 196, 0.35);
        color: #7ee7df;
        font-size: 0.9rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 14px;
    }

    .waiting-title {
        margin: 0;
        font-size: clamp(1.9rem, 4vw, 3rem);
        font-weight: 900;
        color: #4ECDC4;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-shadow: 0 0 24px rgba(78, 205, 196, 0.22);
    }

    .waiting-subtitle {
        margin: 12px 0 0;
        font-size: 1rem;
        color: #cfd8dc;
        line-height: 1.5;
    }

    .waiting-grid {
        display: grid;
        grid-template-columns: 1.15fr 0.85fr;
        gap: 18px;
    }

    .waiting-card,
    .score-card {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 24px;
        border: 2px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 18px 50px rgba(0, 0, 0, 0.28);
        backdrop-filter: blur(10px);
    }

    .waiting-card {
        padding: 24px;
    }

    .score-card {
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .round-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }

    .round-pill,
    .state-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        padding: 10px 14px;
        font-weight: 800;
        font-size: 0.9rem;
        letter-spacing: 0.4px;
    }

    .round-pill {
        background: rgba(255, 215, 0, 0.14);
        border: 1px solid rgba(255, 215, 0, 0.3);
        color: #ffd86b;
    }

    .state-pill {
        background: rgba(78, 205, 196, 0.14);
        border: 1px solid rgba(78, 205, 196, 0.3);
        color: #7ee7df;
    }

    .player-result-box {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 18px;
        border-radius: 20px;
        margin-bottom: 18px;
        border: 2px solid transparent;
    }

    .player-result-box.correct {
        background: rgba(76, 175, 80, 0.12);
        border-color: rgba(76, 175, 80, 0.32);
    }

    .player-result-box.incorrect {
        background: rgba(244, 67, 54, 0.12);
        border-color: rgba(244, 67, 54, 0.32);
    }

    .player-result-icon {
        width: 72px;
        height: 72px;
        min-width: 72px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 900;
        color: #fff;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.24);
    }

    .player-result-box.correct .player-result-icon {
        background: linear-gradient(135deg, #2e7d32, #4caf50);
    }

    .player-result-box.incorrect .player-result-icon {
        background: linear-gradient(135deg, #b71c1c, #f44336);
    }

    .player-result-text {
        flex: 1;
    }

    .player-result-title {
        margin: 0 0 6px;
        font-size: 1.25rem;
        font-weight: 900;
    }

    .player-result-desc {
        margin: 0;
        color: #d8dee3;
        line-height: 1.5;
    }

    .waiting-animation-box {
        text-align: center;
        padding: 20px 12px 8px;
    }

    .waiting-main-text {
        margin: 0 0 16px;
        font-size: 1.15rem;
        font-weight: 800;
        color: #dff8f6;
    }

    .dots {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #4ECDC4;
        box-shadow: 0 0 18px rgba(78, 205, 196, 0.45);
        animation: bounce 1.4s ease-in-out infinite both;
    }

    .dot:nth-child(1) { animation-delay: -0.32s; }
    .dot:nth-child(2) { animation-delay: -0.16s; }
    .dot:nth-child(3) { animation-delay: 0s; }

    .waiting-note {
        margin: 0;
        color: #aebdc7;
        font-size: 0.98rem;
        line-height: 1.5;
    }

    .score-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 900;
        color: #cfe9ea;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .duel-scoreboard {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        gap: 12px;
    }

    .score-side {
        text-align: center;
        padding: 14px 10px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.04);
    }

    .score-side.player {
        border: 2px solid rgba(78, 205, 196, 0.35);
    }

    .score-side.opponent {
        border: 2px solid rgba(255, 107, 107, 0.35);
    }

    .avatar-frame {
        width: 74px;
        height: 74px;
        margin: 0 auto 10px;
        border-radius: 50%;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .score-side.player .avatar-frame {
        border: 3px solid #4ECDC4;
        box-shadow: 0 0 20px rgba(78, 205, 196, 0.18);
    }

    .score-side.opponent .avatar-frame {
        border: 3px solid #FF6B6B;
        box-shadow: 0 0 20px rgba(255, 107, 107, 0.18);
    }

    .avatar-frame img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .player-label {
        font-size: 0.9rem;
        font-weight: 800;
        margin-bottom: 8px;
        line-height: 1.3;
        word-break: break-word;
    }

    .score-side.player .player-label {
        color: #7ee7df;
    }

    .score-side.opponent .player-label {
        color: #ff9c9c;
    }

    .score-number {
        font-size: 2.1rem;
        font-weight: 900;
        line-height: 1;
    }

    .score-side.player .score-number {
        color: #4ECDC4;
    }

    .score-side.opponent .score-number {
        color: #FF6B6B;
    }

    .vs-pill {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 215, 0, 0.16);
        border: 2px solid rgba(255, 215, 0, 0.42);
        color: #ffd86b;
        font-weight: 900;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
    }

    .points-box {
        padding: 14px 16px;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        text-align: center;
    }

    .points-label {
        display: block;
        margin-bottom: 6px;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #b0bec5;
        font-weight: 800;
    }

    .points-value {
        font-size: 1.6rem;
        font-weight: 900;
    }

    .points-value.positive {
        color: #4ECDC4;
    }

    .points-value.negative {
        color: #FF6B6B;
    }

    .points-value.neutral {
        color: #cfd8dc;
    }

    .footer-note {
        text-align: center;
        color: #94a9b5;
        font-size: 0.92rem;
        padding-top: 4px;
    }

    @keyframes bounce {
        0%, 80%, 100% { transform: scale(0.35); opacity: 0.45; }
        40% { transform: scale(1); opacity: 1; }
    }

    @media (max-width: 860px) {
        .waiting-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        body {
            padding: 12px;
        }

        .waiting-card,
        .score-card,
        .waiting-header {
            border-radius: 20px;
        }

        .waiting-card {
            padding: 18px;
        }

        .duel-scoreboard {
            grid-template-columns: 1fr;
        }

        .vs-pill {
            margin: 0 auto;
        }

        .player-result-box {
            align-items: flex-start;
        }

        .player-result-icon {
            width: 62px;
            height: 62px;
            min-width: 62px;
            font-size: 1.7rem;
        }
    }
</style>

<div class="waiting-shell">
    <div class="waiting-header">
        <div class="waiting-badge">⏳ {{ __('Transition Duo') }}</div>
        <h1 class="waiting-title">{{ __('Prochaine question') }}</h1>
        <p class="waiting-subtitle">
            {{ __('Le résultat du round est enregistré. Le serveur de jeu prépare maintenant la suite de la partie.') }}
        </p>
    </div>

    <div class="waiting-grid">
        <section class="waiting-card">
            <div class="round-row">
                <div class="round-pill">
                    🧠 {{ __('Round') }} {{ $roundValue }} / {{ $totalValue }}
                </div>
                <div class="state-pill">
                    🔄 {{ __('Synchronisation en cours') }}
                </div>
            </div>

            <div class="player-result-box {{ $playerWasCorrect ? 'correct' : 'incorrect' }}">
                <div class="player-result-icon">
                    {{ $playerWasCorrect ? '✓' : '✗' }}
                </div>

                <div class="player-result-text">
                    <h2 class="player-result-title">
                        {{ $playerWasCorrect ? __('Bonne réponse') : __('Réponse incorrecte') }}
                    </h2>

                    <p class="player-result-desc">
                        @if($playerWasCorrect)
                            {{ __('Votre réponse a bien été comptabilisée. Patientez pendant que le prochain round se met en place.') }}
                        @else
                            {{ __('Le round est terminé. Patientez pendant que le système synchronise la suite de la manche.') }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="waiting-animation-box">
                <p class="waiting-main-text">
                    {{ __('En attente du prochain round...') }}
                </p>

                <div class="dots" aria-hidden="true">
                    <div class="dot"></div>
                    <div class="dot"></div>
                    <div class="dot"></div>
                </div>

                <p class="waiting-note">
                    {{ __('Cette page est une transition visuelle seulement. Le gameplay reste piloté par le serveur Node.') }}
                </p>
            </div>
        </section>

        <aside class="score-card">
            <h2 class="score-title">{{ __('Score du duel') }}</h2>

            <div class="duel-scoreboard">
                <div class="score-side player">
                    <div class="avatar-frame">
                        <img src="{{ $playerAvatar }}" alt="{{ $playerDisplayName }}">
                    </div>
                    <div class="player-label">{{ $playerDisplayName }}</div>
                    <div class="score-number">{{ $playerScoreValue }}</div>
                </div>

                <div class="vs-pill">VS</div>

                <div class="score-side opponent">
                    <div class="avatar-frame">
                        <img src="{{ $opponentAvatar }}" alt="{{ $opponentDisplayName }}">
                    </div>
                    <div class="player-label">{{ $opponentDisplayName }}</div>
                    <div class="score-number">{{ $opponentScoreValue }}</div>
                </div>
            </div>

            <div class="points-box">
                <span class="points-label">{{ __('Points du dernier round') }}</span>
                <div class="points-value {{ $pointsValue > 0 ? 'positive' : ($pointsValue < 0 ? 'negative' : 'neutral') }}">
                    {{ $pointsValue > 0 ? '+' : '' }}{{ $pointsValue }}
                </div>
            </div>
        </aside>
    </div>

    <div class="footer-note">
        {{ __('Phase d’attente dédiée au Duo. Aucune logique Firebase ne doit intervenir ici.') }}
    </div>
</div>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const roomId = @json($room_id ?? '');
    const jwtToken = @json($jwt_token ?? '');
    const playerId = @json($playerId ?? auth()->id() ?? '');
    const playerName = @json($playerName ?? ($player_info['name'] ?? 'Joueur'));
    const playerAvatar = @json($playerAvatarPath ?? ($player_info['avatar'] ?? ''));
    const playerDivision = @json($playerDivision ?? null);

    function getGameServerUrl() {
        return window.location.origin;
    }

    if (!roomId || !jwtToken) {
        console.warn('[DuoWaiting] Missing roomId or jwtToken');
        return;
    }

    if (typeof DuoSocketClient === 'undefined') {
        console.error('[DuoWaiting] DuoSocketClient unavailable');
        return;
    }

    try {
        const gameServerUrl = getGameServerUrl();
        console.log('[DuoWaiting] Connecting to game server:', gameServerUrl);

        await DuoSocketClient.connect(gameServerUrl, jwtToken);

        DuoSocketClient.joinRoom(roomId, null, {
            playerId: String(playerId),
            playerName: playerName,
            avatarId: playerAvatar,
            division: playerDivision
        });

        console.log('[DuoWaiting] Connected and joined room:', roomId);

        DuoSocketClient.on('phase_changed', (data) => {
            console.log('[DuoWaiting] phase_changed:', data);

            if (!data || !data.phase) {
                return;
            }

            if (data.phase === 'QUESTION_DISPLAY' || data.phase === 'BUZZ_WINDOW' || data.phase === 'question') {
                window.location.href = "{{ route('game.duo.question') }}";
                return;
            }

            if (data.phase === 'ANSWER_REVEAL' || data.phase === 'answer') {
                window.location.href = "{{ route('game.duo.answer') }}";
                return;
            }

            if (data.phase === 'ROUND_RESULT' || data.phase === 'result') {
                window.location.href = "{{ route('game.duo.result') }}";
                return;
            }

            if (data.phase === 'MATCH_RESULT' || data.phase === 'match_result') {
                window.location.href = "{{ route('game.duo.match-result') }}";
            }
        });

        DuoSocketClient.on('error', (error) => {
            console.error('[DuoWaiting] Socket error:', error);
        });

        DuoSocketClient.on('disconnect', (reason) => {
            console.warn('[DuoWaiting] Disconnected:', reason);
        });

    } catch (err) {
        console.error('[DuoWaiting] Socket init failed:', err.message);
    }
});
</script>
@endsection
