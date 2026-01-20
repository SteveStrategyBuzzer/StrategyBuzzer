@extends('layouts.app')

@section('content')
@php
$mode = 'duo';
$choices = $question['choices'] ?? [];
$questionText = $question['text'] ?? '';
$isBuzzWinner = ($buzz_winner ?? 'player') === 'player';
$buzzTime = $buzz_time ?? 0;
$noBuzz = ($no_buzz ?? false) || !$isBuzzWinner && $buzzTime == 0;
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
    
    .game-container {
        max-width: 600px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 15px;
        position: relative;
        padding: 20px;
    }
    
    .header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(30, 50, 70, 0.8);
        padding: 15px 25px;
        border-radius: 15px;
        border: 2px solid rgba(78, 205, 196, 0.3);
    }
    
    .question-label {
        font-size: 1.3rem;
        font-weight: 700;
        color: #fff;
    }
    
    .potential-points {
        font-size: 1.8rem;
        font-weight: 900;
        transition: all 0.3s ease;
    }
    
    .potential-points.points-2 {
        color: #4ECDC4;
        text-shadow: 0 0 20px rgba(78, 205, 196, 0.8);
    }
    
    .potential-points.points-1 {
        color: #FFD700;
        text-shadow: 0 0 20px rgba(255, 215, 0, 0.8);
    }
    
    .potential-points.points-0 {
        color: #FF6B6B;
        text-shadow: 0 0 20px rgba(255, 107, 107, 0.8);
    }
    
    .score-display {
        font-size: 1.1rem;
        font-weight: 600;
        color: #aaa;
    }
    
    .question-text-box {
        background: rgba(30, 50, 70, 0.6);
        padding: 20px;
        border-radius: 12px;
        font-size: 1.2rem;
        font-weight: 500;
        line-height: 1.5;
        text-align: center;
        border: 1px solid rgba(78, 205, 196, 0.2);
    }
    
    .timer-section {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 10px 0;
    }
    
    .timer-label {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
        white-space: nowrap;
    }
    
    .timer-bar-container {
        flex: 1;
        height: 8px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
        overflow: hidden;
    }
    
    .timer-bar {
        height: 100%;
        background: linear-gradient(90deg, #4ECDC4, #667eea);
        border-radius: 4px;
        transition: width 0.3s linear;
        width: 100%;
    }
    
    .timer-bar.warning {
        background: linear-gradient(90deg, #FF6B6B, #FF8E53);
    }
    
    .timer-seconds {
        font-size: 1rem;
        font-weight: 700;
        color: #4ECDC4;
        min-width: 30px;
        text-align: right;
    }
    
    .timer-seconds.warning {
        color: #FF6B6B;
    }
    
    .answers-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin: 10px 0;
    }
    
    .answer-button {
        display: flex;
        align-items: center;
        gap: 15px;
        background: rgba(255, 255, 255, 0.08);
        border: 2px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 18px 20px;
        color: #fff;
        font-size: 1.1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: left;
        width: 100%;
    }
    
    .answer-number {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        flex-shrink: 0;
    }
    
    .answer-text {
        flex: 1;
    }
    
    .answer-button:hover:not(.disabled):not(.selected) {
        background: rgba(78, 205, 196, 0.15);
        border-color: rgba(78, 205, 196, 0.5);
        transform: translateX(5px);
    }
    
    .answer-button.selected {
        background: rgba(78, 205, 196, 0.25);
        border-color: #4ECDC4;
        box-shadow: 0 0 20px rgba(78, 205, 196, 0.4);
    }
    
    .answer-button.selected .answer-number {
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
    }
    
    .answer-button.correct {
        background: rgba(78, 205, 196, 0.3);
        border-color: #4ECDC4;
        box-shadow: 0 0 25px rgba(78, 205, 196, 0.6);
    }
    
    .answer-button.correct .answer-number {
        background: linear-gradient(135deg, #4ECDC4 0%, #2ECC71 100%);
    }
    
    .answer-button.incorrect {
        background: rgba(255, 107, 107, 0.3);
        border-color: #FF6B6B;
        box-shadow: 0 0 25px rgba(255, 107, 107, 0.6);
    }
    
    .answer-button.incorrect .answer-number {
        background: linear-gradient(135deg, #FF6B6B 0%, #E74C3C 100%);
    }
    
    .answer-button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .answer-button.waiting {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .answer-indicator {
        font-size: 1.4rem;
        margin-left: auto;
    }
    
    .buzz-status-banner {
        padding: 12px 20px;
        border-radius: 10px;
        text-align: center;
        font-size: 1rem;
        font-weight: 600;
        margin-top: 10px;
    }
    
    .buzz-status-banner.buzzed {
        background: rgba(78, 205, 196, 0.15);
        border: 2px solid rgba(78, 205, 196, 0.5);
        color: #4ECDC4;
    }
    
    .buzz-status-banner.no-buzz {
        background: rgba(255, 165, 0, 0.15);
        border: 2px solid rgba(255, 165, 0, 0.5);
        color: #FFA500;
    }
    
    .buzz-status-banner.opponent-buzz {
        background: rgba(255, 107, 107, 0.15);
        border: 2px solid rgba(255, 107, 107, 0.5);
        color: #FF6B6B;
    }
    
    .buzz-status-banner.historian-active {
        background: rgba(139, 90, 43, 0.2);
        border: 2px solid rgba(205, 133, 63, 0.6);
        color: #DEB887;
    }
    
    .historian-skill-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        margin: 15px 0;
    }
    
    .historian-skill-button {
        display: flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(135deg, rgba(139, 90, 43, 0.3) 0%, rgba(205, 133, 63, 0.3) 100%);
        border: 2px solid rgba(205, 133, 63, 0.6);
        border-radius: 15px;
        padding: 15px 25px;
        color: #DEB887;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        animation: pulse-historian 2s ease-in-out infinite;
    }
    
    @keyframes pulse-historian {
        0%, 100% { box-shadow: 0 0 15px rgba(205, 133, 63, 0.4); }
        50% { box-shadow: 0 0 25px rgba(205, 133, 63, 0.7); }
    }
    
    .historian-skill-button:hover {
        background: linear-gradient(135deg, rgba(139, 90, 43, 0.5) 0%, rgba(205, 133, 63, 0.5) 100%);
        transform: scale(1.05);
        box-shadow: 0 0 30px rgba(205, 133, 63, 0.8);
    }
    
    .historian-skill-button .skill-icon {
        font-size: 1.8rem;
    }
    
    .historian-skill-button .skill-text {
        font-weight: 700;
    }
    
    .historian-skill-button .skill-points {
        background: rgba(78, 205, 196, 0.3);
        padding: 4px 10px;
        border-radius: 8px;
        color: #4ECDC4;
        font-size: 0.9rem;
    }
    
    .skill-hint {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
        margin: 0;
    }
    
    .active-skills-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: center;
        margin: 10px 0;
        padding: 10px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 12px;
    }
    
    .skill-action-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 10px;
        border: 2px solid;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .skill-action-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .skill-action-btn.used {
        opacity: 0.3;
        text-decoration: line-through;
    }
    
    .skill-action-btn .skill-icon {
        font-size: 1.2rem;
    }
    
    .skill-action-btn.mathematicien {
        background: rgba(147, 112, 219, 0.2);
        border-color: rgba(147, 112, 219, 0.6);
        color: #B19CD9;
    }
    
    .skill-action-btn.scientifique {
        background: rgba(0, 255, 136, 0.15);
        border-color: rgba(0, 255, 136, 0.5);
        color: #00FF88;
    }
    
    .skill-action-btn.ia-junior {
        background: rgba(0, 191, 255, 0.15);
        border-color: rgba(0, 191, 255, 0.5);
        color: #00BFFF;
    }
    
    .skill-action-btn.visionnaire {
        background: rgba(255, 215, 0, 0.15);
        border-color: rgba(255, 215, 0, 0.5);
        color: #FFD700;
    }
    
    .skill-action-btn.sprinteur {
        background: rgba(255, 165, 0, 0.15);
        border-color: rgba(255, 165, 0, 0.5);
        color: #FFA500;
    }
    
    .skill-action-btn.historien {
        background: rgba(139, 90, 43, 0.2);
        border-color: rgba(205, 133, 63, 0.6);
        color: #DEB887;
    }
    
    .skill-action-btn:not(:disabled):hover {
        transform: scale(1.05);
        filter: brightness(1.2);
    }
    
    .answer-button.illuminated {
        background: linear-gradient(135deg, rgba(147, 112, 219, 0.4) 0%, rgba(186, 85, 211, 0.4) 100%);
        border-color: #B19CD9;
        box-shadow: 0 0 20px rgba(147, 112, 219, 0.6);
        animation: pulse-illuminate 1.5s ease-in-out infinite;
    }
    
    @keyframes pulse-illuminate {
        0%, 100% { box-shadow: 0 0 20px rgba(147, 112, 219, 0.6); }
        50% { box-shadow: 0 0 35px rgba(147, 112, 219, 0.9); }
    }
    
    .answer-button.acidified {
        background: rgba(0, 255, 136, 0.1);
        border-color: #00FF88;
        box-shadow: 0 0 15px rgba(0, 255, 136, 0.4);
    }
    
    .answer-button.acidified::after {
        content: '🧪';
        position: absolute;
        right: 10px;
        font-size: 1.2rem;
    }
    
    .answer-button.eliminated {
        opacity: 0.2;
        pointer-events: none;
        text-decoration: line-through;
    }
    
    .answer-button.ai-suggested {
        background: linear-gradient(135deg, rgba(0, 191, 255, 0.3) 0%, rgba(30, 144, 255, 0.3) 100%);
        border-color: #00BFFF;
        box-shadow: 0 0 20px rgba(0, 191, 255, 0.5);
    }
    
    .answer-button.ai-suggested::before {
        content: '🤖';
        position: absolute;
        left: 10px;
        font-size: 1rem;
    }
    
    .answer-button.locked-correct {
        background: linear-gradient(135deg, rgba(255, 215, 0, 0.3) 0%, rgba(255, 165, 0, 0.3) 100%);
        border-color: #FFD700;
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
    }
    
    .result-overlay {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0, 0, 0, 0.95);
        padding: 40px 60px;
        border-radius: 30px;
        text-align: center;
        z-index: 200;
        border: 3px solid;
        animation: fadeIn 0.3s ease;
        display: none;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translate(-50%, -60%);
        }
        to {
            opacity: 1;
            transform: translate(-50%, -50%);
        }
    }
    
    .result-overlay.correct {
        border-color: #4ECDC4;
        box-shadow: 0 0 50px rgba(78, 205, 196, 0.8);
    }
    
    .result-overlay.incorrect {
        border-color: #FF6B6B;
        box-shadow: 0 0 50px rgba(255, 107, 107, 0.8);
    }
    
    .result-text {
        font-size: 2.5rem;
        font-weight: 900;
        margin-bottom: 15px;
    }
    
    .result-overlay.correct .result-text {
        color: #4ECDC4;
    }
    
    .result-overlay.incorrect .result-text {
        color: #FF6B6B;
    }
    
    .points-text {
        font-size: 1.5rem;
        font-weight: 600;
        opacity: 0.9;
    }
    
    .correct-answer-text {
        font-size: 1.2rem;
        margin-top: 15px;
        color: #FFD700;
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
    
    .waiting-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 150;
    }
    
    .waiting-message {
        background: rgba(0, 0, 0, 0.9);
        padding: 40px 60px;
        border-radius: 30px;
        text-align: center;
        border: 3px solid #FFD700;
        box-shadow: 0 0 50px rgba(255, 215, 0, 0.5);
    }
    
    .waiting-message h2 {
        font-size: 1.8rem;
        color: #FFD700;
        margin-bottom: 10px;
    }
    
    .waiting-message p {
        font-size: 1.1rem;
        opacity: 0.9;
    }
    
    .voice-mic-button {
        position: fixed;
        bottom: 30px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 2px solid rgba(78, 205, 196, 0.5);
        background: rgba(15, 32, 39, 0.9);
        color: white;
        font-size: 1.4rem;
        cursor: pointer;
        z-index: 1000;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .voice-mic-button:hover {
        background: rgba(78, 205, 196, 0.3);
        transform: scale(1.1);
    }
    
    .voice-mic-button.active {
        background: linear-gradient(135deg, #2ECC71, #27AE60);
        border-color: #2ECC71;
        animation: pulse-mic 1.5s infinite;
    }
    
    .voice-mic-button.muted {
        background: rgba(60, 60, 60, 0.9);
        border-color: rgba(150, 150, 150, 0.5);
    }
    
    @keyframes pulse-mic {
        0%, 100% { box-shadow: 0 0 10px rgba(46, 204, 113, 0.5); }
        50% { box-shadow: 0 0 20px rgba(46, 204, 113, 0.8); }
    }
    
    @media (max-width: 480px) {
        .game-container {
            padding: 15px;
        }
        
        .header-row {
            padding: 12px 18px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .question-label {
            font-size: 1.1rem;
        }
        
        .potential-points {
            font-size: 1.5rem;
        }
        
        .answer-button {
            padding: 15px;
            font-size: 1rem;
        }
        
        .answer-number {
            width: 32px;
            height: 32px;
            font-size: 0.9rem;
        }
    }
</style>

<div class="connection-status connecting" id="connectionStatus">{{ __('Connexion...') }}</div>

<button id="voiceMicButton" class="voice-mic-button" title="{{ __('Activer/désactiver le micro') }}">
    <span id="micIcon">🎤</span>
</button>

<div class="game-container">
    <div class="header-row">
        <div class="question-label">{{ __('Question') }} #{{ $currentQuestion ?? 1 }}</div>
        <div class="potential-points points-2" id="potentialPoints">+2</div>
        <div class="score-display" id="scoreDisplay">{{ __('Score') }} <span id="playerScoreValue">{{ $playerScore ?? 0 }}</span></div>
    </div>
    
    <div class="question-text-box">
        {{ $questionText }}
    </div>
    
    <div class="timer-section">
        <span class="timer-label">{{ __('Temps pour répondre') }}</span>
        <div class="timer-bar-container">
            <div class="timer-bar" id="timerBar"></div>
        </div>
        <span class="timer-seconds" id="timerSeconds">10s</span>
    </div>
    
    <div class="answers-container" id="answersContainer">
        @foreach($choices as $index => $choice)
            <button class="answer-button {{ (!$isBuzzWinner && !$noBuzz) ? 'waiting' : '' }}" 
                    data-index="{{ $index }}"
                    data-text="{{ $choice }}"
                    {{ (!$isBuzzWinner && !$noBuzz) ? 'disabled' : '' }}>
                <span class="answer-number">{{ $index + 1 }}</span>
                <span class="answer-text">{{ $choice }}</span>
                <span class="answer-indicator" id="indicator{{ $index }}"></span>
            </button>
        @endforeach
    </div>
    
    @php
        $hasHistorianSkill = false;
        $hasIlluminateNumbers = false;
        $hasAcidifyError = false;
        $hasEliminateTwo = false;
        $hasAiSuggestion = false;
        $hasLockCorrect = false;
        $hasExtraAnswerTime = false;
        
        if (isset($skills) && is_array($skills)) {
            foreach ($skills as $skill) {
                $skillId = $skill['id'] ?? '';
                if ($skillId === 'knowledge_without_time' || $skillId === 'hint_before_others') {
                    $hasHistorianSkill = true;
                }
                if ($skillId === 'illuminate_numbers') $hasIlluminateNumbers = true;
                if ($skillId === 'acidify_error') $hasAcidifyError = true;
                if ($skillId === 'eliminate_two') $hasEliminateTwo = true;
                if ($skillId === 'ai_suggestion') $hasAiSuggestion = true;
                if ($skillId === 'lock_correct') $hasLockCorrect = true;
                if ($skillId === 'extra_answer_time') $hasExtraAnswerTime = true;
            }
        }
        
        $correctIndex = $correct_index ?? null;
        $choicesJson = json_encode($choices);
    @endphp
    
    @if($hasIlluminateNumbers || $hasAcidifyError || $hasEliminateTwo || $hasAiSuggestion || $hasLockCorrect || $hasExtraAnswerTime)
    <div class="active-skills-bar" id="activeSkillsBar">
        @if($hasIlluminateNumbers)
            <button class="skill-action-btn mathematicien" id="skillIlluminate" title="{{ __('Illumine une réponse si elle contient un chiffre') }}">
                <span class="skill-icon">💡</span>
                <span>{{ __('Illuminer') }}</span>
            </button>
        @endif
        @if($hasAcidifyError)
            <button class="skill-action-btn scientifique" id="skillAcidify" title="{{ __('Acidifie une mauvaise réponse') }}">
                <span class="skill-icon">🧪</span>
                <span>{{ __('Acidifier') }}</span>
            </button>
        @endif
        @if($hasEliminateTwo)
            <button class="skill-action-btn ia-junior" id="skillEliminate" title="{{ __('Élimine 2 mauvaises réponses') }}">
                <span class="skill-icon">❌</span>
                <span>{{ __('Éliminer 2') }}</span>
            </button>
        @endif
        @if($hasAiSuggestion)
            <button class="skill-action-btn ia-junior" id="skillAiSuggest" title="{{ __('L\'IA suggère une réponse') }}">
                <span class="skill-icon">🤖</span>
                <span>{{ __('Suggestion IA') }}</span>
            </button>
        @endif
        @if($hasLockCorrect)
            <button class="skill-action-btn visionnaire" id="skillLockCorrect" title="{{ __('Seule la bonne réponse sélectionnable') }}">
                <span class="skill-icon">🔒</span>
                <span>{{ __('2 pts sécurisés') }}</span>
            </button>
        @endif
        @if($hasExtraAnswerTime)
            <button class="skill-action-btn historien" id="skillExtraTime" title="{{ __('Ajoute 2 secondes') }}">
                <span class="skill-icon">⏰</span>
                <span>{{ __('+2s') }}</span>
            </button>
        @endif
    </div>
    @endif

    @if($noBuzz)
        <div class="buzz-status-banner no-buzz">
            ⚠️ {{ __('Pas buzzé - Vous pouvez quand même répondre (0 point)') }}
        </div>
    @elseif($isBuzzWinner)
        <div class="buzz-status-banner buzzed">
            {{ __('Vous avez buzzé en') }} {{ number_format($buzzTime, 1) }}s 💚
        </div>
    @elseif($hasHistorianSkill)
        <div class="buzz-status-banner opponent-buzz" id="waitingBanner">
            ⏳ {{ __(':name a buzzé - En attente de sa réponse...', ['name' => $opponentName ?? __('Adversaire')]) }}
        </div>
        <div class="historian-skill-section" id="historianSkillSection">
            <button class="historian-skill-button" id="historianSkillBtn" title="{{ __('Activer le skill Réponse historique') }}">
                <span class="skill-icon">🪶</span>
                <span class="skill-text">{{ __('Réponse historique') }}</span>
                <span class="skill-points">+1 {{ __('point') }}</span>
            </button>
            <p class="skill-hint">{{ __('Cliquez pour tenter de répondre (+1 si correct, 0 si erreur)') }}</p>
        </div>
        <div class="buzz-status-banner historian-active" id="historianActiveBanner" style="display: none;">
            🪶 {{ __('Skill activé - Répondez pour +1 point (0 si erreur)') }}
        </div>
    @else
        <div class="buzz-status-banner opponent-buzz">
            ⏳ {{ __(':name a buzzé - En attente de sa réponse...', ['name' => $opponentName ?? __('Adversaire')]) }}
        </div>
    @endif
</div>

<div class="result-overlay" id="resultOverlay">
    <div class="result-text" id="resultText"></div>
    <div class="points-text" id="pointsText"></div>
    <div class="correct-answer-text" id="correctAnswerText"></div>
</div>

<div class="waiting-overlay" id="waitingOverlay">
    <div class="waiting-message">
        <h2>⏳ {{ __('En attente...') }}</h2>
        <p id="waitingText">{{ __(':name répond à la question...', ['name' => $opponentName ?? __('Adversaire')]) }}</p>
    </div>
</div>

<audio id="correctSound" preload="auto">
    <source src="{{ asset('audio/buzzers/correct/correct1.mp3') }}" type="audio/mpeg">
</audio>

<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('audio/buzzers/incorrect/incorrect1.mp3') }}" type="audio/mpeg">
</audio>

<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>

<script>
(function() {
    'use strict';
    
    const MATCH_ID = '{{ $match_id ?? "" }}';
    const ROOM_ID = '{{ $room_id ?? "" }}';
    const LOBBY_CODE = '{{ $lobby_code ?? "" }}';
    const JWT_TOKEN = '{{ $jwt_token ?? "" }}';
    const PLAYER_ID = {{ auth()->id() ?? 0 }};
    
    function getGameServerUrl() {
        const configUrl = '{{ config("app.game_server_url", "") }}';
        if (configUrl && !configUrl.includes('localhost')) {
            return configUrl;
        }
        const protocol = window.location.protocol === 'https:' ? 'https:' : 'http:';
        const hostname = window.location.hostname;
        return `${protocol}//${hostname}:3001`;
    }
    const GAME_SERVER_URL = getGameServerUrl();
    const IS_BUZZ_WINNER = {{ $isBuzzWinner ? 'true' : 'false' }};
    const NO_BUZZ = {{ ($noBuzz ?? false) ? 'true' : 'false' }};
    const HAS_HISTORIAN_SKILL = {{ ($hasHistorianSkill ?? false) ? 'true' : 'false' }};
    
    // Sprinteur passive skill: extra_reflection adds +3 seconds
    @php
        $hasExtraReflection = false;
        if (isset($skills) && is_array($skills)) {
            foreach ($skills as $skill) {
                if (($skill['id'] ?? '') === 'extra_reflection') {
                    $hasExtraReflection = true;
                    break;
                }
            }
        }
    @endphp
    const HAS_EXTRA_REFLECTION = {{ $hasExtraReflection ? 'true' : 'false' }};
    let ANSWER_TIME = HAS_EXTRA_REFLECTION ? 13 : 10;
    let timeLeft = ANSWER_TIME;
    let timerInterval = null;
    let answered = false;
    let selectedIndex = null;
    let isRedirecting = false;
    let historianSkillUsed = false;
    
    const CHOICES = @json($choices);
    const HAS_ILLUMINATE = {{ ($hasIlluminateNumbers ?? false) ? 'true' : 'false' }};
    const HAS_ACIDIFY = {{ ($hasAcidifyError ?? false) ? 'true' : 'false' }};
    const HAS_ELIMINATE = {{ ($hasEliminateTwo ?? false) ? 'true' : 'false' }};
    const HAS_AI_SUGGEST = {{ ($hasAiSuggestion ?? false) ? 'true' : 'false' }};
    const HAS_LOCK_CORRECT = {{ ($hasLockCorrect ?? false) ? 'true' : 'false' }};
    const HAS_EXTRA_ANSWER_TIME = {{ ($hasExtraAnswerTime ?? false) ? 'true' : 'false' }};
    
    let skillsUsed = {
        illuminate: false,
        acidify: false,
        eliminate: false,
        aiSuggest: false,
        lockCorrect: false,
        extraTime: false
    };
    
    const timerBar = document.getElementById('timerBar');
    const timerSeconds = document.getElementById('timerSeconds');
    const potentialPoints = document.getElementById('potentialPoints');
    const connectionStatus = document.getElementById('connectionStatus');
    const resultOverlay = document.getElementById('resultOverlay');
    const resultText = document.getElementById('resultText');
    const pointsText = document.getElementById('pointsText');
    const correctAnswerText = document.getElementById('correctAnswerText');
    const waitingOverlay = document.getElementById('waitingOverlay');
    const answersContainer = document.getElementById('answersContainer');
    const correctSound = document.getElementById('correctSound');
    const incorrectSound = document.getElementById('incorrectSound');
    const answerButtons = document.querySelectorAll('.answer-button');
    
    function containsNumber(str) {
        return /\d/.test(str);
    }
    
    function activateIlluminateSkill() {
        if (skillsUsed.illuminate || answered) return;
        skillsUsed.illuminate = true;
        
        const btn = document.getElementById('skillIlluminate');
        if (btn) btn.classList.add('used');
        
        answerButtons.forEach(function(button) {
            const text = button.getAttribute('data-text') || '';
            if (containsNumber(text)) {
                button.classList.add('illuminated');
            }
        });
        
        console.log('[Skills] Illuminate numbers activated');
    }
    
    function activateAcidifySkill() {
        if (skillsUsed.acidify || answered) return;
        skillsUsed.acidify = true;
        
        const btn = document.getElementById('skillAcidify');
        if (btn) btn.classList.add('used');
        
        const wrongAnswers = [];
        answerButtons.forEach(function(button, idx) {
            if (!button.classList.contains('correct') && !button.classList.contains('illuminated')) {
                wrongAnswers.push(idx);
            }
        });
        
        if (wrongAnswers.length > 0) {
            const randomIdx = wrongAnswers[Math.floor(Math.random() * wrongAnswers.length)];
            answerButtons[randomIdx].classList.add('acidified');
        }
        
        console.log('[Skills] Acidify error activated');
    }
    
    function activateEliminateSkill() {
        if (skillsUsed.eliminate || answered) return;
        skillsUsed.eliminate = true;
        
        const btn = document.getElementById('skillEliminate');
        if (btn) btn.classList.add('used');
        
        const wrongAnswers = [];
        answerButtons.forEach(function(button, idx) {
            if (!button.classList.contains('illuminated') && !button.classList.contains('ai-suggested')) {
                wrongAnswers.push(idx);
            }
        });
        
        for (let i = wrongAnswers.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [wrongAnswers[i], wrongAnswers[j]] = [wrongAnswers[j], wrongAnswers[i]];
        }
        
        let eliminated = 0;
        for (let i = 0; i < wrongAnswers.length && eliminated < 2; i++) {
            const idx = wrongAnswers[i];
            if (answerButtons.length - eliminated > 2) {
                answerButtons[idx].classList.add('eliminated');
                eliminated++;
            }
        }
        
        console.log('[Skills] Eliminate 2 activated, removed', eliminated, 'answers');
    }
    
    function activateAiSuggestSkill() {
        if (skillsUsed.aiSuggest || answered) return;
        skillsUsed.aiSuggest = true;
        
        const btn = document.getElementById('skillAiSuggest');
        if (btn) btn.classList.add('used');
        
        const availableAnswers = [];
        answerButtons.forEach(function(button, idx) {
            if (!button.classList.contains('eliminated') && !button.classList.contains('acidified')) {
                availableAnswers.push(idx);
            }
        });
        
        if (availableAnswers.length > 0) {
            const suggestedIdx = availableAnswers[Math.floor(Math.random() * availableAnswers.length)];
            answerButtons[suggestedIdx].classList.add('ai-suggested');
        }
        
        console.log('[Skills] AI Suggestion activated');
    }
    
    function activateLockCorrectSkill() {
        if (skillsUsed.lockCorrect || answered) return;
        
        const currentPoints = calculatePotentialPoints(timeLeft);
        if (currentPoints !== 2) {
            alert('{{ __("Ce skill ne fonctionne que si vous êtes sur 2 points !") }}');
            return;
        }
        
        skillsUsed.lockCorrect = true;
        
        const btn = document.getElementById('skillLockCorrect');
        if (btn) btn.classList.add('used');
        
        answerButtons.forEach(function(button) {
            button.classList.add('locked-correct');
        });
        
        console.log('[Skills] Lock correct activated - 2 points secured');
    }
    
    function activateExtraTimeSkill() {
        if (skillsUsed.extraTime || answered) return;
        skillsUsed.extraTime = true;
        
        const btn = document.getElementById('skillExtraTime');
        if (btn) btn.classList.add('used');
        
        timeLeft += 2;
        ANSWER_TIME += 2;
        
        timerSeconds.textContent = timeLeft + 's';
        const percentage = (timeLeft / ANSWER_TIME) * 100;
        timerBar.style.width = percentage + '%';
        
        console.log('[Skills] Extra time activated, +2s');
    }
    
    function initSkillButtons() {
        const illuminateBtn = document.getElementById('skillIlluminate');
        if (illuminateBtn) {
            illuminateBtn.addEventListener('click', activateIlluminateSkill);
        }
        
        const acidifyBtn = document.getElementById('skillAcidify');
        if (acidifyBtn) {
            acidifyBtn.addEventListener('click', activateAcidifySkill);
        }
        
        const eliminateBtn = document.getElementById('skillEliminate');
        if (eliminateBtn) {
            eliminateBtn.addEventListener('click', activateEliminateSkill);
        }
        
        const aiSuggestBtn = document.getElementById('skillAiSuggest');
        if (aiSuggestBtn) {
            aiSuggestBtn.addEventListener('click', activateAiSuggestSkill);
        }
        
        const lockCorrectBtn = document.getElementById('skillLockCorrect');
        if (lockCorrectBtn) {
            lockCorrectBtn.addEventListener('click', activateLockCorrectSkill);
        }
        
        const extraTimeBtn = document.getElementById('skillExtraTime');
        if (extraTimeBtn) {
            extraTimeBtn.addEventListener('click', activateExtraTimeSkill);
        }
    }
    
    function calculatePotentialPoints(remainingTime) {
        if (historianSkillUsed) return 1;
        if (NO_BUZZ) return 0;
        if (remainingTime > 3) return 2;
        if (remainingTime >= 1) return 1;
        return 0;
    }
    
    function updatePotentialPointsDisplay(points) {
        potentialPoints.textContent = '+' + points;
        potentialPoints.className = 'potential-points points-' + points;
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
    
    function startTimer() {
        if (timerInterval) clearInterval(timerInterval);
        
        if (NO_BUZZ) {
            updatePotentialPointsDisplay(0);
        }
        
        timerInterval = setInterval(function() {
            timeLeft--;
            
            const percentage = (timeLeft / ANSWER_TIME) * 100;
            timerBar.style.width = percentage + '%';
            timerSeconds.textContent = Math.max(0, timeLeft) + 's';
            
            if (timeLeft <= 5) {
                timerBar.classList.add('warning');
                timerSeconds.classList.add('warning');
            }
            
            if (!NO_BUZZ) {
                const points = calculatePotentialPoints(timeLeft);
                updatePotentialPointsDisplay(points);
            }
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                if (!answered && IS_BUZZ_WINNER) {
                    handleTimeout();
                }
            }
        }, 1000);
    }
    
    function handleTimeout() {
        if (answered) return;
        answered = true;
        
        answerButtons.forEach(function(btn) {
            btn.classList.add('disabled');
        });
        
        DuoSocketClient.answer(-1);
    }
    
    function selectAnswer(index) {
        if (answered || (!IS_BUZZ_WINNER && !NO_BUZZ && !historianSkillUsed)) return;
        
        answered = true;
        selectedIndex = index;
        
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        
        answerButtons.forEach(function(btn) {
            btn.classList.remove('selected');
            btn.classList.add('disabled');
        });
        
        answerButtons[index].classList.add('selected');
        answerButtons[index].classList.remove('disabled');
        
        let pointsToSend = 0;
        if (historianSkillUsed) {
            pointsToSend = 1;
        } else if (!NO_BUZZ) {
            pointsToSend = calculatePotentialPoints(timeLeft);
        }
        
        DuoSocketClient.answer(index, { 
            potentialPoints: pointsToSend,
            historianSkillUsed: historianSkillUsed
        });
    }
    
    function activateHistorianSkill() {
        if (historianSkillUsed || answered || IS_BUZZ_WINNER) return;
        
        historianSkillUsed = true;
        
        const historianSection = document.getElementById('historianSkillSection');
        const waitingBanner = document.getElementById('waitingBanner');
        const historianActiveBanner = document.getElementById('historianActiveBanner');
        
        if (historianSection) historianSection.style.display = 'none';
        if (waitingBanner) waitingBanner.style.display = 'none';
        if (historianActiveBanner) historianActiveBanner.style.display = 'block';
        
        answerButtons.forEach(function(btn) {
            btn.classList.remove('waiting');
            btn.disabled = false;
        });
        
        updatePotentialPointsDisplay(1);
        
        if (!timerInterval) {
            startTimer();
        }
        
        console.log('[DuoAnswer] Historian skill activated - can answer for 1 point');
    }
    
    function showResult(isCorrect, correctIndex, pointsEarned) {
        resultOverlay.className = 'result-overlay ' + (isCorrect ? 'correct' : 'incorrect');
        resultText.textContent = isCorrect ? '{{ __("Bonne réponse !") }}' : '{{ __("Mauvaise réponse !") }}';
        
        if (isCorrect) {
            pointsText.textContent = '+' + pointsEarned + ' {{ __("points") }}';
        } else if (historianSkillUsed) {
            pointsText.textContent = '{{ __("0 point") }}';
        } else {
            pointsText.textContent = '{{ __("-2 points") }}';
        }
        
        if (!isCorrect && correctIndex !== undefined && correctIndex >= 0) {
            const choices = @json($choices);
            if (choices[correctIndex]) {
                correctAnswerText.textContent = '{{ __("La bonne réponse était :") }} ' + choices[correctIndex];
            }
        } else {
            correctAnswerText.textContent = '';
        }
        
        resultOverlay.style.display = 'block';
        
        if (isCorrect && correctSound) {
            correctSound.play().catch(function() {});
        } else if (!isCorrect && incorrectSound) {
            incorrectSound.play().catch(function() {});
        }
        
        answerButtons.forEach(function(btn, idx) {
            btn.classList.remove('selected');
            const indicator = document.getElementById('indicator' + idx);
            if (idx === correctIndex) {
                btn.classList.add('correct');
                if (indicator) indicator.textContent = '✓';
            } else if (idx === selectedIndex && !isCorrect) {
                btn.classList.add('incorrect');
                if (indicator) indicator.textContent = '✗';
            }
        });
    }
    
    answerButtons.forEach(function(btn, index) {
        btn.addEventListener('click', function() {
            selectAnswer(index);
        });
    });
    
    const historianSkillBtn = document.getElementById('historianSkillBtn');
    if (historianSkillBtn) {
        historianSkillBtn.addEventListener('click', activateHistorianSkill);
    }
    
    DuoSocketClient.onConnect = function() {
        updateConnectionStatus('connected');
        
        DuoSocketClient.joinRoom(ROOM_ID, LOBBY_CODE, {
            token: JWT_TOKEN
        });
    };
    
    DuoSocketClient.onDisconnect = function(reason) {
        updateConnectionStatus('disconnected');
    };
    
    DuoSocketClient.onError = function(error) {
        console.error('[DuoAnswer] Socket error:', error);
    };
    
    DuoSocketClient.onAnswerRevealed = function(data) {
        if (isRedirecting) return;
        
        waitingOverlay.style.display = 'none';
        
        const isCorrect = data.isCorrect || false;
        const correctIndex = data.correctIndex !== undefined ? data.correctIndex : data.correctAnswer;
        const pointsEarned = data.points || data.pointsEarned || 0;
        
        showResult(isCorrect, correctIndex, pointsEarned);
        
        setTimeout(function() {
            if (isRedirecting) return;
            
            if (data.nextUrl) {
                isRedirecting = true;
                window.location.href = data.nextUrl;
            } else if (data.matchEnded) {
                isRedirecting = true;
                window.location.href = '/duo/result/' + MATCH_ID;
            }
        }, 3000);
    };
    
    DuoSocketClient.onRoundEnded = function(data) {
        if (isRedirecting) return;
        
        setTimeout(function() {
            if (isRedirecting) return;
            isRedirecting = true;
            
            if (data.nextQuestionUrl) {
                window.location.href = data.nextQuestionUrl;
            } else {
                window.location.href = '/duo/question/' + MATCH_ID;
            }
        }, 2000);
    };
    
    DuoSocketClient.onMatchEnded = function(data) {
        if (isRedirecting) return;
        isRedirecting = true;
        
        setTimeout(function() {
            window.location.href = '/duo/result/' + MATCH_ID;
        }, 2000);
    };
    
    DuoSocketClient.onScoreUpdate = function(data) {
        console.log('[DuoAnswer] Score update received:', data);
        const playerScoreEl = document.getElementById('playerScoreValue');
        if (playerScoreEl && data.score !== undefined) {
            const dataPlayerId = String(data.playerId).replace('player:', '');
            const currentPlayerId = String(PLAYER_ID);
            if (dataPlayerId === currentPlayerId || data.playerId == PLAYER_ID) {
                playerScoreEl.textContent = data.score;
            }
        }
    };
    
    if (GAME_SERVER_URL) {
        updateConnectionStatus('connecting');
        DuoSocketClient.connect(GAME_SERVER_URL, JWT_TOKEN)
            .then(function() {
                console.log('[DuoAnswer] Connected to game server');
            })
            .catch(function(error) {
                console.error('[DuoAnswer] Failed to connect:', error);
                updateConnectionStatus('disconnected');
            });
    }
    
    initSkillButtons();
    
    if (IS_BUZZ_WINNER || NO_BUZZ) {
        startTimer();
    } else {
        waitingOverlay.style.display = 'flex';
    }
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
    let isMicActive = false;
    const VOICE_LOBBY_CODE = '{{ $lobby_code ?? "" }}';
    const CURRENT_PLAYER_ID = {{ auth()->id() ?? 0 }};
    
    const micButton = document.getElementById('voiceMicButton');
    const micIcon = document.getElementById('micIcon');
    
    function updateMicButtonState(active) {
        isMicActive = active;
        if (micButton && micIcon) {
            if (active) {
                micButton.classList.add('active');
                micButton.classList.remove('muted');
                micIcon.textContent = '🎤';
            } else {
                micButton.classList.remove('active');
                micButton.classList.add('muted');
                micIcon.textContent = '🔇';
            }
        }
    }
    
    async function toggleMicrophone() {
        if (!voiceChat) return;
        try {
            const newState = await voiceChat.toggleMicrophone();
            updateMicButtonState(newState);
        } catch (error) {
            console.error('[VoiceChat] Toggle mic error:', error);
        }
    }
    
    async function initVoiceChat() {
        if (!VOICE_LOBBY_CODE || !window.voiceChatDb) {
            console.log('[VoiceChat] Missing lobby code or Firebase - hiding mic button');
            if (micButton) micButton.style.display = 'none';
            return;
        }
        
        try {
            voiceChat = new VoiceChat({
                sessionId: VOICE_LOBBY_CODE,
                localUserId: CURRENT_PLAYER_ID,
                mode: 'duo',
                db: window.voiceChatDb,
                onConnectionChange: (state) => {
                    if (state.muted !== undefined) updateMicButtonState(!state.muted);
                },
                onError: (error) => console.error('[VoiceChat] Error:', error)
            });
            
            await voiceChat.initialize();
            console.log('[VoiceChat] Background audio initialized successfully');
            
            if (micButton) {
                micButton.addEventListener('click', toggleMicrophone);
                updateMicButtonState(false);
            }
        } catch (error) {
            console.error('[VoiceChat] Init error:', error);
            if (micButton) micButton.style.display = 'none';
        }
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(initVoiceChat, 1000);
    });
    
    window.addEventListener('beforeunload', () => {
        if (voiceChat) voiceChat.cleanup();
    });
})();
</script>
@endsection
