@extends('layouts.app')

@section('content')
<style>
    body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        color: #fff;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 5px;
        overflow-y: auto;
        overflow-x: hidden;
        margin: 0;
    }
    
    .result-container {
        max-width: 800px;
        width: 100%;
        text-align: center;
        padding: 10px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        max-height: 100vh;
    }
    
    .opponent-header {
        margin-bottom: 15px;
        padding: 12px;
        background: rgba(102, 126, 234, 0.2);
        border-radius: 12px;
        border: 2px solid rgba(102, 126, 234, 0.4);
    }
    
    .opponent-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: #667eea;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .round-details {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 12px;
        padding: 12px;
        background: rgba(0,0,0,0.3);
        border-radius: 10px;
        backdrop-filter: blur(10px);
    }
    
    .round-player, .round-opponent {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }
    
    .round-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .round-label {
        font-size: 0.85rem;
        color: #4ECDC4;
        font-weight: 600;
    }
    
    .points-gained {
        background: linear-gradient(135deg, #2ECC71, #27AE60);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 1rem;
    }
    
    .points-lost {
        background: linear-gradient(135deg, #E74C3C, #C0392B);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 1rem;
    }
    
    .points-neutral {
        background: rgba(255,255,255,0.1);
        color: #95a5a6;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .points-timeout {
        background: linear-gradient(135deg, #F39C12, #E67E22);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 1rem;
    }
    
    .score-battle {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
        animation: fadeIn 0.8s ease-out;
        flex-shrink: 0;
    }
    
    .score-player, .score-opponent {
        width: 150px;
        min-width: 150px;
        flex-shrink: 0;
        padding: 15px;
        border-radius: 15px;
        position: relative;
        backdrop-filter: blur(10px);
    }
    
    .score-player {
        background: linear-gradient(145deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        border: 3px solid #667eea;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    }
    
    .score-player.correct {
        border-color: #2ECC71;
        box-shadow: 0 10px 40px rgba(46, 204, 113, 0.3);
    }
    
    .score-player.incorrect {
        border-color: #E74C3C;
        box-shadow: 0 10px 40px rgba(231, 76, 60, 0.3);
    }
    
    .score-player.timeout {
        border-color: #F39C12;
        box-shadow: 0 10px 40px rgba(243, 156, 18, 0.3);
    }
    
    .score-opponent {
        background: linear-gradient(145deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
        border: 3px solid #667eea;
        box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    }
    
    .score-label {
        font-size: 0.9rem;
        opacity: 0.8;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .score-number {
        font-size: 2.5rem;
        font-weight: 900;
        line-height: 1;
    }
    
    .score-player .score-number {
        color: #667eea;
    }
    
    .score-player.correct .score-number {
        color: #2ECC71;
    }
    
    .score-player.incorrect .score-number {
        color: #E74C3C;
    }
    
    .score-player.timeout .score-number {
        color: #F39C12;
    }
    
    .score-opponent .score-number {
        color: #667eea;
    }
    
    .vs-divider {
        font-size: 1.2rem;
        font-weight: bold;
        color: #4ECDC4;
        background: rgba(78, 205, 196, 0.2);
        padding: 10px;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #4ECDC4;
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.5);
    }
    
    .result-answers {
        background: rgba(0,0,0,0.4);
        padding: 15px;
        border-radius: 15px;
        margin-bottom: 15px;
        animation: fadeIn 1s ease-out;
        border: 2px solid rgba(255,255,255,0.1);
        flex-shrink: 0;
    }
    
    .answer-display {
        padding: 10px 15px;
        border-radius: 12px;
        margin-bottom: 8px;
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
        background: rgba(46, 204, 113, 0.25);
        border: 2px solid #2ECC71;
        box-shadow: 0 5px 20px rgba(46, 204, 113, 0.3);
    }
    
    .answer-incorrect {
        background: rgba(231, 76, 60, 0.25);
        border: 2px solid #E74C3C;
        box-shadow: 0 5px 20px rgba(231, 76, 60, 0.3);
    }
    
    .answer-timeout {
        background: rgba(243, 156, 18, 0.25);
        border: 2px solid #F39C12;
        box-shadow: 0 5px 20px rgba(243, 156, 18, 0.3);
    }
    
    .answer-label {
        opacity: 0.9;
        font-size: 0.95rem;
        font-weight: 600;
        flex-shrink: 0;
    }
    
    .answer-text {
        flex: 1;
        text-align: left;
        font-weight: 500;
    }
    
    .answer-icon {
        font-size: 1.8rem;
    }
    
    .question-info {
        background: rgba(0,0,0,0.3);
        border: 2px solid rgba(78, 205, 196, 0.3);
        border-radius: 10px;
        padding: 10px;
        margin-bottom: 15px;
        backdrop-filter: blur(10px);
    }
    
    .question-info-text {
        font-size: 0.9rem;
        color: #4ECDC4;
        font-weight: 600;
    }
    
    .result-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex-shrink: 0;
    }
    
    .btn-continue {
        width: 100%;
        padding: 16px 30px;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
        color: white;
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.4);
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-continue:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(78, 205, 196, 0.6);
        color: white;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @media (max-width: 768px) {
        .score-battle {
            gap: 15px;
        }
        
        .score-number {
            font-size: 2.5rem;
        }
        
        .vs-divider {
            width: 50px;
            height: 50px;
            font-size: 1.2rem;
        }
        
        .answer-text {
            font-size: 1rem;
        }
    }
    
    @media (max-width: 480px) {
        .score-battle {
            gap: 12px;
        }
        
        .score-player, .score-opponent {
            max-width: 140px;
        }
        
        .score-label {
            font-size: 0.75rem;
        }
        
        .score-number {
            font-size: 2rem;
        }
    }
    
    @media (max-width: 480px) and (orientation: portrait) {
        .result-container {
            padding: 16px;
        }
        
        .score-number {
            font-size: 2rem;
        }
        
        .round-details {
            padding: 12px;
        }
        
        .answer-text {
            font-size: 0.95rem;
        }
    }
    
    @media (max-height: 500px) and (orientation: landscape) {
        .result-container {
            padding: 10px;
            max-height: 100vh;
            overflow-y: auto;
        }
        
        .score-battle {
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .score-number {
            font-size: 2rem;
        }
        
        .vs-divider {
            width: 45px;
            height: 45px;
            font-size: 1rem;
        }
        
        .round-details {
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .answer-text {
            font-size: 0.9rem;
        }
    }
</style>

@php
    $isCorrect = $params['is_correct'] ?? false;
    $isTimeout = $params['is_timeout'] ?? false;
    $playerAnswer = $params['player_answer'] ?? '';
    $correctAnswer = $params['correct_answer'] ?? '';
    $playerPoints = $params['player_points'] ?? 0;
    $opponentPoints = $params['opponent_points'] ?? 0;
    $score = $params['score'] ?? 0;
    $opponentScore = $params['opponent_score'] ?? 0;
    $playerInfo = $params['player_info'] ?? [];
    $opponentInfo = $params['opponent_info'] ?? [];
    $matchId = $params['match_id'] ?? '';
    $roomCode = $params['room_code'] ?? '';
    $currentQuestion = $params['current_question'] ?? 1;
    $totalQuestions = $params['total_questions'] ?? 10;
    
    $playerName = $playerInfo['username'] ?? __('Vous');
    $opponentName = $opponentInfo['username'] ?? __('Adversaire');
    
    $playerStatusClass = $isTimeout ? 'timeout' : ($isCorrect ? 'correct' : 'incorrect');
@endphp

<div class="result-container">
    <div class="opponent-header">
        <h2 class="opponent-name">{{ __('Vs') }} {{ $opponentName }}</h2>
    </div>
    
    <div class="round-details">
        <div class="round-player">
            <div class="round-info">
                <span class="round-label">🎮 {{ __('Vous') }}</span>
                @if($isTimeout)
                    <span class="points-timeout">0</span>
                @elseif($playerPoints > 0)
                    <span class="points-gained">+{{ $playerPoints }}</span>
                @elseif($playerPoints < 0)
                    <span class="points-lost">{{ $playerPoints }}</span>
                @else
                    <span class="points-neutral">0</span>
                @endif
            </div>
        </div>
        
        <div class="round-opponent">
            <div class="round-info">
                <span class="round-label">🎯 {{ $opponentName }}</span>
                @if($opponentPoints > 0)
                    <span class="points-gained">+{{ $opponentPoints }}</span>
                @elseif($opponentPoints < 0)
                    <span class="points-lost">{{ $opponentPoints }}</span>
                @else
                    <span class="points-neutral">0</span>
                @endif
            </div>
        </div>
    </div>
    
    <div class="score-battle">
        <div class="score-player {{ $playerStatusClass }}">
            <div class="score-label">🎮 {{ __('Vous') }}</div>
            <div class="score-number">{{ $score }}</div>
        </div>
        
        <div class="vs-divider">{{ __('VS') }}</div>
        
        <div class="score-opponent">
            <div class="score-label">🎯 {{ $opponentName }}</div>
            <div class="score-number">{{ $opponentScore }}</div>
        </div>
    </div>
    
    <div class="result-answers">
        @if($isTimeout)
            <div class="answer-display answer-timeout">
                <span class="answer-label">{{ __('Votre réponse') }} :</span>
                <span class="answer-text">⏰ {{ __('Temps écoulé') }}</span>
                <span class="answer-icon">⏱️</span>
            </div>
        @elseif($isCorrect)
            <div class="answer-display answer-correct">
                <span class="answer-label">{{ __('Votre réponse') }} :</span>
                <span class="answer-text">{{ $playerAnswer }}</span>
                <span class="answer-icon">✅</span>
            </div>
        @else
            <div class="answer-display answer-incorrect">
                <span class="answer-label">{{ __('Votre réponse') }} :</span>
                <span class="answer-text">{{ $playerAnswer ?: __('Aucune réponse') }}</span>
                <span class="answer-icon">❌</span>
            </div>
        @endif
        
        <div class="answer-display answer-correct">
            <span class="answer-label">{{ __('Bonne réponse') }} :</span>
            <span class="answer-text">{{ $correctAnswer }}</span>
            <span class="answer-icon">✅</span>
        </div>
    </div>
    
    <div class="question-info">
        <span class="question-info-text">❓ {{ __('Question') }} {{ $currentQuestion }}/{{ $totalQuestions }}</span>
    </div>
    
    <div class="result-actions">
        <a href="/game/duo/waiting" class="btn-continue">
            🚀 {{ __('Continuer') }}
        </a>
    </div>
</div>

<script src="{{ asset('js/firebase-game-sync.js') }}"></script>
<script>
(function() {
    const MATCH_ID = @json($matchId);
    const ROOM_CODE = @json($roomCode);
    const CURRENT_QUESTION = @json($currentQuestion);
    const PLAYER_ID = @json(auth()->id());
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function initFirebase() {
        if (typeof FirebaseGameSync !== 'undefined' && MATCH_ID) {
            FirebaseGameSync.init({
                matchId: MATCH_ID,
                mode: 'duo',
                laravelUserId: PLAYER_ID,
                csrfToken: CSRF_TOKEN,
                callbacks: {
                    onReady: function() {
                        console.log('[DuoResult] Firebase ready');
                    },
                    onPhaseChange: function(phase, data) {
                        console.log('[DuoResult] Phase changed:', phase, data);
                    },
                    onOpponentDisconnect: function(opponentId, info) {
                        console.log('[DuoResult] Opponent disconnected:', opponentId);
                    }
                }
            }).catch(function(error) {
                console.error('[DuoResult] Firebase init failed:', error);
            });
        }
    }

    initFirebase();

    window.addEventListener('beforeunload', function() {
        if (typeof FirebaseGameSync !== 'undefined') {
            FirebaseGameSync.cleanup();
        }
    });
})();
</script>
@endsection
