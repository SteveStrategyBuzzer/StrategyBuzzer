@extends('layouts.app')

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

<div class="connection-status connecting" id="connectionStatus">{{ __('Connexion...') }}</div>

<button id="voiceMicBtn" class="voice-mic-btn" onclick="toggleVoiceMic()" title="{{ __('Activer/Désactiver le micro') }}">
    🔇
</button>

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
    
    <div class="result-answers">
        @if(!empty($playerAnswer))
            <div class="answer-display {{ $wasCorrect ? 'answer-correct' : 'answer-incorrect' }}">
                <span class="answer-icon">{{ $wasCorrect ? '✓' : '✗' }}</span>
                <span class="answer-label">{{ __('Votre réponse') }} :</span>
                <span class="answer-text">{{ $playerAnswer }}</span>
            </div>
        @endif
        
        <div class="answer-display answer-correct">
            <span class="answer-icon">✓</span>
            <span class="answer-label">{{ __('Bonne réponse') }} :</span>
            <span class="answer-text">{{ $question['correct_answer'] ?? $question['answer'] ?? '-' }}</span>
        </div>
    </div>
    
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
    
    <div class="result-actions">
        <button class="btn-go" id="btnGo">{{ __('GO') }}</button>
        <div class="waiting-message" id="waitingMessage">
            ⏳ {{ __('En attente de l\'autre joueur') }}<span class="waiting-dots"></span>
        </div>
    </div>
</div>

<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>

<script>
(function() {
    'use strict';
    
    const MATCH_ID = '{{ $match_id ?? "" }}';
    const ROOM_ID = '{{ $room_id ?? "" }}';
    const LOBBY_CODE = '{{ $lobby_code ?? "" }}';
    const JWT_TOKEN = '{{ $jwt_token ?? "" }}';
    const GAME_SERVER_URL = '{{ config("app.game_server_url", "") }}';
    const CURRENT_QUESTION = {{ $currentQuestion ?? 1 }};
    const TOTAL_QUESTIONS = {{ $totalQuestions ?? 10 }};
    
    let isReady = false;
    let isRedirecting = false;
    
    const connectionStatus = document.getElementById('connectionStatus');
    const btnGo = document.getElementById('btnGo');
    const waitingMessage = document.getElementById('waitingMessage');
    const playerScoreEl = document.getElementById('playerScore');
    const opponentScoreEl = document.getElementById('opponentScore');
    
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
        
        if (DuoSocketClient.isConnected()) {
            DuoSocketClient.socket.emit('player_ready', {
                roomId: ROOM_ID || LOBBY_CODE,
                matchId: MATCH_ID
            });
            console.log('[DuoResult] Player ready sent');
        }
    }
    
    function navigateToNextQuestion() {
        if (isRedirecting) return;
        isRedirecting = true;
        
        const nextQuestion = CURRENT_QUESTION + 1;
        
        if (nextQuestion > TOTAL_QUESTIONS) {
            window.location.href = '/duo/match/' + MATCH_ID + '/final';
        } else {
            window.location.href = '/duo/match/' + MATCH_ID + '/question/' + nextQuestion;
        }
    }
    
    function navigateToRoundScoreboard(data) {
        if (isRedirecting) return;
        isRedirecting = true;
        
        window.location.href = '/duo/match/' + MATCH_ID + '/round-scoreboard';
    }
    
    function navigateToFinalResults(data) {
        if (isRedirecting) return;
        isRedirecting = true;
        
        window.location.href = '/duo/match/' + MATCH_ID + '/final';
    }
    
    btnGo.addEventListener('click', setPlayerReady);
    
    if (GAME_SERVER_URL) {
        updateConnectionStatus('connecting');
        
        DuoSocketClient.onConnect = function() {
            updateConnectionStatus('connected');
            
            DuoSocketClient.joinRoom(ROOM_ID, LOBBY_CODE, {
                token: JWT_TOKEN,
                matchId: MATCH_ID
            });
        };
        
        DuoSocketClient.onDisconnect = function(reason) {
            updateConnectionStatus('disconnected');
            console.log('[DuoResult] Disconnected:', reason);
        };
        
        DuoSocketClient.onError = function(error) {
            console.error('[DuoResult] Socket error:', error);
        };
        
        DuoSocketClient.onRoundEnded = function(data) {
            console.log('[DuoResult] Round ended', data);
            navigateToRoundScoreboard(data);
        };
        
        DuoSocketClient.onMatchEnded = function(data) {
            console.log('[DuoResult] Match ended', data);
            navigateToFinalResults(data);
        };
        
        DuoSocketClient.onScoreUpdate = function(data) {
            console.log('[DuoResult] Score update', data);
            if (data.playerScore !== undefined) {
                playerScoreEl.textContent = data.playerScore;
            }
            if (data.opponentScore !== undefined) {
                opponentScoreEl.textContent = data.opponentScore;
            }
        };
        
        DuoSocketClient.onPlayerReady = function(data) {
            console.log('[DuoResult] Player ready received', data);
        };
        
        DuoSocketClient.connect(GAME_SERVER_URL, JWT_TOKEN)
            .then(function() {
                console.log('[DuoResult] Connected to game server');
                
                DuoSocketClient.socket.on('both_ready', function(data) {
                    console.log('[DuoResult] Both players ready', data);
                    navigateToNextQuestion();
                });
            })
            .catch(function(error) {
                console.error('[DuoResult] Failed to connect:', error);
                updateConnectionStatus('disconnected');
            });
    } else {
        console.warn('[DuoResult] No game server URL configured');
        updateConnectionStatus('disconnected');
        
        btnGo.addEventListener('click', function() {
            if (!isReady) {
                setPlayerReady();
                setTimeout(navigateToNextQuestion, 2000);
            }
        });
    }
    
    window.addEventListener('beforeunload', function() {
        if (DuoSocketClient.isConnected()) {
            DuoSocketClient.disconnect();
        }
    });
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
})();
</script>
@endsection
