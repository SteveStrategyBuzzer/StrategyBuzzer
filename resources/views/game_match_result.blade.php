@extends('layouts.app')

@php
$mode = $params['mode'] ?? 'duo';
$matchResult = $params['match_result'] ?? [];
$opponentInfo = $params['opponent_info'] ?? [];

$playerWon = $matchResult['player_won'] ?? false;
$isDraw = $matchResult['is_draw'] ?? false;
$playerRoundsWon = $matchResult['player_rounds_won'] ?? 0;
$opponentRoundsWon = $matchResult['opponent_rounds_won'] ?? 0;
$playerTotalScore = $matchResult['player_total_score'] ?? 0;
$opponentTotalScore = $matchResult['opponent_total_score'] ?? 0;
$coinsEarned = $matchResult['coins_earned'] ?? 0;
$xpEarned = $matchResult['xp_earned'] ?? 0;
$divisionPoints = $matchResult['division_points'] ?? 0;
$newDivision = $matchResult['new_division'] ?? null;
$promoted = $matchResult['promoted'] ?? false;
$demoted = $matchResult['demoted'] ?? false;

$opponentName = $opponentInfo['name'] ?? __('Adversaire');
$opponentAvatar = $opponentInfo['avatar'] ?? 'default';
$opponentDivision = $opponentInfo['division'] ?? 'Bronze';
$opponentLevel = $opponentInfo['level'] ?? 1;

if (strpos($opponentAvatar, 'http') === false && strpos($opponentAvatar, 'images/') === false) {
    $opponentAvatar = asset("images/avatars/standard/{$opponentAvatar}.png");
} elseif (strpos($opponentAvatar, 'images/') === 0) {
    $opponentAvatar = asset($opponentAvatar);
}

$resultClass = $playerWon ? 'victory' : ($isDraw ? 'draw' : 'defeat');
$resultIcon = $playerWon ? '🏆' : ($isDraw ? '🤝' : '😔');
$resultTitle = $playerWon ? __('Victoire') : ($isDraw ? __('Égalité') : __('Défaite'));
$resultColor = $playerWon ? '#11998e, #38ef7d' : ($isDraw ? '#667eea, #764ba2' : '#e74c3c, #c0392b');

$modeLabel = $mode === 'solo' ? 'Solo' : ($mode === 'duo' ? 'Duo' : ($mode === 'league_individual' ? 'League' : 'Match'));
$returnRoute = $mode === 'solo' ? route('solo') : ($mode === 'duo' ? route('duo.lobby') : ($mode === 'league_individual' ? route('league.individual.index') : route('menu')));

$newLevel = $matchResult['new_level'] ?? null;
$currentLevel = $matchResult['current_level'] ?? ($opponentLevel ?? 1);
$nextOpponentName = $matchResult['next_opponent_name'] ?? null;
$duoFullUnlocked = $matchResult['duo_full_unlocked'] ?? false;
$isBoss = $opponentInfo['is_boss'] ?? false;
$hasStrategeBonus = $matchResult['has_stratege_bonus'] ?? false;
$coinsBonus = $matchResult['coins_bonus'] ?? 0;
$remainingLives = $matchResult['remaining_lives'] ?? null;
$hasLives = $matchResult['has_lives'] ?? true;
$cooldownTime = $matchResult['cooldown_time'] ?? null;
$nextLifeRegen = $matchResult['next_life_regen'] ?? null;
$roundSummaries = $matchResult['round_summaries'] ?? [];
$partyEfficiency = $matchResult['party_efficiency'] ?? ($matchResult['global_efficiency'] ?? 0);
$totalCorrect = $matchResult['total_correct'] ?? 0;
$totalIncorrect = $matchResult['total_incorrect'] ?? 0;
$totalUnanswered = $matchResult['total_unanswered'] ?? 0;
@endphp

@section('title', $resultTitle . ' - StrategyBuzzer')

@section('content')
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, {{ $resultColor }});
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .result-container {
        background: rgba(255, 255, 255, 0.98);
        border-radius: 25px;
        padding: 40px;
        max-width: 650px;
        width: 100%;
        text-align: center;
        box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.6s ease-out;
    }
    
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .result-icon {
        font-size: 5rem;
        margin-bottom: 15px;
        animation: {{ $playerWon ? 'bounce' : 'pulse' }} 1s ease infinite;
    }
    
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-20px); }
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .result-title {
        font-size: 2.8rem;
        font-weight: 900;
        background: linear-gradient(135deg, {{ $resultColor }});
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 10px;
    }
    
    .mode-badge {
        display: inline-block;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 6px 20px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 700;
        margin-bottom: 25px;
    }
    
    .opponent-section {
        background: #f7f9fc;
        padding: 25px;
        border-radius: 20px;
        margin: 20px 0;
    }
    
    .opponent-label {
        font-size: 1rem;
        color: #666;
        margin-bottom: 15px;
    }
    
    .opponent-card {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
    }
    
    .opponent-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #667eea;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
    }
    
    .opponent-info {
        text-align: left;
    }
    
    .opponent-name {
        font-size: 1.6rem;
        font-weight: 800;
        color: #333;
        margin-bottom: 5px;
    }
    
    .opponent-division {
        font-size: 0.95rem;
        color: #667eea;
        font-weight: 600;
    }
    
    .score-battle {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 30px;
        margin: 25px 0;
    }
    
    .score-player, .score-opponent {
        padding: 20px 35px;
        border-radius: 15px;
        min-width: 120px;
    }
    
    .score-player {
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
        color: white;
    }
    
    .score-opponent {
        background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
        color: white;
    }
    
    .score-label {
        font-size: 0.85rem;
        opacity: 0.9;
        margin-bottom: 5px;
    }
    
    .score-value {
        font-size: 2.5rem;
        font-weight: 900;
    }
    
    .score-vs {
        font-size: 1.5rem;
        font-weight: 900;
        color: #667eea;
    }
    
    .rounds-display {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin: 20px 0;
    }
    
    .round-indicator {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.2rem;
        color: white;
    }
    
    .round-indicator.won {
        background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
    }
    
    .round-indicator.lost {
        background: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
    }
    
    .round-indicator.draw {
        background: linear-gradient(135deg, #FFC107 0%, #FF9800 100%);
    }
    
    .rewards-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 20px;
        margin: 25px 0;
    }
    
    .rewards-title {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 20px;
    }
    
    .rewards-grid {
        display: flex;
        justify-content: center;
        gap: 30px;
        flex-wrap: wrap;
    }
    
    .reward-item {
        text-align: center;
    }
    
    .reward-icon {
        font-size: 2rem;
        margin-bottom: 5px;
    }
    
    .reward-value {
        font-size: 1.5rem;
        font-weight: 900;
    }
    
    .reward-label {
        font-size: 0.8rem;
        opacity: 0.9;
    }
    
    .division-change {
        margin-top: 20px;
        padding: 15px;
        border-radius: 15px;
        font-weight: 700;
    }
    
    .division-change.promoted {
        background: rgba(76, 175, 80, 0.3);
        color: #4CAF50;
    }
    
    .division-change.demoted {
        background: rgba(244, 67, 54, 0.3);
        color: #f44336;
    }
    
    .action-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
        margin-top: 30px;
    }
    
    .btn {
        padding: 18px 40px;
        font-size: 1.1rem;
        font-weight: 700;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, {{ $resultColor }});
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
    }
    
    .btn-secondary {
        background: #e0e0e0;
        color: #333;
    }
    
    .btn-secondary:hover {
        background: #d0d0d0;
        transform: translateY(-2px);
    }
    
    @media (max-width: 600px) {
        .result-container {
            padding: 25px;
        }
        
        .result-title {
            font-size: 2rem;
        }
        
        .score-battle {
            flex-direction: column;
            gap: 15px;
        }
        
        .score-vs {
            display: none;
        }
        
        .opponent-card {
            flex-direction: column;
            text-align: center;
        }
        
        .opponent-info {
            text-align: center;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
    }
</style>

<div class="result-container">
    <div class="result-icon">{{ $resultIcon }}</div>
    <h1 class="result-title">{{ $resultTitle }}</h1>
    <span class="mode-badge">{{ $modeLabel }}</span>
    
    <div class="opponent-section">
        <div class="opponent-label">{{ $playerWon ? __('Vous avez battu') : ($isDraw ? __('Match nul contre') : __('Vous avez perdu contre')) }}</div>
        <div class="opponent-card">
            <img src="{{ $opponentAvatar }}" alt="{{ $opponentName }}" class="opponent-avatar" onerror="this.src='{{ asset('images/avatars/standard/default.png') }}'">
            <div class="opponent-info">
                <div class="opponent-name">{{ $opponentName }}</div>
                <div class="opponent-division">{{ $opponentDivision }} - {{ __('Niveau') }} {{ $opponentLevel }}</div>
            </div>
        </div>
    </div>
    
    <div class="score-battle">
        <div class="score-player">
            <div class="score-label">{{ __('Vous') }}</div>
            <div class="score-value">{{ $playerTotalScore }}</div>
        </div>
        <span class="score-vs">VS</span>
        <div class="score-opponent">
            <div class="score-label">{{ $opponentName }}</div>
            <div class="score-value">{{ $opponentTotalScore }}</div>
        </div>
    </div>
    
    <div class="rounds-display">
        @for($i = 0; $i < 3; $i++)
            @php
                $roundClass = 'pending';
                $roundText = '-';
                if ($i < $playerRoundsWon + $opponentRoundsWon) {
                    if ($i < $playerRoundsWon) {
                        $roundClass = 'won';
                        $roundText = '✓';
                    } else {
                        $roundClass = 'lost';
                        $roundText = '✗';
                    }
                }
            @endphp
            <div class="round-indicator {{ $roundClass }}">{{ $roundText }}</div>
        @endfor
    </div>
    
    @if($coinsEarned > 0 || $xpEarned > 0 || $divisionPoints != 0)
    <div class="rewards-section">
        <div class="rewards-title">{{ __('Récompenses') }}</div>
        <div class="rewards-grid">
            @if($coinsEarned > 0)
            <div class="reward-item">
                <div class="reward-icon">🪙</div>
                <div class="reward-value">+{{ $coinsEarned }}</div>
                <div class="reward-label">{{ __('Pièces') }}</div>
            </div>
            @endif
            @if($xpEarned > 0)
            <div class="reward-item">
                <div class="reward-icon">⭐</div>
                <div class="reward-value">+{{ $xpEarned }}</div>
                <div class="reward-label">XP</div>
            </div>
            @endif
            @if($divisionPoints != 0)
            <div class="reward-item">
                <div class="reward-icon">{{ $divisionPoints > 0 ? '📈' : '📉' }}</div>
                <div class="reward-value">{{ $divisionPoints > 0 ? '+' : '' }}{{ $divisionPoints }}</div>
                <div class="reward-label">{{ __('Points Division') }}</div>
            </div>
            @endif
        </div>
        
        @if($promoted)
        <div class="division-change promoted">
            🎉 {{ __('Promu en') }} {{ $newDivision }} !
        </div>
        @elseif($demoted)
        <div class="division-change demoted">
            {{ __('Rétrogradé en') }} {{ $newDivision }}
        </div>
        @endif
    </div>
    @endif
    
    @if($mode === 'solo')
    @if($playerWon && $newLevel)
    <div style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 20px; border-radius: 15px; margin: 20px 0; box-shadow: 0 5px 20px rgba(17, 153, 142, 0.4);">
        <div style="color: white; text-align: center;">
            <div style="font-size: 1.8rem; font-weight: 900; margin-bottom: 10px;">
                🎉 {{ __('Niveau') }} {{ $newLevel }} {{ __('débloqué') }} !
            </div>
            @if($nextOpponentName)
            <div style="font-size: 1.1rem; opacity: 0.9;">
                {{ __('Prochain adversaire') }}: <strong>{{ $nextOpponentName }}</strong>
            </div>
            @endif
        </div>
    </div>
    @endif
    
    @if($duoFullUnlocked)
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 15px; margin: 20px 0; box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);">
        <div style="color: white; text-align: center;">
            <div style="font-size: 2rem; margin-bottom: 10px;">🎮</div>
            <div style="font-size: 1.3rem; font-weight: 700;">{{ __('Mode Duo Débloqué !') }}</div>
            <div style="font-size: 0.9rem; opacity: 0.9; margin-top: 5px;">{{ __('Affrontez de vrais joueurs en temps réel') }}</div>
        </div>
    </div>
    @endif
    
    @if(!empty($roundSummaries))
    <div style="background: {{ $playerWon ? 'rgba(46, 204, 113, 0.1)' : 'rgba(231, 76, 60, 0.1)' }}; padding: 20px; border-radius: 15px; margin: 25px 0;">
        <div style="font-size: 1.3rem; font-weight: 700; color: #333; margin-bottom: 15px;">📊 {{ __('Statistiques par Manche') }}</div>
        
        @foreach($roundSummaries as $roundNum => $roundStats)
        <div style="background: white; padding: 15px; border-radius: 10px; margin-bottom: 15px; border-left: 4px solid {{ $playerWon ? '#11998e' : '#e74c3c' }};">
            <div style="font-weight: 700; color: {{ $playerWon ? '#11998e' : '#e74c3c' }}; margin-bottom: 10px;">🏆 {{ __('Manche') }} {{ $roundNum }}</div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; font-size: 0.9rem;">
                <div>
                    <span style="color: #666;">✅ {{ __('Réussi') }}:</span>
                    <strong style="color: #2ECC71;">{{ $roundStats['correct'] ?? 0 }}/{{ $roundStats['questions'] ?? 0 }}</strong>
                </div>
                <div>
                    <span style="color: #666;">❌ {{ __('Échec') }}:</span>
                    <strong style="color: #E74C3C;">{{ $roundStats['wrong'] ?? 0 }}/{{ $roundStats['questions'] ?? 0 }}</strong>
                </div>
                <div>
                    <span style="color: #666;">⏭️ {{ __('Sans réponse') }}:</span>
                    <strong style="color: #95a5a6;">{{ $roundStats['unanswered'] ?? 0 }}/{{ $roundStats['questions'] ?? 0 }}</strong>
                </div>
                <div>
                    <span style="color: #666;">📈 {{ __('Efficacité') }}:</span>
                    <strong style="color: {{ $playerWon ? '#11998e' : '#e74c3c' }};">{{ number_format($roundStats['efficiency'] ?? 0, 1) }}%</strong>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
    
    @if(!$playerWon && $remainingLives !== null)
    <div style="background: #f7f9fc; padding: 20px; border-radius: 15px; margin: 20px 0;">
        <div style="text-align: center;">
            <div style="font-size: 1.1rem; color: #666; margin-bottom: 10px;">{{ __('Vies restantes') }}</div>
            <div style="font-size: 2.5rem;">
                @for($i = 0; $i < $remainingLives; $i++)❤️@endfor
                @for($i = $remainingLives; $i < 3; $i++)🖤@endfor
            </div>
            @if(!$hasLives && $nextLifeRegen)
            <div style="font-size: 0.9rem; color: #e74c3c; margin-top: 10px;">
                {{ __('Prochaine vie dans') }}: <span id="life-countdown" data-regen="{{ $nextLifeRegen }}"></span>
            </div>
            @endif
        </div>
    </div>
    @endif
    @endif
    
    <div class="action-buttons">
        <a href="{{ $returnRoute }}" class="btn btn-primary">{{ __('Rejouer') }}</a>
        <a href="{{ route('menu') }}" class="btn btn-secondary">{{ __('Menu') }}</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($playerWon)
    if (typeof confetti !== 'undefined') {
        confetti({
            particleCount: 100,
            spread: 70,
            origin: { y: 0.6 }
        });
    }
    @endif
});
</script>
@endsection
