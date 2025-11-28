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
    
    .result-icon {
        font-size: 80px;
        margin-bottom: 10px;
        animation: scaleIn 0.5s ease-out;
        flex-shrink: 0;
    }
    
    .result-title {
        font-size: 2rem;
        font-weight: 900;
        margin-bottom: 15px;
        animation: slideDown 0.6s ease-out;
        text-transform: uppercase;
        letter-spacing: 2px;
        flex-shrink: 0;
    }
    
    .result-correct .result-title {
        color: #2ECC71;
        text-shadow: 0 0 30px rgba(46, 204, 113, 0.8);
    }
    
    .result-incorrect .result-title {
        color: #E74C3C;
        text-shadow: 0 0 30px rgba(231, 76, 60, 0.8);
    }
    
    /* Round Details */
    .round-details {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 12px;
        padding: 8px;
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
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.9rem;
    }
    
    .points-lost {
        background: linear-gradient(135deg, #f093fb, #f5576c);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.9rem;
    }
    
    .points-neutral {
        background: rgba(255,255,255,0.1);
        color: #95a5a6;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .speed-indicator {
        font-size: 0.75rem;
        color: #95a5a6;
        padding: 2px 8px;
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
    }
    
    .speed-indicator.first {
        color: #f39c12;
        background: rgba(243, 156, 18, 0.2);
    }
    
    /* Score Battle Display */
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
    
    /* Answers display */
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
    
    /* Informations de progression */
    .progress-info {
        background: rgba(0,0,0,0.3);
        border: 2px solid rgba(78, 205, 196, 0.3);
        border-radius: 10px;
        padding: 12px;
        margin-top: 10px;
        margin-bottom: 15px;
        backdrop-filter: blur(10px);
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    
    .info-item {
        background: rgba(78, 205, 196, 0.1);
        border: 1px solid rgba(78, 205, 196, 0.3);
        border-radius: 6px;
        padding: 8px 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .info-label {
        font-size: 0.75rem;
        color: #4ECDC4;
        font-weight: 600;
    }
    
    .info-value {
        font-size: 0.85rem;
        color: white;
        font-weight: bold;
    }
    
    /* Section "Le saviez-vous" */
    .did-you-know {
        background: rgba(102, 126, 234, 0.15);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 15px;
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
    
    /* Timer next question et boutons */
    .result-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex-shrink: 0;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
        justify-content: center;
    }
    
    .btn-action {
        flex: 1;
        padding: 12px 20px;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .btn-menu {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    
    .btn-menu:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    }
    
    .btn-go {
        background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
        color: white;
        box-shadow: 0 5px 20px rgba(78, 205, 196, 0.4);
    }
    
    .btn-go:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(78, 205, 196, 0.6);
    }
    
    .next-question-timer {
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.2) 0%, rgba(102, 126, 234, 0.2) 100%);
        padding: 15px;
        border-radius: 15px;
        font-size: 1rem;
        border: 2px solid rgba(78, 205, 196, 0.3);
        animation: fadeIn 1.2s ease-out;
    }
    
    .timer-count {
        font-size: 2rem;
        font-weight: 900;
        color: #4ECDC4;
        display: inline-block;
        margin: 0 5px;
        animation: pulse 1s infinite;
    }
    
    /* Skills Magicienne */
    .skills-container {
        background: rgba(102, 126, 234, 0.15);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 15px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .skills-title {
        font-size: 1.1rem;
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
        font-size: 2rem;
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
            transform: translateY(-50px);
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
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.15);
        }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .result-title {
            font-size: 2rem;
        }
        
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
    
    /* === RESPONSIVE POUR ORIENTATION === */
    
    /* Mobile Portrait */
    @media (max-width: 480px) and (orientation: portrait) {
        .result-container {
            padding: 16px;
        }
        
        .result-title {
            font-size: 1.8rem;
        }
        
        .result-icon {
            font-size: 4rem;
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
    
    /* Mobile Paysage */
    @media (max-height: 500px) and (orientation: landscape) {
        .result-container {
            padding: 10px;
            max-height: 100vh;
            overflow-y: auto;
        }
        
        .result-correct, .result-incorrect {
            padding: 12px;
            margin-bottom: 12px;
        }
        
        .result-title {
            font-size: 1.5rem;
        }
        
        .result-icon {
            font-size: 3rem;
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
        
        .next-button {
            padding: 12px 24px;
        }
    }
    
    /* Tablettes Portrait */
    @media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
        .result-title {
            font-size: 2.2rem;
        }
        
        .score-number {
            font-size: 2.8rem;
        }
    }
    
    /* Tablettes Paysage */
    @media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
        .result-container {
            padding: 18px;
        }
        
        .score-battle {
            gap: 20px;
        }
    }
</style>

<div class="result-container">
    <!-- En-tête avec nom de l'adversaire -->
    <div class="opponent-header">
        <h2 class="opponent-name">Vs {{ $params['opponent_name'] ?? 'Adversaire' }}</h2>
    </div>
    
    <!-- Détails du round -->
    @if(isset($params['player_points']))
    <div class="round-details">
        <div class="round-player">
            <div class="round-info">
                <span class="round-label">🎮 Vous</span>
                @if($params['player_points'] > 0)
                    <span class="points-gained">+{{ $params['player_points'] }}</span>
                @elseif($params['player_points'] < 0)
                    <span class="points-lost">{{ $params['player_points'] }}</span>
                @else
                    <span class="points-neutral">0</span>
                @endif
            </div>
            @if(isset($params['opponent_faster']) && $params['opponent_faster'])
                <div class="speed-indicator">⏱️ 2ème</div>
            @elseif($params['player_points'] != 0 && !isset($params['is_timeout']))
                <div class="speed-indicator first">⚡ 1er</div>
            @endif
        </div>
        
        <div class="round-opponent">
            <div class="round-info">
                <span class="round-label">🎯 {{ $params['opponent_name'] ?? 'Adversaire' }}</span>
                @if(isset($params['opponent_buzzed']) && !$params['opponent_buzzed'])
                    <span class="points-neutral">Pas buzzé</span>
                @elseif(isset($params['opponent_points']))
                    @if($params['opponent_points'] > 0)
                        <span class="points-gained">+{{ $params['opponent_points'] }}</span>
                    @elseif($params['opponent_points'] < 0)
                        <span class="points-lost">{{ $params['opponent_points'] }}</span>
                    @else
                        <span class="points-neutral">0</span>
                    @endif
                @endif
            </div>
            @if(isset($params['opponent_faster']) && $params['opponent_faster'] && isset($params['opponent_buzzed']) && $params['opponent_buzzed'])
                <div class="speed-indicator first">⚡ 1er</div>
            @elseif(isset($params['opponent_buzzed']) && $params['opponent_buzzed'] && $params['opponent_points'] != 0 && !$params['opponent_faster'])
                <div class="speed-indicator">⏱️ 2ème</div>
            @endif
        </div>
    </div>
    @endif
    
    <!-- Score Battle -->
    <div class="score-battle">
        <div class="score-player">
            <div class="score-label">🎮 Vous</div>
            <div class="score-number">{{ $params['score'] }}</div>
        </div>
        
        <div class="vs-divider">VS</div>
        
        <div class="score-opponent">
            <div class="score-label">🎯 {{ $params['opponent_name'] ?? 'Adversaire' }}</div>
            <div class="score-number">{{ $params['opponent_score'] ?? 0 }}</div>
        </div>
    </div>
    
    <!-- Skills Magicienne -->
    @php
        $avatar = session('avatar', 'Aucun');
        $usedSkills = session('used_skills', []);
        $cancelErrorUsed = in_array('cancel_error', $usedSkills);
        $bonusQuestionUsed = in_array('bonus_question', $usedSkills);
    @endphp
    
    @if($avatar === 'Magicienne')
    <div class="skills-container">
        <div class="skills-title">✨ Compétences Magicienne ✨</div>
        <div class="skills-grid">
            <!-- Skill 1: Annule erreur -->
            <div class="skill-item {{ $cancelErrorUsed ? 'used' : '' }}">
                <div class="skill-icon">⭐</div>
                <div class="skill-info">
                    <div class="skill-name">Annule erreur</div>
                </div>
                @if($cancelErrorUsed)
                    <div class="skill-used-badge">UTILISÉ</div>
                @elseif(!$params['is_correct'] && isset($params['player_points']) && $params['player_points'] < 0)
                    <button class="skill-btn" id="cancelErrorBtn" onclick="useCancelError()">Activer</button>
                @else
                    <button class="skill-btn" disabled>Activer</button>
                @endif
            </div>
            
            <!-- Skill 2: Question bonus -->
            <div class="skill-item {{ $bonusQuestionUsed ? 'used' : '' }}">
                <div class="skill-icon">✨</div>
                <div class="skill-info">
                    <div class="skill-name">Question bonus</div>
                </div>
                @if($bonusQuestionUsed)
                    <div class="skill-used-badge">UTILISÉ</div>
                @else
                    <button class="skill-btn" onclick="useBonusQuestion()">Activer</button>
                @endif
            </div>
        </div>
    </div>
    @endif
    
    {{-- Skills Visionnaire --}}
    @if($avatar === 'Visionnaire')
    @php
        $previewUsed = in_array('preview_questions', $usedSkills);
        $previewsRemaining = session('visionnaire_previews_remaining', 5);
        $nextQuestion = session('visionnaire_next_question');
    @endphp
    <div class="skills-container">
        <div class="skills-title">👁️ {{ __('Compétences Visionnaire') }} 👁️</div>
        <div class="skills-grid">
            <!-- Skill: Voir la question suivante -->
            <div class="skill-item">
                <div class="skill-icon" style="cursor: pointer;" onclick="showVisionnairePreview()" title="{{ __('Voir la question suivante') }}">👁️</div>
                <div class="skill-info">
                    <div class="skill-name">{{ __('Question suivante') }}</div>
                    <div class="skill-desc" style="font-size: 0.75rem; opacity: 0.7;">{{ $previewsRemaining }}/5 {{ __('restantes') }}</div>
                </div>
                @if($previewsRemaining > 0)
                    <button class="skill-btn" onclick="showVisionnairePreview()" style="background: linear-gradient(135deg, #8a2be2 0%, #6a1cb2 100%);">{{ __('Voir') }}</button>
                @else
                    <div class="skill-used-badge">{{ __('ÉPUISÉ') }}</div>
                @endif
            </div>
        </div>
    </div>
    @endif
    
    <!-- Answers -->
    <div class="result-answers">
        @php
            $question = $params['question'];
            $userAnswerIndex = $params['answer_index'];
            $correctIndex = $question['correct_index'];
            $isTimeout = $params['is_timeout'] ?? false;
        @endphp
        
        @if(!$params['is_correct'])
            <!-- Afficher la réponse incorrecte du joueur ou le timeout -->
            <div class="answer-display answer-incorrect">
                <span class="answer-label">{{ __('Votre réponse') }}:</span>
                <span class="answer-text">
                    @if($isTimeout)
                        ⏰ {{ __('Temps écoulé - Pas de buzz') }}
                    @elseif($userAnswerIndex === -1)
                        ❌ {{ __('Aucun choix sélectionné') }}
                    @else
                        {{ $question['answers'][$userAnswerIndex] }}
                    @endif
                </span>
                <span class="answer-icon">❌</span>
            </div>
        @endif
        
        <!-- Afficher la bonne réponse -->
        <div class="answer-display answer-correct">
            <span class="answer-label">{{ __('Bonne réponse') }}:</span>
            <span class="answer-text">{{ $question['answers'][$correctIndex] }}</span>
            <span class="answer-icon">✅</span>
        </div>
    </div>
    
    <!-- Informations de progression en 2 colonnes -->
    <div class="progress-info">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">⚔️ {{ __('Score') }}:</span>
                <span class="info-value">{{ $params['player_rounds_won'] ?? 0 }}-{{ $params['opponent_rounds_won'] ?? 0 }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">❤️ {{ __('Vies') }}:</span>
                <span class="info-value">{{ $params['vies_restantes'] ?? config('game.life_max', 3) }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">📈 {{ __('Progression') }}:</span>
                <span class="info-value">{{ $params['current_question'] ?? 1 }}/{{ $params['total_questions'] ?? 30 }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">🎯 {{ __('Niveau') }}:</span>
                <span class="info-value">{{ $params['niveau'] ?? 1 }}</span>
            </div>
        </div>
    </div>
    
    <!-- Section "Le saviez-vous" -->
    <div class="did-you-know">
        <div class="did-you-know-title">💡 {{ __('Le saviez-vous ?') }}</div>
        <div class="did-you-know-content">
            {{ $params['did_you_know'] ?? __('Chargement...') }}
        </div>
    </div>
    
    <!-- Actions: Boutons et Timer -->
    <div class="result-actions">
        <div class="action-buttons">
            <a href="{{ route('solo.index') }}" class="btn-action btn-menu">
                ← Solo
            </a>
            
            @php
                $gameMode = session('game_mode', 'solo');
                $isMultiplayer = in_array($gameMode, ['duo', 'league', 'master']);
            @endphp
            
            @if($isMultiplayer)
                <button onclick="markAsReady()" class="btn-action btn-ready" id="readyBtn">
                    ✅ Prêt (<span id="readyCount">0</span>/<span id="totalPlayers">2</span>)
                </button>
            @else
                <button onclick="goToNextQuestion()" class="btn-action btn-go">
                    🚀 GO
                </button>
            @endif
        </div>
        
        <div class="next-question-timer">
            @if($isMultiplayer)
                {{ __('En attente des autres joueurs...') }}
            @else
                {{ __('Prochaine question dans') }} <span class="timer-count" id="countdown">15</span> {{ __('secondes...') }}
            @endif
        </div>
    </div>
</div>

<!-- Musique d'ambiance du gameplay (continue depuis game_answer) -->
<audio id="gameplayAmbient" preload="auto" loop>
    <source src="{{ asset('sounds/gameplay_ambient.mp3') }}" type="audio/mpeg">
</audio>

<script>
// Vérifier si la musique de gameplay est activée
function isGameplayMusicEnabled() {
    const enabled = localStorage.getItem('gameplay_music_enabled');
    return enabled === null || enabled === 'true'; // Activé par défaut
}

// Continuer la musique d'ambiance du gameplay SEULEMENT si activée
const gameplayAmbient = document.getElementById('gameplayAmbient');
gameplayAmbient.volume = 0.5; // -6 dB ≈ 50% de volume

if (isGameplayMusicEnabled()) {
    const savedTime = parseFloat(localStorage.getItem('gameplayMusicTime') || '0');
    gameplayAmbient.addEventListener('loadedmetadata', function() {
        if (savedTime > 0 && savedTime < gameplayAmbient.duration) {
            gameplayAmbient.currentTime = savedTime;
        }
        
        gameplayAmbient.play().catch(e => {
            console.log('Gameplay music autoplay blocked:', e);
            document.addEventListener('click', function playGameplayMusic() {
                gameplayAmbient.play().catch(err => console.log('Audio play failed:', err));
                document.removeEventListener('click', playGameplayMusic);
            }, { once: true });
        });
    });

    setInterval(() => {
        if (!gameplayAmbient.paused) {
            localStorage.setItem('gameplayMusicTime', gameplayAmbient.currentTime.toString());
        }
    }, 1000);

    window.addEventListener('beforeunload', () => {
        localStorage.setItem('gameplayMusicTime', gameplayAmbient.currentTime.toString());
    });
}

// Compte à rebours de 15 secondes
let countdown = 15;
const countdownElement = document.getElementById('countdown');

const interval = setInterval(() => {
    countdown--;
    if (countdown > 0) {
        countdownElement.textContent = countdown;
    } else {
        clearInterval(interval);
        // Rediriger vers la prochaine question
        goToNextQuestion();
    }
}, 1000);

// Skills Magicienne
function useCancelError() {
    fetch("{{ route('solo.cancel-error') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mettre à jour le score affiché immédiatement
            const scoreElement = document.querySelector('.score-player .score-number');
            if (scoreElement && data.new_score !== undefined) {
                scoreElement.textContent = data.new_score;
            }
            
            // Supprimer le bouton du skill pour éviter double utilisation
            const cancelErrorBtn = document.getElementById('cancelErrorBtn');
            if (cancelErrorBtn) {
                cancelErrorBtn.remove();
            }
            
            // Afficher le message de confirmation
            showSkillMessage(data.message);
        } else {
            showSkillMessage(data.message || 'Erreur lors de l\'activation du skill', false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showSkillMessage('Erreur lors de l\'activation du skill', false);
    });
}

function showSkillMessage(message, isSuccess = true) {
    // Créer l'overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        animation: fadeIn 0.3s ease-out;
    `;
    
    // Créer la boîte de message
    const messageBox = document.createElement('div');
    messageBox.style.cssText = `
        background: linear-gradient(135deg, ${isSuccess ? '#2ECC71' : '#E74C3C'} 0%, ${isSuccess ? '#27AE60' : '#C0392B'} 100%);
        color: white;
        padding: 30px 40px;
        border-radius: 20px;
        text-align: center;
        font-size: 1.3rem;
        font-weight: 700;
        box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        max-width: 90%;
        animation: scaleIn 0.3s ease-out;
    `;
    messageBox.textContent = message;
    
    overlay.appendChild(messageBox);
    document.body.appendChild(overlay);
    
    // Auto-fermeture après 2 secondes pour les succès (compétence +2 points)
    if (isSuccess) {
        setTimeout(() => overlay.remove(), 2000);
    } else {
        // Fermer au clic pour les erreurs
        overlay.addEventListener('click', () => overlay.remove());
    }
}

function useBonusQuestion() {
    window.location.href = "{{ route('solo.bonus-question') }}";
}

// Protection contre les double-clics
let navigationInProgress = false;

function goToNextQuestion() {
    // Empêcher les appels multiples
    if (navigationInProgress) {
        return;
    }
    
    navigationInProgress = true;
    clearInterval(interval);
    
    // Désactiver le bouton GO pour éviter les double-clics
    const goButton = document.querySelector('.btn-go');
    if (goButton) {
        goButton.disabled = true;
        goButton.style.opacity = '0.5';
        goButton.style.cursor = 'not-allowed';
        goButton.textContent = '⏳ Chargement...';
    }
    
    window.location.href = "{{ route('solo.next') }}";
}

// GÉNÉRATION PROACTIVE DYNAMIQUE AVEC IDEMPOTENCE : Génère les blocs basés sur le nombre de questions configuré
(function() {
    const currentQuestion = {{ $params['current_question'] ?? 1 }};
    const currentRound = {{ $params['current_round'] ?? 1 }};
    const questionsPerRound = {{ session('nb_questions', 10) }};
    const matchUuid = "{{ session('match_uuid', 'default') }}"; // Identifiant unique de match
    
    // GARDE D'IDEMPOTENCE : Récupérer les blocs déjà demandés pour ce match + cette manche
    const storageKey = `blocks_${matchUuid}_round_${currentRound}`;
    let requestedBlocks = [];
    try {
        const stored = sessionStorage.getItem(storageKey);
        requestedBlocks = stored ? JSON.parse(stored) : [];
    } catch (e) {
        console.warn('[PROGRESSIVE] Failed to read sessionStorage:', e);
    }
    
    // Architecture progressive : Bloc 1 = 2 questions (déjà généré au démarrage), blocs suivants = 3 questions chacun
    const blocksNeeded = Math.ceil((questionsPerRound - 2) / 3);
    
    // CALCUL DYNAMIQUE DES TRIGGERS : Déclencher chaque bloc 1 QUESTION PLUS TÔT pour éviter l'attente
    // ANCIEN: Bloc 2 à Q2, Bloc 3 à Q5, Bloc 4 à Q8 → TROP TARD, génération pas finie
    // NOUVEAU: Bloc 2 à Q1, Bloc 3 à Q4, Bloc 4 à Q7 → Génération pendant le gameplay
    for (let blockId = 2; blockId <= blocksNeeded + 1; blockId++) {
        // Calcul du trigger : Bloc 2 à Q1, puis tous les 3 questions (décalé de -1)
        const triggerQuestion = blockId === 2 ? 1 : 1 + (blockId - 2) * 3;
        
        // Ne pas déclencher si le trigger dépasse le nombre total de questions
        if (triggerQuestion > questionsPerRound) {
            break;
        }
        
        // IDEMPOTENCE : Déclencher si on a ATTEINT OU DÉPASSÉ le trigger ET que ce bloc n'a jamais été demandé
        // Cela permet la récupération si un fetch a échoué ou si la page a été rechargée
        if (currentQuestion >= triggerQuestion && !requestedBlocks.includes(blockId)) {
            console.log(`[PROGRESSIVE] Requesting block ${blockId} at Q${currentQuestion} (trigger was Q${triggerQuestion})`);
            
            const startTime = performance.now();
            fetch("{{ route('solo.generate-block') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    count: 3,
                    round: currentRound,
                    block_id: blockId
                })
            }).then(response => response.json())
              .then(data => {
                  const duration = ((performance.now() - startTime) / 1000).toFixed(1);
                  // MARQUER COMME DEMANDÉ SEULEMENT APRÈS SUCCÈS
                  if (data.success) {
                      requestedBlocks.push(blockId);
                      sessionStorage.setItem(storageKey, JSON.stringify(requestedBlocks));
                      console.log(`[PROGRESSIVE] ✅ Block ${blockId} generated in ${duration}s:`, data);
                  } else {
                      console.error(`[PROGRESSIVE] ❌ Block ${blockId} returned success=false after ${duration}s:`, data);
                  }
              })
              .catch(err => {
                  const duration = ((performance.now() - startTime) / 1000).toFixed(1);
                  console.error(`[PROGRESSIVE] ❌ Block ${blockId} failed after ${duration}s - will retry:`, err);
                  // NE PAS marquer comme demandé pour permettre un retry
              });
        } else if (currentQuestion >= triggerQuestion) {
            console.log(`[PROGRESSIVE] Block ${blockId} already requested, skipping`);
        }
    }
    
    // Pour Magicienne avatar : générer 1 question bonus 1 QUESTION PLUS TÔT
    @if(session('avatar') === 'Magicienne')
    const bonusBlockId = 999;
    const bonusTrigger = questionsPerRound - 1; // Déclencher à l'avant-dernière question
    if (currentQuestion >= bonusTrigger && !requestedBlocks.includes(bonusBlockId)) {
        console.log(`[PROGRESSIVE] Requesting bonus block at Q${currentQuestion} (trigger was Q${bonusTrigger})`);
        
        const bonusStartTime = performance.now();
        fetch("{{ route('solo.generate-block') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                count: 1,
                round: currentRound,
                block_id: bonusBlockId
            })
        }).then(response => response.json())
          .then(data => {
              const duration = ((performance.now() - bonusStartTime) / 1000).toFixed(1);
              // MARQUER COMME DEMANDÉ SEULEMENT APRÈS SUCCÈS
              if (data.success) {
                  requestedBlocks.push(bonusBlockId);
                  sessionStorage.setItem(storageKey, JSON.stringify(requestedBlocks));
                  console.log(`[PROGRESSIVE] ✅ Bonus block generated in ${duration}s:`, data);
              } else {
                  console.error(`[PROGRESSIVE] ❌ Bonus block returned success=false after ${duration}s:`, data);
              }
          })
          .catch(err => {
              const duration = ((performance.now() - bonusStartTime) / 1000).toFixed(1);
              console.error(`[PROGRESSIVE] ❌ Bonus block failed after ${duration}s - will retry:`, err);
          });
    }
    @endif
})();
</script>

{{-- Popup Visionnaire Preview synchronisé avec le countdown --}}
@if(session('avatar') === 'Visionnaire')
<div id="visionnairePopup" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 9999; justify-content: center; align-items: center;">
  <div style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); border: 3px solid #8a2be2; border-radius: 20px; padding: 30px; max-width: 90%; width: 500px; text-align: center; box-shadow: 0 0 40px rgba(138,43,226,0.5); animation: popupAppear 0.3s ease-out;">
    <div style="font-size: 3rem; margin-bottom: 15px;">👁️</div>
    <div style="font-size: 1.3rem; font-weight: 700; color: #8a2be2; margin-bottom: 10px;">{{ __('Question Suivante') }}</div>
    
    <div id="visionnaireQuestionText" style="font-size: 1.1rem; color: #fff; line-height: 1.5; margin: 20px 0; padding: 20px; background: rgba(138,43,226,0.15); border-radius: 12px; border: 1px solid rgba(138,43,226,0.3);">
      <em style="opacity: 0.7;">{{ __('Chargement...') }}</em>
    </div>
    
    <div id="visionnaireTheme" style="display: none; font-size: 0.9rem; color: #8a2be2; margin-bottom: 15px;"></div>
    
    <div style="font-size: 0.9rem; color: rgba(255,255,255,0.6); margin-top: 15px;">
      {{ __('Tapez n\'importe où pour fermer') }}
    </div>
    
    <div style="margin-top: 20px; font-size: 0.85rem; color: rgba(138,43,226,0.8);">
      {{ __('Previews restantes') }}: <span id="previewsRemainingDisplay">{{ session('visionnaire_previews_remaining', 5) }}</span>/5
    </div>
    
    <div id="popupCountdown" style="margin-top: 10px; font-size: 0.8rem; color: rgba(255,255,255,0.5);">
      {{ __('Fermeture automatique dans') }} <span id="popupTimer">--</span>s
    </div>
  </div>
</div>

<style>
@keyframes popupAppear {
  from { opacity: 0; transform: scale(0.8); }
  to { opacity: 1; transform: scale(1); }
}
</style>

<script>
let visionnairePreviewsRemaining = {{ session('visionnaire_previews_remaining', 5) }};
let popupOpen = false;
let popupSyncInterval = null;

function showVisionnairePreview() {
    if (visionnairePreviewsRemaining <= 0) {
        alert('{{ __("Plus de previews disponibles!") }} (0/5)');
        return;
    }
    
    const popup = document.getElementById('visionnairePopup');
    const questionText = document.getElementById('visionnaireQuestionText');
    const themeDiv = document.getElementById('visionnaireTheme');
    const previewsDisplay = document.getElementById('previewsRemainingDisplay');
    
    // Afficher le popup
    popup.style.display = 'flex';
    popupOpen = true;
    
    // Fetch la question suivante du serveur
    fetch("{{ route('solo.use-skill') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ skill: 'preview_questions' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.next_question) {
            questionText.innerHTML = data.next_question.text || '{{ __("Question non disponible") }}';
            
            if (data.next_question.theme) {
                themeDiv.style.display = 'block';
                themeDiv.innerHTML = '{{ __("Thème") }}: <strong>' + data.next_question.theme + '</strong>';
            }
            
            // Mettre à jour le compteur
            visionnairePreviewsRemaining = data.previews_remaining;
            previewsDisplay.textContent = visionnairePreviewsRemaining;
            
            // Mettre à jour le bouton sur la page
            const skillDesc = document.querySelector('.skills-container .skill-desc');
            if (skillDesc) {
                skillDesc.textContent = visionnairePreviewsRemaining + '/5 {{ __("restantes") }}';
            }
        } else {
            questionText.innerHTML = '<em style="opacity: 0.7;">' + (data.message || '{{ __("Erreur lors du chargement") }}') + '</em>';
        }
    })
    .catch(err => {
        questionText.innerHTML = '<em style="opacity: 0.7;">{{ __("Erreur de connexion") }}</em>';
    });
    
    // Synchroniser avec le countdown existant
    syncPopupWithCountdown();
    popupSyncInterval = setInterval(syncPopupWithCountdown, 100);
    
    // Fermer au clic
    popup.onclick = function(e) {
        closeVisionnairePopup();
    };
}

function syncPopupWithCountdown() {
    const countdownElement = document.getElementById('countdown');
    const popupTimerElement = document.getElementById('popupTimer');
    
    if (countdownElement && popupTimerElement) {
        const remaining = parseInt(countdownElement.textContent);
        popupTimerElement.textContent = remaining;
        
        // Fermer automatiquement quand le countdown atteint 0
        if (remaining <= 0) {
            closeVisionnairePopup();
        }
    }
}

function closeVisionnairePopup() {
    const popup = document.getElementById('visionnairePopup');
    popup.style.display = 'none';
    popupOpen = false;
    
    if (popupSyncInterval) {
        clearInterval(popupSyncInterval);
        popupSyncInterval = null;
    }
}

// Fermer avec Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && popupOpen) {
        closeVisionnairePopup();
    }
});
</script>
@endif
@endsection
