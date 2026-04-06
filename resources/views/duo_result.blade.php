@extends('layouts.game')

@section('game-data')
<script>
window.MATCH_ID          = @json((string)($match_id ?? ''));
window.ROOM_ID           = @json((string)($room_id ?? ''));
window.LOBBY_CODE        = @json((string)($lobby_code ?? ''));
window.JWT_TOKEN         = @json((string)($jwt_token ?? ''));
window.CURRENT_USER_ID   = @json((string)(auth()->id() ?? ''));
window.TOTAL_QUESTIONS   = {{ (int)($totalQuestions ?? 10) }};
window.NO_SOCKET_OVERLAY = true;
window.QUESTION_URL      = @json(route('game.duo.question'));
window.RESULT_URL        = @json(route('game.duo.result'));
window.MATCH_RESULT_URL  = @json(route('game.duo.match-result'));
</script>
@endsection

@section('content')
@php
$mode = 'duo';
@endphp

<style>
    body {
        background: linear-gradient(135deg, #0F2027 0%, #203A43 50%, #2C5364 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px;
        margin: 0;
        overflow-x: hidden;
    }
    
    .result-container {
        max-width: 800px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 15px;
        padding: 15px;
    }
    
    .result-header {
        text-align: center;
        padding: 20px;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 20px;
        border: 2px solid;
        animation: fadeIn 0.5s ease-out;
    }
    
    .result-header.result-correct {
        border-color: rgba(78, 205, 196, 0.5);
        background: rgba(78, 205, 196, 0.1);
    }
    
    .result-header.result-incorrect {
        border-color: rgba(255, 107, 107, 0.5);
        background: rgba(255, 107, 107, 0.1);
    }
    
    .round-indicator {
        font-size: 0.9rem;
        color: #4ECDC4;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
    }
    
    .result-icon {
        font-size: 60px;
        margin-bottom: 10px;
        animation: scaleIn 0.5s ease-out;
    }
    
    .result-title {
        font-size: 1.8rem;
        font-weight: 900;
        margin-bottom: 10px;
        animation: slideDown 0.6s ease-out;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    
    .result-correct .result-title {
        color: #4ECDC4;
        text-shadow: 0 0 30px rgba(78, 205, 196, 0.8);
    }
    
    .result-incorrect .result-title {
        color: #FF6B6B;
        text-shadow: 0 0 30px rgba(255, 107, 107, 0.8);
    }
    
    .points-earned {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .points-earned.positive {
        color: #4ECDC4;
    }
    
    .points-earned.negative {
        color: #FF6B6B;
    }
    
    .points-earned.neutral {
        color: #95a5a6;
    }
    
    .score-battle {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        margin: 15px 0;
        animation: fadeIn 0.8s ease-out;
    }
    
    .score-player, .score-opponent {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        padding: 15px 25px;
        border-radius: 15px;
        backdrop-filter: blur(10px);
        min-width: 120px;
    }
    
    .score-player {
        background: rgba(78, 205, 196, 0.15);
        border: 3px solid #4ECDC4;
        box-shadow: 0 8px 30px rgba(78, 205, 196, 0.3);
    }
    
    .score-opponent {
        background: rgba(255, 107, 107, 0.15);
        border: 3px solid #FF6B6B;
        box-shadow: 0 8px 30px rgba(255, 107, 107, 0.3);
    }
    
    .player-avatar-small {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 3px solid #4ECDC4;
        object-fit: cover;
    }
    
    .opponent-avatar-small {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 3px solid #FF6B6B;
        object-fit: cover;
    }
    
    .opponent-avatar-empty {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 3px solid #FF6B6B;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 107, 107, 0.2);
        font-size: 1.5rem;
        font-weight: 900;
        color: #FF6B6B;
    }
    
    .score-label {
        font-size: 0.85rem;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .score-player .score-label {
        color: #4ECDC4;
    }
    
    .score-opponent .score-label {
        color: #FF6B6B;
    }
    
    .score-number {
        font-size: 2.2rem;
        font-weight: 900;
        line-height: 1;
    }
    
    .score-player .score-number {
        color: #4ECDC4;
        text-shadow: 0 0 20px rgba(78, 205, 196, 0.5);
    }
    
    .score-opponent .score-number {
        color: #FF6B6B;
        text-shadow: 0 0 20px rgba(255, 107, 107, 0.5);
    }
    
    .vs-divider {
        font-size: 1.2rem;
        font-weight: bold;
        color: #FFD700;
        background: rgba(255, 215, 0, 0.2);
        padding: 10px;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #FFD700;
        box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
    }
    
    .result-answers {
        background: rgba(0, 0, 0, 0.4);
        padding: 15px;
        border-radius: 15px;
        animation: fadeIn 1s ease-out;
        border: 2px solid rgba(255, 255, 255, 0.1);
    }
    
    .answer-display {
        padding: 12px 15px;
        border-radius: 12px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
        backdrop-filter: blur(5px);
    }
    
    .answer-display:last-child {
        margin-bottom: 0;
    }
    
    .answer-correct {
        background: rgba(78, 205, 196, 0.25);
        border: 2px solid #4ECDC4;
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.3);
    }
    
    .answer-incorrect {
        background: rgba(255, 107, 107, 0.25);
        border: 2px solid #FF6B6B;
        box-shadow: 0 5px 20px rgba(255, 107, 107, 0.3);
    }
    
    .answer-label {
        opacity: 0.9;
        font-size: 0.9rem;
        font-weight: 600;
        flex-shrink: 0;
        min-width: 120px;
    }
    
    .answer-text {
        flex: 1;
        text-align: left;
        font-weight: 500;
    }
    
    .answer-icon {
        font-size: 1.5rem;
    }
    
    .progress-info {
        background: rgba(0, 0, 0, 0.3);
        border: 2px solid rgba(78, 205, 196, 0.3);
        border-radius: 12px;
        padding: 12px;
        backdrop-filter: blur(10px);
    }
    
    .stats-columns {
        display: flex;
        gap: 12px;
    }
    
    .stats-column {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .stats-column.left {
        border-right: 1px solid rgba(78, 205, 196, 0.3);
        padding-right: 12px;
    }
    
    .stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 8px;
        background: rgba(78, 205, 196, 0.08);
        border-radius: 6px;
    }
    
    .stat-label {
        font-size: 0.75rem;
        color: #4ECDC4;
        font-weight: 600;
    }
    
    .stat-value {
        font-size: 0.85rem;
        color: white;
        font-weight: bold;
    }
    
    .did-you-know {
        background: rgba(102, 126, 234, 0.15);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 12px;
        padding: 15px;
        backdrop-filter: blur(10px);
    }
    
    .did-you-know-title {
        font-size: 1rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 10px;
        text-align: center;
    }
    
    .did-you-know-content {
        font-size: 0.9rem;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.9);
        text-align: center;
        font-style: italic;
    }
    
    .skills-container {
        background: rgba(102, 126, 234, 0.15);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 15px;
        padding: 15px;
    }
    
    .skills-title {
        font-size: 1rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 12px;
        text-align: center;
    }
    
    .skills-grid {
        display: grid;
        gap: 10px;
    }
    
    .skill-item {
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid rgba(102, 126, 234, 0.3);
        border-radius: 12px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s;
    }
    
    .skill-item.used {
        background: rgba(255, 215, 0, 0.1);
        border-color: gold;
    }
    
    .skill-icon {
        font-size: 1.8rem;
    }
    
    .skill-info {
        flex: 1;
        text-align: left;
    }
    
    .skill-name {
        font-size: 0.9rem;
        font-weight: 600;
        color: #667eea;
    }
    
    .skill-desc {
        font-size: 0.75rem;
        opacity: 0.8;
        margin-top: 2px;
    }
    
    .skill-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .skill-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.5);
    }
    
    .skill-btn:disabled {
        background: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.5);
        cursor: not-allowed;
    }
    
    .skill-used-badge {
        background: gold;
        color: #1a1a2e;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
    }
    
    .status-section {
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 15px;
    }
    
    .status-title {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-align: center;
    }
    
    .status-row {
        display: flex;
        justify-content: space-between;
        gap: 15px;
    }
    
    .status-item {
        flex: 1;
        padding: 12px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .status-item.player {
        background: rgba(78, 205, 196, 0.1);
        border: 2px solid rgba(78, 205, 196, 0.3);
    }
    
    .status-item.opponent {
        background: rgba(255, 107, 107, 0.1);
        border: 2px solid rgba(255, 107, 107, 0.3);
    }
    
    .status-item.ready {
        background: rgba(46, 204, 113, 0.2);
        border-color: #2ECC71;
    }
    
    .status-item.ready .status-icon {
        color: #2ECC71;
    }
    
    .status-item.waiting .status-icon {
        color: #F39C12;
        animation: pulse 1.5s infinite;
    }
    
    .status-icon {
        font-size: 1.3rem;
    }
    
    .status-text {
        font-size: 0.85rem;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .result-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 10px;
    }
    
    .btn-go {
        width: 100%;
        padding: 16px 30px;
        border-radius: 15px;
        font-size: 1.2rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 2px;
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
        color: white;
        box-shadow: 0 8px 30px rgba(78, 205, 196, 0.4);
    }
    
    .btn-go:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 12px 40px rgba(78, 205, 196, 0.6);
    }
    
    .btn-go:disabled {
        background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        cursor: not-allowed;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
    }
    
    .waiting-message {
        text-align: center;
        padding: 15px;
        background: rgba(255, 215, 0, 0.15);
        border: 2px solid rgba(255, 215, 0, 0.4);
        border-radius: 12px;
        color: #FFD700;
        font-weight: 600;
        animation: pulse-waiting 2s ease-in-out infinite;
        display: none;
    }
    
    .waiting-message.show {
        display: block;
    }
    
    @keyframes pulse-waiting {
        0%, 100% {
            opacity: 1;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
        }
        50% {
            opacity: 0.8;
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.5);
        }
    }
    
    .waiting-dots::after {
        content: '';
        animation: dots-content 1.5s steps(4, end) infinite;
    }
    
    @keyframes dots-content {
        0% { content: ''; }
        25% { content: '.'; }
        50% { content: '..'; }
        75%, 100% { content: '...'; }
    }
    
    .connection-status {
        position: fixed;
        top: 10px;
        right: 10px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        z-index: 1000;
    }
    
    .connection-status.connected {
        background: rgba(78, 205, 196, 0.3);
        color: #4ECDC4;
    }
    
    .connection-status.disconnected {
        background: rgba(255, 107, 107, 0.3);
        color: #FF6B6B;
    }
    
    .connection-status.connecting {
        background: rgba(255, 215, 0, 0.3);
        color: #FFD700;
    }
    
    @keyframes scaleIn {
        from {
            transform: scale(0) rotate(-180deg);
            opacity: 0;
        }
        to {
            transform: scale(1) rotate(0deg);
            opacity: 1;
        }
    }
    
    @keyframes slideDown {
        from {
            transform: translateY(-30px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(15px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @media (max-width: 768px) {
        .result-title {
            font-size: 1.5rem;
        }
        
        .score-battle {
            gap: 15px;
        }
        
        .score-player, .score-opponent {
            padding: 12px 18px;
            min-width: 100px;
        }
        
        .player-avatar-small, .opponent-avatar-small, .opponent-avatar-empty {
            width: 50px;
            height: 50px;
        }
        
        .score-number {
            font-size: 1.8rem;
        }
        
        .vs-divider {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }
    }
    
    @media (max-width: 480px) {
        .result-container {
            padding: 10px;
            gap: 12px;
        }
        
        .result-title {
            font-size: 1.3rem;
        }
        
        .result-icon {
            font-size: 50px;
        }
        
        .score-player, .score-opponent {
            padding: 10px 15px;
            min-width: 90px;
        }
        
        .player-avatar-small, .opponent-avatar-small, .opponent-avatar-empty {
            width: 45px;
            height: 45px;
        }
        
        .score-number {
            font-size: 1.5rem;
        }
        
        .answer-label {
            min-width: 100px;
            font-size: 0.8rem;
        }
        
        .btn-go {
            padding: 14px 25px;
            font-size: 1rem;
        }
        
        .stats-columns {
            flex-direction: column;
            gap: 8px;
        }
        
        .stats-column.left {
            border-right: none;
            border-bottom: 1px solid rgba(78, 205, 196, 0.3);
            padding-right: 0;
            padding-bottom: 8px;
        }
    }
    
    @media (max-height: 600px) and (orientation: landscape) {
        .result-container {
            padding: 8px;
            gap: 10px;
        }
        
        .result-icon {
            font-size: 40px;
            margin-bottom: 5px;
        }
        
        .result-title {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        
        .score-battle {
            margin: 10px 0;
        }
        
        .player-avatar-small, .opponent-avatar-small, .opponent-avatar-empty {
            width: 40px;
            height: 40px;
        }
        
        .score-number {
            font-size: 1.4rem;
        }
    }
    
    .voice-mic-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #dc3545;
        border: 2px solid #c82333;
        font-size: 24px;
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s ease;
    }
    .voice-mic-btn.active {
        background: #28a745;
        border-color: #1e7e34;
    }
</style>

{{-- connection-status, voice-mic button: provided by layouts.game --}}

<div class="result-container">
    <div class="result-header {{ $wasCorrect ? 'result-correct' : 'result-incorrect' }}">
        <div class="round-indicator">{{ __('Question') }} {{ $currentQuestion ?? 1 }}/{{ $totalQuestions ?? 10 }}</div>
        <div class="result-icon">{{ $wasCorrect ? '✅' : '❌' }}</div>
        <div class="result-title">
            @if($wasCorrect)
                {{ __('Bonne réponse !') }}
            @else
                {{ __('Mauvaise réponse') }}
            @endif
        </div>
        <div class="points-earned {{ $pointsEarned > 0 ? 'positive' : ($pointsEarned < 0 ? 'negative' : 'neutral') }}">
            @if($pointsEarned > 0)
                +{{ $pointsEarned }} {{ __('points') }}
            @elseif($pointsEarned < 0)
                {{ $pointsEarned }} {{ __('points') }}
            @else
                {{ __('0 point') }}
            @endif
        </div>
    </div>
    
    <div class="score-battle">
        <div class="score-player">
            <img src="{{ $playerAvatarPath ?? asset('images/avatars/standard/default.png') }}" alt="{{ __('Votre avatar') }}" class="player-avatar-small">
            <div class="score-label">{{ __('Vous') }}</div>
            <div class="score-number" id="playerScore">{{ $playerScore ?? 0 }}</div>
        </div>
        
        <div class="vs-divider">VS</div>
        
        <div class="score-opponent">
            @if(!empty($opponentAvatarPath))
                <img src="{{ $opponentAvatarPath }}" alt="{{ __('Avatar adversaire') }}" class="opponent-avatar-small">
            @else
                <div class="opponent-avatar-empty">?</div>
            @endif
            <div class="score-label">{{ $opponentName ?? __('Adversaire') }}</div>
            <div class="score-number" id="opponentScore">{{ $opponentScore ?? 0 }}</div>
        </div>
    </div>
    
    @php
        // Skill Historien - Parchemin (history_corrects)
        $hasScrollSkill = false;
        $scrollSkillUsed = false;
        $playerBuzzed = $playerBuzzed ?? false;
        $playerPoints = $playerPoints ?? 0;
        if (isset($skills) && is_array($skills)) {
            foreach ($skills as $skill) {
                if (($skill['id'] ?? '') === 'history_corrects') {
                    $hasScrollSkill = true;
                    $scrollSkillUsed = $skill['used'] ?? false;
                    break;
                }
            }
        }
        // Parchemin disponible si: Historien + pas utilisé + a buzzé + erreur + points négatifs
        $scrollSkillAvailable = $hasScrollSkill && !$scrollSkillUsed && $playerBuzzed && !$wasCorrect && $playerPoints < 0;
        // Points à récupérer selon l'ordre de buzz
        $opponentFaster = $opponentFaster ?? false;
        $pointsToRecover = $opponentFaster ? 1 : 2;
    @endphp
    
    <div class="result-answers">
        @if(!empty($playerAnswer))
            <div class="answer-display {{ $wasCorrect ? 'answer-correct' : 'answer-incorrect' }}">
                <span class="answer-icon">{{ $wasCorrect ? '✓' : '✗' }}</span>
                <span class="answer-label">{{ __('Votre réponse') }} :</span>
                <span class="answer-text">{{ $playerAnswer }}</span>
            </div>
        @endif
        
        <div class="answer-display answer-correct {{ $scrollSkillAvailable ? 'scroll-skill-clickable' : '' }}" 
             @if($scrollSkillAvailable) onclick="useScrollSkill({{ $pointsToRecover }})" style="cursor: pointer;" @endif>
            <span class="answer-icon">✓</span>
            <span class="answer-label">{{ __('Bonne réponse') }} :</span>
            <span class="answer-text">@if($scrollSkillAvailable)📜 @endif{{ $question['correct_answer'] ?? $question['answer'] ?? '-' }}</span>
        </div>
    </div>
    
    @if($scrollSkillAvailable)
    <div id="scrollSkillPopup" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
         background: linear-gradient(135deg, #1a3a4a 0%, #2d5a6a 100%); padding: 30px; border-radius: 20px; 
         border: 3px solid #4ECDC4; box-shadow: 0 0 50px rgba(78, 205, 196, 0.5); z-index: 1000; text-align: center;">
        <div style="font-size: 3rem;">📜</div>
        <div style="font-size: 1.3rem; font-weight: 700; color: #fff; margin-top: 10px;">{{ __("L'histoire corrige") }}</div>
        <div id="scrollSkillPoints" style="font-size: 2rem; font-weight: 900; color: #FFD700; margin-top: 10px;">+{{ $pointsToRecover }} {{ __('points') }}</div>
    </div>
    @endif
    
    <div class="progress-info">
        <div class="stats-columns">
            <div class="stats-column left">
                <div class="stat-row">
                    <span class="stat-label">{{ __('Manche') }}</span>
                    <span class="stat-value">{{ $currentRound ?? 1 }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Question') }}</span>
                    <span class="stat-value">{{ $currentQuestion ?? 1 }}/{{ $totalQuestions ?? 10 }}</span>
                </div>
            </div>
            <div class="stats-column right">
                <div class="stat-row">
                    <span class="stat-label">{{ __('Votre score') }}</span>
                    <span class="stat-value" style="color: #4ECDC4;">{{ $playerScore ?? 0 }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Score adversaire') }}</span>
                    <span class="stat-value" style="color: #FF6B6B;">{{ $opponentScore ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>
    
    @if(!empty($question['fun_fact']))
        <div class="did-you-know">
            <div class="did-you-know-title">💡 {{ __('Le saviez-vous ?') }}</div>
            <div class="did-you-know-content">{{ $question['fun_fact'] }}</div>
        </div>
    @endif
    
    @if(!empty($skills) && count($skills) > 0)
    <div class="skills-container">
        <div class="skills-title">✨ {{ __('Compétences') }} {{ $avatarName ?? '' }} ✨</div>
        <div class="skills-grid">
            @foreach($skills as $skill)
            <div class="skill-item {{ ($skill['used'] ?? false) ? 'used' : '' }}">
                <span class="skill-icon">{{ $skill['icon'] ?? '🔮' }}</span>
                <div class="skill-info">
                    <div class="skill-name">{{ $skill['name'] ?? __('Compétence') }}</div>
                    @if(!empty($skill['description']))
                    <div class="skill-desc">{{ $skill['description'] }}</div>
                    @endif
                </div>
                @if($skill['used'] ?? false)
                    <span class="skill-used-badge">{{ __('Utilisé') }}</span>
                @else
                    <button class="skill-btn" onclick="activateSkill('{{ $skill['id'] ?? '' }}')">{{ __('Activer') }}</button>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
    
    {{-- Skills Challenger (reduce_time, shuffle_answers) --}}
    @if(($avatarName ?? '') === 'Challenger')
    @php
        // Skills scopés au match
        $matchId = $match_id ?? session('game_state.match_id', 0);
        $skillsKey = "duo_skills_{$matchId}";
        $matchSkills = session($skillsKey, [
            'used_skills' => [],
            'reduce_time_active' => false,
            'reduce_time_questions_left' => 0,
            'shuffle_answers_active' => false,
            'shuffle_answers_questions_left' => 0,
        ]);
        $reduceTimeUsed = in_array('reduce_time', $matchSkills['used_skills']);
        $reduceTimeActive = $matchSkills['reduce_time_active'];
        $reduceTimeQuestionsLeft = $matchSkills['reduce_time_questions_left'];
        $shuffleAnswersUsed = in_array('shuffle_answers', $matchSkills['used_skills']);
        $shuffleAnswersActive = $matchSkills['shuffle_answers_active'];
        $shuffleAnswersQuestionsLeft = $matchSkills['shuffle_answers_questions_left'];
    @endphp
    <div class="skills-container" style="border-color: rgba(255, 87, 34, 0.4); background: rgba(255, 87, 34, 0.15);">
        <div class="skills-title">⚔️ {{ __('Compétences Challenger') }} ⚔️</div>
        <div class="skills-grid">
            <!-- Skill: Chrono Réduit -->
            <div class="skill-item {{ $reduceTimeUsed ? 'used' : '' }}">
                <div class="skill-icon">⏱️</div>
                <div class="skill-info">
                    <div class="skill-name">{{ __('Chrono Réduit') }}</div>
                    <div class="skill-desc" style="font-size: 0.75rem; opacity: 0.7;">
                        @if($reduceTimeActive)
                            {{ $reduceTimeQuestionsLeft }} {{ __('questions restantes') }}
                        @else
                            {{ __('-2 sec pour l\'adversaire') }}
                        @endif
                    </div>
                </div>
                @if($reduceTimeUsed || $reduceTimeActive)
                    <div class="skill-used-badge">{{ $reduceTimeActive ? __('ACTIF') : __('UTILISÉ') }}</div>
                @else
                    <button class="skill-btn" onclick="useChallengerSkill('reduce_time')" style="background: linear-gradient(135deg, #ff5722 0%, #e64a19 100%);">{{ __('Activer') }}</button>
                @endif
            </div>
            
            <!-- Skill: Mélange Réponses -->
            <div class="skill-item {{ $shuffleAnswersUsed ? 'used' : '' }}">
                <div class="skill-icon">🔀</div>
                <div class="skill-info">
                    <div class="skill-name">{{ __('Mélange Réponses') }}</div>
                    <div class="skill-desc" style="font-size: 0.75rem; opacity: 0.7;">
                        @if($shuffleAnswersActive)
                            {{ $shuffleAnswersQuestionsLeft }} {{ __('questions restantes') }}
                        @else
                            {{ __('Réponses en mouvement') }}
                        @endif
                    </div>
                </div>
                @if($shuffleAnswersUsed || $shuffleAnswersActive)
                    <div class="skill-used-badge">{{ $shuffleAnswersActive ? __('ACTIF') : __('UTILISÉ') }}</div>
                @else
                    <button class="skill-btn" onclick="useChallengerSkill('shuffle_answers')" style="background: linear-gradient(135deg, #ff5722 0%, #e64a19 100%);">{{ __('Activer') }}</button>
                @endif
            </div>
        </div>
    </div>
    @endif
    
    <div class="status-section">
        <div class="status-title">{{ __('Statut des joueurs') }}</div>
        <div class="status-row">
            <div class="status-item player waiting" id="playerStatus">
                <span class="status-icon">⏳</span>
                <span class="status-text">{{ __('Vous') }} - {{ __('En attente') }}</span>
            </div>
            <div class="status-item opponent waiting" id="opponentStatus">
                <span class="status-icon">⏳</span>
                <span class="status-text">{{ $opponentName ?? __('Adversaire') }} - {{ __('En attente') }}</span>
            </div>
        </div>
    </div>
    
    <div class="result-actions">
        <button class="btn-go" id="btnGo">{{ __('GO') }}</button>
        <div class="waiting-message" id="waitingMessage">
            ⏳ {{ __('En attente de l\'autre joueur') }}<span class="waiting-dots"></span>
        </div>
    </div>
</div>

{{-- socket.io + DuoSocketClient: loaded by layouts.game --}}

<script>
(function() {
    'use strict';
    
    const MATCH_ID = '{{ $match_id ?? "" }}';
    const ROOM_ID = '{{ $room_id ?? "" }}';
    const LOBBY_CODE = '{{ $lobby_code ?? "" }}';
    const JWT_TOKEN = '{{ $jwt_token ?? "" }}';
    const CURRENT_QUESTION = {{ $currentQuestion ?? 1 }};
    const TOTAL_QUESTIONS = {{ $totalQuestions ?? 10 }};
    const CURRENT_PLAYER_ID = {{ $playerId ?? auth()->id() ?? 0 }};
    
    let isReady = false;
    let isRedirecting = false;
    
    const connectionStatus = document.getElementById('connectionStatus');
    const btnGo = document.getElementById('btnGo');
    const waitingMessage = document.getElementById('waitingMessage');
    const playerScoreEl = document.getElementById('playerScore');
    const opponentScoreEl = document.getElementById('opponentScore');
    const playerStatus = document.getElementById('playerStatus');
    const opponentStatus = document.getElementById('opponentStatus');
    
    function getGameServerUrl() {
        return window.location.origin;
    }
    
    function updateConnectionStatus(status) {
        connectionStatus.className = 'connection-status ' + status;
        switch(status) {
            case 'connected':
                connectionStatus.textContent = '{{ __("Connecté") }}';
                break;
            case 'disconnected':
                connectionStatus.textContent = '{{ __("Déconnecté") }}';
                break;
            case 'connecting':
                connectionStatus.textContent = '{{ __("Connexion...") }}';
                break;
        }
    }
    
    function setPlayerReady() {
        if (isReady) return;
        
        isReady = true;
        btnGo.disabled = true;
        btnGo.textContent = '{{ __("PRÊT !") }}';
        waitingMessage.classList.add('show');
        
        if (playerStatus) {
            playerStatus.classList.remove('waiting');
            playerStatus.classList.add('ready');
            playerStatus.querySelector('.status-icon').textContent = '✅';
            playerStatus.querySelector('.status-text').textContent = '{{ __("Vous") }} - {{ __("Prêt") }}';
        }
        
        if (DuoSocketClient.isConnected()) {
            DuoSocketClient.socket.emit('player_ready', {
                roomId: ROOM_ID || LOBBY_CODE,
                matchId: MATCH_ID
            });
            console.log('[DuoResult] Player ready sent');
        }
    }
    
    function setOpponentReady() {
        if (opponentStatus) {
            opponentStatus.classList.remove('waiting');
            opponentStatus.classList.add('ready');
            opponentStatus.querySelector('.status-icon').textContent = '✅';
            opponentStatus.querySelector('.status-text').textContent = '{{ $opponentName ?? __("Adversaire") }} - {{ __("Prêt") }}';
        }
    }
    
    function resetReadyStatus() {
        isReady = false;
        
        if (btnGo) {
            btnGo.disabled = false;
            btnGo.textContent = '{{ __("GO") }}';
        }
        
        if (waitingMessage) {
            waitingMessage.classList.remove('show');
        }
        
        if (playerStatus) {
            playerStatus.classList.remove('ready');
            playerStatus.classList.add('waiting');
            playerStatus.querySelector('.status-icon').textContent = '⏳';
            playerStatus.querySelector('.status-text').textContent = '{{ __("Vous") }} - {{ __("En attente") }}';
        }
        
        if (opponentStatus) {
            opponentStatus.classList.remove('ready');
            opponentStatus.classList.add('waiting');
            opponentStatus.querySelector('.status-icon').textContent = '⏳';
            opponentStatus.querySelector('.status-text').textContent = '{{ $opponentName ?? __("Adversaire") }} - {{ __("En attente") }}';
        }
    }
    
    function navigateToNextQuestion() {
        if (isRedirecting) return;
        isRedirecting = true;
        window.location.href = window.QUESTION_URL || "{{ route('game.duo.question') }}";
    }
    
    function navigateToRoundScoreboard() {
        if (isRedirecting) return;
        isRedirecting = true;
        window.location.href = window.RESULT_URL || "{{ route('game.duo.result') }}";
    }
    
    function navigateToFinalResults() {
        if (isRedirecting) return;
        isRedirecting = true;
        window.location.href = window.MATCH_RESULT_URL || "{{ route('game.duo.match-result') }}";
    }
    
    btnGo.addEventListener('click', setPlayerReady);
    
    // ── Named socket handlers (closures over IIFE vars) ──────────────────────
    // connect() + joinRoom() handled by GameplayRuntime — view-specific only
    function _onResultDisconnect(reason) {
        console.log('[DuoResult] Disconnected:', reason);
    }
    function _onResultError(error) {
        console.error('[DuoResult] Socket error:', error);
    }
    function _onResultRoundEnded(data) {
        console.log('[DuoResult] Round ended', data);
        navigateToRoundScoreboard();
    }
    function _onResultMatchEnded(data) {
        console.log('[DuoResult] Match ended', data);
        navigateToFinalResults();
    }
    function _onResultScoreUpdate(data) {
        console.log('[DuoResult] Score update', data);
        if (data.playerScore !== undefined)  { playerScoreEl.textContent  = data.playerScore; }
        if (data.opponentScore !== undefined) { opponentScoreEl.textContent = data.opponentScore; }
    }
    function _onResultPlayerReady(data) {
        console.log('[DuoResult] Player ready received', data);
        if (data && data.playerId && String(data.playerId) !== String(CURRENT_PLAYER_ID)) {
            setOpponentReady();
        }
    }
    function _onResultPhaseChanged(data) {
        console.log('[DuoResult] Phase changed', data);
        if (!data || !data.phase) { return; }
        if (data.phase === 'QUESTION_ACTIVE' || data.phase === 'QUESTION_DISPLAY' || data.phase === 'BUZZ_WINDOW' || data.phase === 'question') {
            navigateToNextQuestion(); return;
        }
        if (data.phase === 'MATCH_RESULT' || data.phase === 'match_result' || data.phase === 'MATCH_END' || data.phase === 'FINISHED') {
            navigateToFinalResults(); return;
        }
        resetReadyStatus();
    }
    function _onResultState(payload) {
        if (!payload) return;
        var phase = payload.state ? payload.state.phase : payload.phase;
        if (phase === 'QUESTION_ACTIVE' || phase === 'QUESTION_DISPLAY' || phase === 'BUZZ_WINDOW') {
            navigateToNextQuestion();
        } else if (phase === 'MATCH_END' || phase === 'FINISHED') {
            navigateToFinalResults();
        }
    }
    function _onResultBothReady(data) {
        console.log('[DuoResult] Both players ready', data);
        navigateToNextQuestion();
    }
    // Expose for the scripts section — .on() bindings done there after DuoSocketClient.js loads
    window._duoResultHandlers = {
        disconnect:    _onResultDisconnect,
        error:         _onResultError,
        round_ended:   _onResultRoundEnded,
        match_ended:   _onResultMatchEnded,
        score_update:  _onResultScoreUpdate,
        player_ready:  _onResultPlayerReady,
        phase_changed: _onResultPhaseChanged,
        state:         _onResultState,
        both_ready:    _onResultBothReady
    };

    window.addEventListener('beforeunload', function() {
        if (DuoSocketClient.isConnected()) {
            // keep shared lifecycle behavior
        }
    });
    
    window.activateSkill = function(skillId) {
        if (!skillId) return;
        
        console.log('[DuoResult] Activating skill:', skillId);
        
        if (DuoSocketClient.isConnected()) {
            DuoSocketClient.useSkill(skillId);
        }
        
        const btn = event.target;
        btn.disabled = true;
        btn.textContent = '{{ __("Activé") }}';
        btn.closest('.skill-item').classList.add('used');
    };
    
    // Skill Challenger - reduce_time et shuffle_answers
    window.useChallengerSkill = function(skillId) {
        if (!skillId) return;
        
        console.log('[DuoResult] Activating Challenger skill:', skillId);
        
        fetch("{{ route('game.duo.use-skill') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ skill_id: skillId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSkillMessage(data.message);
                // Recharger la page après 1.5s pour mettre à jour l'interface
                setTimeout(() => location.reload(), 1500);
            } else {
                showSkillMessage(data.message || 'Erreur lors de l\'activation du skill', false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showSkillMessage('Erreur lors de l\'activation du skill', false);
        });
    };
    
    function showSkillMessage(message, isSuccess = true) {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;
        
        const popup = document.createElement('div');
        popup.style.cssText = `
            background: ${isSuccess ? 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)' : 'linear-gradient(135deg, #2e1a1a 0%, #3e1616 100%)'};
            border: 2px solid ${isSuccess ? '#4CAF50' : '#f44336'};
            border-radius: 20px;
            padding: 30px 40px;
            text-align: center;
            max-width: 90%;
            animation: popIn 0.3s ease;
        `;
        popup.innerHTML = `
            <div style="font-size: 3rem; margin-bottom: 15px;">${isSuccess ? '✅' : '❌'}</div>
            <div style="color: white; font-size: 1.2rem; font-weight: 600;">${message}</div>
        `;
        
        overlay.appendChild(popup);
        document.body.appendChild(overlay);
        
        setTimeout(() => overlay.remove(), 2000);
    }
    
    // Skill Historien - Parchemin (L'histoire corrige)
    window.useScrollSkill = function(pointsToRecover) {
        console.log('[DuoResult] Using scroll skill for', pointsToRecover, 'points');
        
        // Émettre l'événement au serveur Socket.IO
        if (DuoSocketClient.isConnected()) {
            DuoSocketClient.socket.emit('activate_skill', {
                roomId: ROOM_ID || LOBBY_CODE,
                matchId: MATCH_ID,
                skillId: 'history_corrects',
                pointsToRecover: pointsToRecover
            });
        }
        
        // Afficher le popup de confirmation
        const popup = document.getElementById('scrollSkillPopup');
        if (popup) {
            popup.style.display = 'block';
            
            // Mettre à jour le score affiché
            const playerScoreEl = document.getElementById('playerScore');
            if (playerScoreEl) {
                const currentScore = parseInt(playerScoreEl.textContent) || 0;
                playerScoreEl.textContent = currentScore + pointsToRecover;
            }
            
            // Masquer après 2 secondes
            setTimeout(() => {
                popup.style.display = 'none';
            }, 2000);
            
            // Désactiver le clic sur la bonne réponse
            const correctAnswer = document.querySelector('.answer-correct.scroll-skill-clickable');
            if (correctAnswer) {
                correctAnswer.onclick = null;
                correctAnswer.style.cursor = 'default';
                correctAnswer.classList.remove('scroll-skill-clickable');
            }
        }
    };
})();
</script>

<script type="module">
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { getFirestore, doc, collection, addDoc, onSnapshot, query, where, deleteDoc, getDocs, getDoc, setDoc, serverTimestamp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js';

const firebaseConfig = {
    apiKey: "{{ config('services.firebase.api_key', 'AIzaSyC2D2lVq3D_lRFM3kvbLmLUFJpv8Dh35qU') }}",
    authDomain: "{{ config('services.firebase.project_id', 'strategybuzzer') }}.firebaseapp.com",
    projectId: "{{ config('services.firebase.project_id', 'strategybuzzer') }}",
    storageBucket: "{{ config('services.firebase.project_id', 'strategybuzzer') }}.appspot.com",
    messagingSenderId: "{{ config('services.firebase.messaging_sender_id', '681234567890') }}",
    appId: "{{ config('services.firebase.app_id', '1:681234567890:web:abc123') }}"
};

const app = initializeApp(firebaseConfig, 'voice-chat-app');
const db = getFirestore(app);
window.voiceChatDb = db;
window.voiceChatFirebase = { doc, collection, addDoc, onSnapshot, query, where, deleteDoc, getDocs, getDoc, setDoc, serverTimestamp };
</script>

<script src="{{ asset('js/VoiceChat.js') }}"></script>

<script>
(function() {
    'use strict';
    
    let voiceChat = null;
    const VOICE_LOBBY_CODE = '{{ $lobby_code ?? "" }}';
    const CURRENT_PLAYER_ID = {{ auth()->id() ?? 0 }};
    
    async function initVoiceChat() {
        if (!VOICE_LOBBY_CODE || !window.voiceChatDb) {
            console.log('[VoiceChat] Missing lobby code or Firebase - skipping');
            return;
        }
        
        try {
            voiceChat = new VoiceChat({
                sessionId: VOICE_LOBBY_CODE,
                localUserId: CURRENT_PLAYER_ID,
                mode: 'duo',
                db: window.voiceChatDb,
                onConnectionChange: (state) => updateMicUI(state),
                onError: (error) => console.error('[VoiceChat] Error:', error)
            });
            
            await voiceChat.initialize();
            console.log('[VoiceChat] Initialized successfully');
        } catch (error) {
            console.error('[VoiceChat] Init error:', error);
        }
    }
    
    function updateMicUI(state) {
        const micBtn = document.getElementById('voiceMicBtn');
        if (micBtn) {
            micBtn.classList.toggle('active', !state.muted);
            micBtn.textContent = state.muted ? '🔇' : '🔊';
        }
    }
    
    window.toggleVoiceMic = async function() {
        if (!voiceChat) {
            await initVoiceChat();
        }
        if (voiceChat) {
            await voiceChat.toggleMicrophone();
        }
    };
    
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(initVoiceChat, 1000);
    });
    
    window.addEventListener('beforeunload', () => {
        if (voiceChat) voiceChat.cleanup();
    });

    // Register DuoSocketClient handlers after all scripts have loaded.
    // DOMContentLoaded is guaranteed to fire after ALL blocking <script src=""> tags —
    // including DuoSocketClient.js. setTimeout(0) is unreliable: it is a macrotask that
    // can fire DURING a script fetch, before window.DuoSocketClient is set.
    document.addEventListener('DOMContentLoaded', function() {
        var ds = window.DuoSocketClient;
        var h  = window._duoResultHandlers;
        if (!ds || !h) { console.error('[DuoResult] DuoSocketClient or handlers missing'); return; }
        ds.on('disconnect',   h.disconnect);
        ds.on('error',        h.error);
        ds.on('round_ended',  h.round_ended);
        ds.on('match_ended',  h.match_ended);
        ds.on('score_update', h.score_update);
        ds.on('player_ready', h.player_ready);
        ds.on('phase_changed',h.phase_changed);
        ds.on('state',        h.state);
        ds.on('both_ready',   h.both_ready);
    });
})();
</script>
@endsection

@section('scripts')
{{-- Handlers registered via setTimeout(0) inside @section('content') IIFE above --}}
@endsection
