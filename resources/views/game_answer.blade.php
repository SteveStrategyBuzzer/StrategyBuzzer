@extends('layouts.app')

@section('content')
@php
// Mode de jeu (solo, duo, league_individual, league_team, master)
$mode = $params['mode'] ?? 'solo';

// Index de la bonne réponse
$correctIndex = $params['question']['correct_index'] ?? -1;

// Toutes les réponses pour vérification JavaScript
$allAnswers = $params['question']['answers'] ?? [];

// Route dynamique selon le mode
$answerRoute = match($mode) {
    'duo' => route('game.answer', ['mode' => 'duo']),
    'league_individual' => route('game.answer', ['mode' => 'league_individual']),
    'league_team' => route('game.answer', ['mode' => 'league_team']),
    'master' => route('game.answer', ['mode' => 'master']),
    default => route('solo.answer'),
};

// Skill Historien - Plume (knowledge_without_time)
$hasFeatherSkill = false;
$featherSkillAvailable = false;
$usedSkills = $params['used_skills'] ?? session('used_skills', []);
$avatarSkillsFull = $params['avatar_skills_full'] ?? [];
if (!empty($avatarSkillsFull['skills'])) {
    foreach ($avatarSkillsFull['skills'] as $skill) {
        if (($skill['id'] ?? '') === 'knowledge_without_time') {
            $hasFeatherSkill = true;
            $featherSkillAvailable = !in_array('knowledge_without_time', $usedSkills);
            break;
        }
    }
}
// La Plume est active si le joueur n'a pas buzzé ET le skill est disponible
$playerBuzzed = $params['player_buzzed'] ?? true;
$featherActive = $hasFeatherSkill && $featherSkillAvailable && !$playerBuzzed;

// Mathématicien: illuminate_numbers - skill disponible (vérifier dans avatarSkillsFull pour coéquipier)
$illuminateSkillAvailable = false;
if (!empty($avatarSkillsFull['skills'])) {
    foreach ($avatarSkillsFull['skills'] as $skill) {
        if (($skill['id'] ?? '') === 'illuminate_numbers') {
            $illuminateSkillAvailable = !in_array('illuminate_numbers', $usedSkills);
            break;
        }
    }
}
// Fallback sur les params si déjà calculé
if (!$illuminateSkillAvailable) {
    $illuminateSkillAvailable = $params['illuminate_skill_available'] ?? false;
}

// Vérifier si la bonne réponse contient un chiffre (pour rendre l'icône brillante)
$correctAnswerText = $allAnswers[$correctIndex] ?? '';
$correctAnswerHasNumber = preg_match('/\d/', $correctAnswerText);
$illuminateCanActivate = $illuminateSkillAvailable && $correctAnswerHasNumber;

// Scientifique: acidify_error - skill disponible
$acidifySkillAvailable = false;
if (!empty($avatarSkillsFull['skills'])) {
    foreach ($avatarSkillsFull['skills'] as $skill) {
        if (($skill['id'] ?? '') === 'acidify_error') {
            $acidifySkillAvailable = !in_array('acidify_error', $usedSkills);
            break;
        }
    }
}

// Explorateur: see_opponent_choice - skill disponible (vérifier dans avatarSkillsFull pour coéquipier)
$seeOpponentSkillAvailable = false;
if (!empty($avatarSkillsFull['skills'])) {
    foreach ($avatarSkillsFull['skills'] as $skill) {
        if (($skill['id'] ?? '') === 'see_opponent_choice') {
            $seeOpponentSkillAvailable = !in_array('see_opponent_choice', $usedSkills);
            break;
        }
    }
}
// Fallback sur les params si déjà calculé
if (!$seeOpponentSkillAvailable) {
    $seeOpponentSkillAvailable = $params['see_opponent_skill_available'] ?? false;
}
$opponentAnswerChoice = $params['opponent_answer_choice'] ?? null;

// Challenger: shuffle_answers - les réponses bougent toutes les 1.5 sec
$shuffleAnswersActive = session('shuffle_answers_active', false);
$shuffleQuestionsLeft = session('shuffle_answers_questions_left', 0);

// IA Junior: ai_suggestion - skill disponible (suggère la réponse à 90%)
$aiSuggestionSkillAvailable = false;
if (!empty($avatarSkillsFull['skills'])) {
    foreach ($avatarSkillsFull['skills'] as $skill) {
        if (($skill['id'] ?? '') === 'ai_suggestion') {
            $aiSuggestionSkillAvailable = !in_array('ai_suggestion', $usedSkills);
            break;
        }
    }
}

// IA Junior: eliminate_two - skill disponible (élimine 2 mauvaises réponses)
$eliminateTwoSkillAvailable = false;
if (!empty($avatarSkillsFull['skills'])) {
    foreach ($avatarSkillsFull['skills'] as $skill) {
        if (($skill['id'] ?? '') === 'eliminate_two') {
            $eliminateTwoSkillAvailable = !in_array('eliminate_two', $usedSkills);
            break;
        }
    }
}

// IA Junior: replay - skill disponible (rejouer après une erreur)
$replaySkillAvailable = false;
if (!empty($avatarSkillsFull['skills'])) {
    foreach ($avatarSkillsFull['skills'] as $skill) {
        if (($skill['id'] ?? '') === 'replay') {
            $replaySkillAvailable = !in_array('replay', $usedSkills);
            break;
        }
    }
}
@endphp

<style>
    body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        color: #fff;
        min-height: 100vh;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 5px;
        overflow: hidden;
        margin: 0;
    }
    
    .answer-container {
        max-width: 900px;
        width: 100%;
        margin: 0 auto;
        padding: 10px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        max-height: 100vh;
        position: relative;
        z-index: 10;
    }
    
    /* Header info */
    .answer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        flex-wrap: wrap;
        gap: 10px;
        flex-shrink: 0;
    }
    
    .answer-info {
        background: linear-gradient(135deg, rgba(78, 205, 196, 0.2) 0%, rgba(102, 126, 234, 0.2) 100%);
        padding: 8px 15px;
        border-radius: 15px;
        border: 2px solid rgba(78, 205, 196, 0.3);
        backdrop-filter: blur(10px);
        flex: 1;
        min-width: 150px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .answer-title {
        font-size: 0.85rem;
        color: #4ECDC4;
        margin-bottom: 3px;
        font-weight: 600;
    }
    
    .answer-value {
        font-size: 1.2rem;
        font-weight: bold;
    }
    
    .score-box {
        text-align: right;
    }
    
    /* Timer barre */
    .answer-timer {
        margin-bottom: 10px;
        position: relative;
        flex-shrink: 0;
    }
    
    .timer-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        font-size: 0.8rem;
    }
    
    .timer-bar-container {
        height: 8px;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        overflow: hidden;
        position: relative;
        border: 2px solid rgba(255,255,255,0.2);
    }
    
    .timer-bar {
        height: 100%;
        background: linear-gradient(90deg, #4ECDC4 0%, #667eea 100%);
        transition: width 1s linear;
        border-radius: 8px;
        box-shadow: 0 0 20px rgba(78, 205, 196, 0.6);
    }
    
    .timer-bar.warning {
        background: linear-gradient(90deg, #FF6B6B 0%, #EE5A6F 100%);
        animation: timer-pulse 0.5s infinite;
    }
    
    @keyframes timer-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    
    /* Choix de réponses - Bulles stylisées */
    .answers-grid {
        display: grid;
        gap: 8px;
        margin-bottom: 10px;
        flex: 1;
        overflow-y: auto;
        position: relative;
    }
    
    /* Skill Challenger: Shuffle answers - animation de déplacement */
    .answers-grid.shuffle-active .answer-bubble {
        transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), 
                    opacity 0.2s ease;
    }
    
    .answers-grid.shuffle-active .answer-bubble.shuffling {
        opacity: 0.7;
    }
    
    .shuffle-indicator {
        position: absolute;
        top: -25px;
        right: 10px;
        font-size: 0.75rem;
        color: #FF6B6B;
        display: flex;
        align-items: center;
        gap: 5px;
        animation: pulse-shuffle 1.5s infinite;
    }
    
    @keyframes pulse-shuffle {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .answer-bubble {
        background: linear-gradient(145deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
        border: 2px solid rgba(102, 126, 234, 0.4);
        border-radius: 15px;
        padding: 12px 18px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        backdrop-filter: blur(5px);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .answer-bubble::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        transition: left 0.5s;
    }
    
    .answer-bubble:hover::before {
        left: 100%;
    }
    
    .answer-bubble:hover:not(.disabled) {
        transform: translateX(8px) scale(1.02);
        border-color: #4ECDC4;
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.25) 0%, rgba(102, 126, 234, 0.25) 100%);
        box-shadow: 0 10px 30px rgba(78, 205, 196, 0.4);
    }
    
    .answer-bubble:active:not(.disabled) {
        transform: translateX(4px) scale(0.98);
    }
    
    .answer-bubble.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .answer-number {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: bold;
        flex-shrink: 0;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.5);
    }
    
    .answer-text {
        font-size: 0.95rem;
        font-weight: 500;
        flex: 1;
    }
    
    .answer-icon {
        font-size: 1.2rem;
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .answer-bubble:hover .answer-icon {
        opacity: 1;
    }
    
    /* Plume toujours visible quand le skill est actif */
    .answer-icon.feather-active {
        opacity: 1;
    }
    
    /* IA Junior: Replay skill - icône ↩️ visible après erreur */
    .answer-icon.replay-active {
        opacity: 1;
    }
    
    /* Style pour la mauvaise réponse sélectionnée */
    .answer-bubble.wrong-selected {
        background: linear-gradient(145deg, rgba(231, 76, 60, 0.4) 0%, rgba(192, 57, 43, 0.4) 100%) !important;
        border: 3px solid #E74C3C !important;
        opacity: 0.6;
        pointer-events: none;
    }
    
    /* Style pour les réponses avec replay actif */
    .answer-bubble.replay-available {
        border: 2px solid #9B59B6 !important;
        animation: replay-pulse 1s infinite;
    }
    
    @keyframes replay-pulse {
        0%, 100% { box-shadow: 0 0 15px rgba(155, 89, 182, 0.5); }
        50% { box-shadow: 0 0 30px rgba(155, 89, 182, 0.8); }
    }
    
    /* Style pour la bonne réponse illuminée */
    .answer-bubble.highlighted {
        background: linear-gradient(145deg, rgba(78, 205, 196, 0.6) 0%, rgba(102, 234, 126, 0.6) 100%) !important;
        border-color: #4ECDC4 !important;
        box-shadow: 0 0 30px rgba(78, 205, 196, 0.9), inset 0 0 20px rgba(78, 205, 196, 0.4) !important;
        animation: glow-pulse 1.5s infinite;
    }
    
    @keyframes glow-pulse {
        0%, 100% { box-shadow: 0 0 30px rgba(78, 205, 196, 0.9), inset 0 0 20px rgba(78, 205, 196, 0.4); }
        50% { box-shadow: 0 0 50px rgba(78, 205, 196, 1), inset 0 0 30px rgba(78, 205, 196, 0.6); }
    }
    
    /* Mathématicien skill: illuminate_numbers - bordure dorée brillante quand activé */
    .answer-bubble.illuminated {
        background: linear-gradient(145deg, rgba(255, 215, 0, 0.35) 0%, rgba(255, 165, 0, 0.35) 100%) !important;
        border: 3px solid #FFD700 !important;
        box-shadow: 0 0 30px rgba(255, 215, 0, 0.9), 0 0 60px rgba(255, 165, 0, 0.5), inset 0 0 20px rgba(255, 215, 0, 0.4) !important;
        animation: illuminate-glow 1s infinite;
        position: relative;
        transform: scale(1.02);
    }
    
    .answer-bubble.illuminated::after {
        content: '💡';
        position: absolute;
        top: -12px;
        right: -12px;
        font-size: 1.6rem;
    }
    
    @keyframes illuminate-glow {
        0%, 100% { box-shadow: 0 0 30px rgba(255, 215, 0, 0.9), 0 0 60px rgba(255, 165, 0, 0.5), inset 0 0 20px rgba(255, 215, 0, 0.4); }
        50% { box-shadow: 0 0 50px rgba(255, 215, 0, 1), 0 0 80px rgba(255, 165, 0, 0.7), inset 0 0 30px rgba(255, 215, 0, 0.6); }
    }
    
    /* Bouton skill Mathématicien cliquable */
    .illuminate-skill-btn {
        position: fixed;
        bottom: 100px;
        right: 20px;
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: linear-gradient(145deg, #FFD700, #FFA500);
        border: 3px solid #fff;
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.8), 0 4px 15px rgba(0,0,0,0.3);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        z-index: 1000;
        animation: skill-pulse 1.2s ease-in-out infinite;
        transition: transform 0.2s;
    }
    
    .illuminate-skill-btn:hover {
        transform: scale(1.1);
    }
    
    .illuminate-skill-btn:active {
        transform: scale(0.95);
    }
    
    .illuminate-skill-btn.used {
        opacity: 0.4;
        pointer-events: none;
        animation: none;
    }
    
    .illuminate-skill-btn.not-activable {
        opacity: 0.5;
        background: linear-gradient(145deg, #666, #555);
        box-shadow: 0 0 10px rgba(100, 100, 100, 0.5), 0 4px 15px rgba(0,0,0,0.3);
        animation: none;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    @keyframes skill-pulse {
        0%, 100% { 
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.8), 0 4px 15px rgba(0,0,0,0.3);
            transform: scale(1);
        }
        50% { 
            box-shadow: 0 0 40px rgba(255, 215, 0, 1), 0 0 60px rgba(255, 165, 0, 0.6), 0 4px 15px rgba(0,0,0,0.3);
            transform: scale(1.05);
        }
    }
    
    .skill-label {
        position: fixed;
        bottom: 70px;
        right: 10px;
        background: rgba(0,0,0,0.7);
        color: #FFD700;
        padding: 5px 12px;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 600;
        z-index: 999;
        text-align: center;
    }
    
    /* Scientifique Acidify Skill Button */
    .acidify-skill-btn {
        position: fixed;
        bottom: 100px;
        left: 20px;
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: linear-gradient(145deg, #00FF00, #32CD32);
        border: 3px solid #fff;
        box-shadow: 0 0 20px rgba(0, 255, 0, 0.8), 0 4px 15px rgba(0,0,0,0.3);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        z-index: 1000;
        animation: acidify-pulse 1.2s ease-in-out infinite;
        transition: transform 0.2s;
    }
    
    .acidify-skill-btn:hover {
        transform: scale(1.1);
    }
    
    .acidify-skill-btn:active {
        transform: scale(0.95);
    }
    
    .acidify-skill-btn.used {
        opacity: 0.4;
        pointer-events: none;
        animation: none;
    }
    
    @keyframes acidify-pulse {
        0%, 100% { 
            box-shadow: 0 0 20px rgba(0, 255, 0, 0.8), 0 4px 15px rgba(0,0,0,0.3);
            transform: scale(1);
        }
        50% { 
            box-shadow: 0 0 40px rgba(0, 255, 0, 1), 0 0 60px rgba(50, 205, 50, 0.6), 0 4px 15px rgba(0,0,0,0.3);
            transform: scale(1.05);
        }
    }
    
    .skill-label.acidify-label {
        bottom: 70px;
        left: 10px;
        right: auto;
        color: #00FF00;
    }
    
    /* Acidified answer style */
    .answer-bubble.acidified {
        opacity: 0.3;
        pointer-events: none;
        background: rgba(0, 0, 0, 0.5) !important;
        border-color: #00FF00 !important;
        text-decoration: line-through;
        position: relative;
    }
    
    .answer-bubble.acidified::after {
        content: '🧪';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 2rem;
        opacity: 0.7;
    }
    
    /* Explorateur See Opponent Skill Button - Bas droite comme les autres */
    .see-opponent-skill-btn {
        position: fixed;
        bottom: 100px;
        right: 20px;
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: linear-gradient(145deg, #9B59B6, #8E44AD);
        border: 3px solid #fff;
        box-shadow: 0 0 20px rgba(155, 89, 182, 0.8), 0 4px 15px rgba(0,0,0,0.3);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        z-index: 1000;
        animation: see-opponent-pulse 1.2s ease-in-out infinite;
        transition: transform 0.2s;
    }
    
    .see-opponent-skill-btn:hover {
        transform: scale(1.1);
    }
    
    .see-opponent-skill-btn:active {
        transform: scale(0.95);
    }
    
    .see-opponent-skill-btn.used {
        opacity: 0.4;
        pointer-events: none;
        animation: none;
    }
    
    .see-opponent-skill-btn.not-usable {
        opacity: 0.5;
        background: linear-gradient(145deg, #666, #555);
        box-shadow: 0 0 10px rgba(100, 100, 100, 0.5), 0 4px 15px rgba(0,0,0,0.3);
        animation: none;
        cursor: not-allowed;
        pointer-events: none;
    }
    
    .see-opponent-skill-btn.waiting {
        opacity: 0.6;
        background: linear-gradient(145deg, #888, #666);
        box-shadow: 0 0 15px rgba(155, 89, 182, 0.4), 0 4px 15px rgba(0,0,0,0.3);
        animation: waiting-pulse 1s infinite;
        cursor: wait;
        pointer-events: none;
    }
    
    @keyframes waiting-pulse {
        0%, 100% { opacity: 0.6; }
        50% { opacity: 0.8; }
    }
    
    @keyframes see-opponent-pulse {
        0%, 100% { 
            box-shadow: 0 0 20px rgba(155, 89, 182, 0.8), 0 4px 15px rgba(0,0,0,0.3);
            transform: scale(1);
        }
        50% { 
            box-shadow: 0 0 40px rgba(155, 89, 182, 1), 0 0 60px rgba(142, 68, 173, 0.6), 0 4px 15px rgba(0,0,0,0.3);
            transform: scale(1.05);
        }
    }
    
    .skill-label.see-opponent-label {
        bottom: 70px;
        right: 10px;
        color: #9B59B6;
    }
    
    /* IA Junior: Suggestion IA skill button */
    .ai-suggestion-skill-btn {
        position: fixed;
        bottom: 140px;
        right: 15px;
        width: 55px;
        height: 55px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3498DB, #2980B9);
        border: 3px solid #5DADE2;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        cursor: pointer;
        box-shadow: 0 0 20px rgba(52, 152, 219, 0.8), 0 4px 15px rgba(0,0,0,0.3);
        animation: ai-suggestion-pulse 1.5s ease-in-out infinite;
        z-index: 1000;
        transition: transform 0.2s;
    }
    
    .ai-suggestion-skill-btn:hover {
        transform: scale(1.1);
    }
    
    .ai-suggestion-skill-btn:active {
        transform: scale(0.95);
    }
    
    .ai-suggestion-skill-btn.used {
        opacity: 0.4;
        pointer-events: none;
        animation: none;
    }
    
    @keyframes ai-suggestion-pulse {
        0%, 100% { 
            box-shadow: 0 0 20px rgba(52, 152, 219, 0.8), 0 4px 15px rgba(0,0,0,0.3);
            transform: scale(1);
        }
        50% { 
            box-shadow: 0 0 40px rgba(52, 152, 219, 1), 0 0 60px rgba(41, 128, 185, 0.6), 0 4px 15px rgba(0,0,0,0.3);
            transform: scale(1.05);
        }
    }
    
    .skill-label.ai-suggestion-label {
        bottom: 200px;
        right: 10px;
        color: #3498DB;
    }
    
    /* IA Junior: Eliminate two skill button */
    .eliminate-two-skill-btn {
        position: fixed;
        bottom: 210px;
        right: 15px;
        width: 55px;
        height: 55px;
        border-radius: 50%;
        background: linear-gradient(135deg, #E74C3C, #C0392B);
        border: 3px solid #EC7063;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        cursor: pointer;
        box-shadow: 0 0 20px rgba(231, 76, 60, 0.8), 0 4px 15px rgba(0,0,0,0.3);
        animation: eliminate-two-pulse 1.5s ease-in-out infinite;
        z-index: 1000;
        transition: transform 0.2s;
    }
    
    .eliminate-two-skill-btn:hover {
        transform: scale(1.1);
    }
    
    .eliminate-two-skill-btn:active {
        transform: scale(0.95);
    }
    
    .eliminate-two-skill-btn.used {
        opacity: 0.4;
        pointer-events: none;
        animation: none;
    }
    
    @keyframes eliminate-two-pulse {
        0%, 100% { 
            box-shadow: 0 0 20px rgba(231, 76, 60, 0.8), 0 4px 15px rgba(0,0,0,0.3);
            transform: scale(1);
        }
        50% { 
            box-shadow: 0 0 40px rgba(231, 76, 60, 1), 0 0 60px rgba(192, 57, 43, 0.6), 0 4px 15px rgba(0,0,0,0.3);
            transform: scale(1.05);
        }
    }
    
    .skill-label.eliminate-two-label {
        bottom: 270px;
        right: 10px;
        color: #E74C3C;
    }
    
    /* IA Junior: Replay skill overlay (appears after wrong answer) */
    .replay-skill-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
    
    .replay-skill-overlay.active {
        display: flex;
    }
    
    .replay-skill-container {
        text-align: center;
        padding: 30px;
        background: linear-gradient(135deg, rgba(230, 126, 34, 0.95), rgba(211, 84, 0, 0.95));
        border-radius: 20px;
        border: 3px solid #F39C12;
        box-shadow: 0 0 40px rgba(230, 126, 34, 0.8);
        animation: replay-appear 0.3s ease-out;
    }
    
    @keyframes replay-appear {
        from {
            opacity: 0;
            transform: scale(0.8);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .replay-skill-icon {
        font-size: 4rem;
        margin-bottom: 15px;
    }
    
    .replay-skill-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
    }
    
    .replay-skill-desc {
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 15px;
    }
    
    .replay-skill-timer {
        font-size: 1.2rem;
        color: #F1C40F;
        font-weight: 600;
        margin-bottom: 20px;
    }
    
    .replay-skill-btn {
        padding: 15px 40px;
        font-size: 1.2rem;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, #27AE60, #1E8449);
        border: none;
        border-radius: 30px;
        cursor: pointer;
        box-shadow: 0 5px 20px rgba(39, 174, 96, 0.5);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .replay-skill-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 30px rgba(39, 174, 96, 0.7);
    }
    
    .replay-skill-skip {
        margin-top: 15px;
        padding: 10px 30px;
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.7);
        background: transparent;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .replay-skill-skip:hover {
        color: #fff;
        border-color: rgba(255, 255, 255, 0.6);
    }
    
    /* AI suggested answer highlight */
    .answer-bubble.ai-suggested {
        border: 4px solid #3498DB !important;
        box-shadow: 0 0 25px rgba(52, 152, 219, 0.9), inset 0 0 20px rgba(52, 152, 219, 0.3);
        position: relative;
    }
    
    .answer-bubble.ai-suggested::before {
        content: '🤖';
        position: absolute;
        top: -15px;
        right: -15px;
        font-size: 1.5rem;
        background: #3498DB;
        border-radius: 50%;
        padding: 5px;
        z-index: 10;
    }
    
    /* Eliminated answer styling */
    .answer-bubble.eliminated {
        opacity: 0.3;
        pointer-events: none;
        text-decoration: line-through;
        filter: grayscale(100%);
        position: relative;
    }
    
    .answer-bubble.eliminated::after {
        content: '❌';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 2rem;
        z-index: 10;
    }
    
    /* Opponent choice highlight */
    .answer-bubble.opponent-choice {
        border: 4px solid #9B59B6 !important;
        box-shadow: 0 0 20px rgba(155, 89, 182, 0.8), inset 0 0 15px rgba(155, 89, 182, 0.3);
        position: relative;
    }
    
    .answer-bubble.opponent-choice::before {
        content: '👁️';
        position: absolute;
        top: -15px;
        right: -15px;
        font-size: 1.5rem;
        background: #9B59B6;
        border-radius: 50%;
        padding: 5px;
        z-index: 10;
    }
    
    /* Buzz info */
    .buzz-info {
        text-align: center;
        padding: 8px;
        background: rgba(78, 205, 196, 0.15);
        border-radius: 12px;
        margin-bottom: 5px;
        border: 2px solid rgba(78, 205, 196, 0.3);
        flex-shrink: 0;
    }
    
    .buzz-info-text {
        font-size: 0.8rem;
        color: #4ECDC4;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .answer-header {
            flex-direction: column;
            gap: 10px;
        }
        
        .answer-info {
            width: 100%;
        }
        
        .answer-value {
            font-size: 1.3rem;
        }
        
        .answer-text {
            font-size: 1rem;
        }
        
        .answer-number {
            width: 40px;
            height: 40px;
            font-size: 1.1rem;
        }
    }
    
    @media (max-width: 480px) {
        .answer-bubble {
            padding: 15px 18px;
        }
    }
    
    /* === RESPONSIVE POUR ORIENTATION === */
    
    /* Mobile Portrait */
    @media (max-width: 480px) and (orientation: portrait) {
        .answer-container {
            padding: 12px;
        }
        
        .answer-bubble {
            padding: 12px 16px;
            margin-bottom: 8px;
        }
        
        .answer-text {
            font-size: 0.95rem;
        }
    }
    
    /* Mobile Paysage */
    @media (max-height: 500px) and (orientation: landscape) {
        .answer-container {
            padding: 8px;
            max-height: 100vh;
            overflow-y: auto;
        }
        
        .answer-header {
            margin-bottom: 8px;
        }
        
        .answer-timer {
            margin-bottom: 8px;
        }
        
        .answer-bubble {
            padding: 10px 14px;
            margin-bottom: 6px;
        }
        
        .answer-text {
            font-size: 0.9rem;
        }
        
        .answer-number {
            width: 35px;
            height: 35px;
            font-size: 1rem;
        }
    }
    
    /* Tablettes Portrait */
    @media (min-width: 481px) and (max-width: 900px) and (orientation: portrait) {
        .answer-bubble {
            padding: 16px 20px;
        }
    }
    
    /* Tablettes Paysage */
    @media (min-width: 481px) and (max-width: 1024px) and (orientation: landscape) {
        .answer-container {
            padding: 16px;
        }
    }
    
    /* Animation Bouclier Défenseur - Fullscreen DERRIÈRE tous les éléments */
    .shield-defense-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 5;
        pointer-events: none;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .shield-defense-icon {
        width: 120px;
        height: auto;
        opacity: 0;
        transform: scale(0.1) translateY(200vh);
        filter: drop-shadow(0 0 50px rgba(70, 130, 180, 1));
    }

    .shield-defense-icon.animate {
        animation: shieldDefenseRush 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
    }

    @keyframes shieldDefenseRush {
        0% {
            opacity: 0;
            transform: scale(0.1) translateY(200vh);
            filter: drop-shadow(0 0 20px rgba(70, 130, 180, 0.5));
        }
        20% {
            opacity: 1;
            transform: scale(2) translateY(0);
            filter: drop-shadow(0 0 40px rgba(70, 130, 180, 0.8));
        }
        40% {
            transform: scale(8) translateY(0);
            filter: drop-shadow(0 0 80px rgba(70, 130, 180, 1));
        }
        60% {
            transform: scale(15) translateY(0) rotate(-5deg);
            filter: drop-shadow(0 0 100px rgba(70, 130, 180, 1));
        }
        80% {
            opacity: 1;
            transform: scale(25) translateY(0) rotate(3deg);
            filter: drop-shadow(0 0 150px rgba(255, 255, 255, 0.8));
        }
        100% {
            opacity: 0;
            transform: scale(40) translateY(0);
            filter: drop-shadow(0 0 200px rgba(255, 255, 255, 0));
        }
    }

    /* Flash lumineux derrière le bouclier */
    .shield-defense-flash {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 4;
        pointer-events: none;
        background: radial-gradient(circle at center, rgba(78, 205, 196, 0.3) 0%, transparent 70%);
        opacity: 0;
    }

    .shield-defense-flash.animate {
        animation: shieldFlash 1.2s ease-out forwards;
    }

    @keyframes shieldFlash {
        0% { opacity: 0; }
        30% { opacity: 0.6; }
        60% { opacity: 0.8; }
        100% { opacity: 0; }
    }
</style>

<!-- Son épée/bouclier -->
<audio id="swordShieldSound" preload="auto">
    <source src="{{ asset('sounds/sword_shield.wav') }}" type="audio/wav">
</audio>

<!-- Animation Bouclier Défenseur - Fullscreen Defense (quand on est attaqué) -->
<div id="shieldDefenseFlash" class="shield-defense-flash"></div>
<div id="shieldDefenseOverlay" class="shield-defense-overlay">
    <img id="shieldDefenseIcon" class="shield-defense-icon" src="{{ asset('images/shield_medieval.png') }}" alt="Shield">
</div>

<div class="answer-container">
    <!-- Header -->
    <div class="answer-header">
        <div class="answer-info" style="text-align: center; width: 100%; flex-direction: row; align-items: center; justify-content: space-between; gap: 15px;">
            @php
                $potentialPoints = $params['potential_points'] ?? 0;
                $pointColor = $potentialPoints == 0 ? '#FFD700' : '#4ECDC4'; // Jaune pour 0, Vert pour 1 ou 2
            @endphp
            <!-- Question # -->
            <div style="font-size: 1.7rem; font-weight: 700; flex: 1; text-align: left;">
                Question #{{ $params['current_question'] }}
            </div>
            <!-- Points en gros au centre -->
            <div style="font-size: 2.5rem; font-weight: 900; color: {{ $pointColor }}; text-shadow: 0 0 20px {{ $pointColor }}80;">
                +{{ $potentialPoints }}
            </div>
            <!-- Score à droite -->
            <div style="font-size: 1.7rem; font-weight: 700; flex: 1; text-align: right;">
                Score {{ $params['score'] }}/{{ $params['opponent_score'] ?? ($params['current_question'] - 1) }}</span>
            </div>
        </div>
    </div>
    
    <!-- Timer -->
    <div class="answer-timer">
        <div class="timer-label">
            <span>⏱️ Temps pour répondre</span>
            <span id="timerText">10s</span>
        </div>
        <div class="timer-bar-container">
            <div class="timer-bar" id="timerBar"></div>
        </div>
    </div>
    
    <!-- Choix de réponses -->
    <form id="answerForm" method="POST" action="{{ $answerRoute }}">
        @csrf
        <input type="hidden" name="answer_index" id="answerIndex">
        <input type="hidden" name="feather_skill_used" id="featherSkillUsed" value="0">
        <input type="hidden" name="illuminate_skill_used" id="illuminateSkillUsedInput" value="0">
        <input type="hidden" name="replay_skill_used" id="replaySkillUsedInput" value="0">
        
        <div class="answers-grid{{ $shuffleAnswersActive ? ' shuffle-active' : '' }}" id="answersGrid">
            @if($shuffleAnswersActive)
            <div class="shuffle-indicator">
                🔀 {{ __('Réponses en mouvement') }}
            </div>
            @endif
            @php
                $question = $params['question'];
                $isTrueFalse = $question['type'] === 'true_false';
            @endphp
            
            @foreach($question['answers'] as $index => $answer)
                @if($isTrueFalse && $answer === null)
                    @continue
                @endif
                
                <div class="answer-bubble" onclick="selectAnswer({{ $index }})" data-index="{{ $index }}" data-original-index="{{ $index }}">
                    <div class="answer-number">{{ $index + 1 }}</div>
                    <div class="answer-text">{{ $answer }}</div>
                    <div class="answer-icon {{ $featherActive ? 'feather-active' : '' }}">@if($featherActive)🪶@else👉@endif</div>
                </div>
            @endforeach
        </div>
    </form>
    
    <!-- Buzz info -->
    @if($featherActive)
        <div class="buzz-info" style="background: rgba(78, 205, 196, 0.15); border-color: rgba(78, 205, 196, 0.3);">
            <div class="buzz-info-text" style="color: #4ECDC4;">
                🪶 {{ __('Savoir sans temps') }} - {{ __('Vous pouvez répondre') }} (+1 {{ __('point max') }})
            </div>
        </div>
    @elseif(isset($params['player_buzzed']) && !$params['player_buzzed'])
        <div class="buzz-info" style="background: rgba(255, 107, 107, 0.15); border-color: rgba(255, 107, 107, 0.3);">
            <div class="buzz-info-text" style="color: #FF6B6B;">
                ⚠️ {{ __('Pas buzzé - Vous pouvez quand même répondre (0 point)') }}
            </div>
        </div>
    @elseif(isset($params['buzz_time']))
        <div class="buzz-info">
            <div class="buzz-info-text">
                Vous avez buzzé en {{ $params['buzz_time'] }}s 💚
            </div>
        </div>
    @endif
</div>

@if($illuminateSkillAvailable)
<div class="illuminate-skill-btn{{ !$illuminateCanActivate ? ' not-activable' : '' }}" id="illuminateSkillBtn" onclick="activateIlluminateSkill()">
    🔢
</div>
<div class="skill-label">{{ __('Illuminer') }}</div>
@endif

@if($acidifySkillAvailable)
<div class="acidify-skill-btn" id="acidifySkillBtn" onclick="activateAcidifySkill()">
    🧪
</div>
<div class="skill-label acidify-label">{{ __('Acidifier') }}</div>
@endif

@if($seeOpponentSkillAvailable)
@php
    // Le skill est utilisable seulement si l'adversaire a fait un choix
    $skillUsable = $opponentAnswerChoice !== null;
    // Délai de lecture de l'IA : 0.5s par réponse selon la position du choix (1-4)
    $readingDelayMs = $skillUsable ? (($opponentAnswerChoice + 1) * 500) : 0;
@endphp
<div class="see-opponent-skill-btn{{ !$skillUsable ? ' not-usable' : ' waiting' }}" id="seeOpponentSkillBtn" onclick="activateSeeOpponentSkill()" data-reading-delay="{{ $readingDelayMs }}">
    👁️
</div>
<div class="skill-label see-opponent-label">{{ __('Voir choix') }}</div>
@endif

@if($aiSuggestionSkillAvailable)
<div class="ai-suggestion-skill-btn" id="aiSuggestionSkillBtn" onclick="activateAiSuggestionSkill()">
    🤖
</div>
<div class="skill-label ai-suggestion-label">{{ __('Suggestion IA') }}</div>
@endif

@if($eliminateTwoSkillAvailable)
<div class="eliminate-two-skill-btn" id="eliminateTwoSkillBtn" onclick="activateEliminateTwoSkill()">
    ❌
</div>
<div class="skill-label eliminate-two-label">{{ __('Éliminer') }}</div>
@endif

<audio id="tickSound" preload="auto" loop>
    <source src="{{ asset('sounds/tic_tac.mp3') }}" type="audio/mpeg">
</audio>

<audio id="timeoutSound" preload="auto">
    <source src="{{ asset('sounds/timeout.mp3') }}" type="audio/mpeg">
</audio>

<audio id="correctSound" preload="auto">
    <source src="{{ asset('sounds/correct.mp3') }}" type="audio/mpeg">
</audio>

<audio id="incorrectSound" preload="auto">
    <source src="{{ asset('sounds/incorrect.mp3') }}" type="audio/mpeg">
</audio>

<script>
// Animation Bouclier Défenseur - Non-Blocking (quand on est attaqué et le bouclier bloque)
window.triggerShieldDefense = function() {
    const flash = document.getElementById('shieldDefenseFlash');
    const overlay = document.getElementById('shieldDefenseOverlay');
    const icon = document.getElementById('shieldDefenseIcon');
    const sound = document.getElementById('swordShieldSound');
    
    if (!flash || !overlay || !icon) {
        console.log('Shield defense elements not found');
        return;
    }
    
    // Jouer le son épée/bouclier - VOLUME MAXIMUM
    if (sound) {
        sound.currentTime = 0;
        sound.volume = 1.0;
        sound.play().catch(e => console.log('Shield sound failed:', e));
    }
    
    // Déclencher les animations
    flash.classList.add('animate');
    icon.classList.add('animate');
    
    // Nettoyer après l'animation (1.2s)
    setTimeout(() => {
        flash.classList.remove('animate');
        icon.classList.remove('animate');
    }, 1300);
};

let timeLeft = 10; // Countdown de 10 secondes
let totalTime = 10;
let answered = false;
const correctIndex = {{ $params['correct_index'] ?? -1 }}; // Index de la bonne réponse
let correctSoundDuration = 2000; // Délai par défaut
let incorrectSoundDuration = 500; // Délai par défaut
let illuminateSkillUsed = false;

// Skill Challenger: Shuffle Answers - les réponses bougent toutes les 1.5 sec
const shuffleActive = {{ $shuffleAnswersActive ? 'true' : 'false' }};
let shuffleInterval = null;

function shuffleAnswers() {
    if (answered) return;
    
    const grid = document.getElementById('answersGrid');
    const bubbles = Array.from(grid.querySelectorAll('.answer-bubble'));
    
    if (bubbles.length <= 1) return;
    
    // Ajouter effet de transition
    bubbles.forEach(b => b.classList.add('shuffling'));
    
    // Fisher-Yates shuffle pour mélanger l'ordre
    for (let i = bubbles.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        // Échanger les positions dans le DOM
        if (i !== j) {
            const temp = bubbles[i];
            grid.insertBefore(bubbles[i], bubbles[j]);
            grid.insertBefore(bubbles[j], temp);
            // Mettre à jour le tableau
            [bubbles[i], bubbles[j]] = [bubbles[j], bubbles[i]];
        }
    }
    
    // Mettre à jour les numéros visuels (1, 2, 3, 4)
    bubbles.forEach((bubble, visualIndex) => {
        const numberDiv = bubble.querySelector('.answer-number');
        if (numberDiv) {
            numberDiv.textContent = visualIndex + 1;
        }
    });
    
    // Retirer l'effet après l'animation
    setTimeout(() => {
        bubbles.forEach(b => b.classList.remove('shuffling'));
    }, 400);
}

// Démarrer le shuffle si actif
if (shuffleActive) {
    // Premier shuffle après 1.5 sec
    shuffleInterval = setInterval(shuffleAnswers, 1500);
}

// Fonction pour activer le skill Mathématicien "Illumine si chiffre"
function activateIlluminateSkill() {
    if (answered || illuminateSkillUsed) return;
    
    illuminateSkillUsed = true;
    answered = true;  // Empêche d'autres actions
    
    // Marquer le bouton comme utilisé
    const skillBtn = document.getElementById('illuminateSkillBtn');
    if (skillBtn) {
        skillBtn.classList.add('used');
    }
    
    // Arrêter le timer et le son
    clearInterval(timerInterval);
    const tickSound = document.getElementById('tickSound');
    tickSound.pause();
    tickSound.currentTime = 0;
    
    // Illuminer la bonne réponse
    const bubbles = document.querySelectorAll('.answer-bubble');
    bubbles.forEach((bubble, index) => {
        if (index === correctIndex) {
            bubble.classList.add('illuminated');
            bubble.classList.add('selected');
        } else {
            bubble.classList.add('disabled');
        }
    });
    
    // Jouer le son de bonne réponse
    const correctSound = document.getElementById('correctSound');
    correctSound.currentTime = 0;
    correctSound.play().catch(e => console.log('Audio play failed:', e));
    
    // Sélectionner la bonne réponse dans le formulaire
    document.getElementById('answerIndex').value = correctIndex;
    document.getElementById('illuminateSkillUsedInput').value = '1';
    
    // Soumettre après 1.5 secondes
    setTimeout(() => {
        document.getElementById('answerForm').submit();
    }, 1500);
}

// Fonction pour activer le skill Scientifique "Acidifie 2 erreurs"
let acidifySkillUsed = false;
function activateAcidifySkill() {
    if (answered || acidifySkillUsed) return;
    
    acidifySkillUsed = true;
    
    // Marquer le bouton comme utilisé
    const skillBtn = document.getElementById('acidifySkillBtn');
    if (skillBtn) {
        skillBtn.classList.add('used');
    }
    
    // Trouver les indices des mauvaises réponses
    const bubbles = document.querySelectorAll('.answer-bubble');
    const wrongIndices = [];
    bubbles.forEach((bubble, index) => {
        if (index !== correctIndex) {
            wrongIndices.push(index);
        }
    });
    
    // Mélanger et prendre 2 mauvaises réponses
    for (let i = wrongIndices.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [wrongIndices[i], wrongIndices[j]] = [wrongIndices[j], wrongIndices[i]];
    }
    const toAcidify = wrongIndices.slice(0, 2);
    
    // Acidifier les 2 mauvaises réponses
    toAcidify.forEach(index => {
        bubbles[index].classList.add('acidified');
    });
    
    // Marquer le skill comme utilisé dans le formulaire
    let acidifyInput = document.getElementById('acidifySkillUsedInput');
    if (!acidifyInput) {
        acidifyInput = document.createElement('input');
        acidifyInput.type = 'hidden';
        acidifyInput.id = 'acidifySkillUsedInput';
        acidifyInput.name = 'acidify_skill_used';
        document.getElementById('answerForm').appendChild(acidifyInput);
    }
    acidifyInput.value = '1';
}

// Fonction pour activer le skill Explorateur "Voir choix adverse"
let seeOpponentSkillUsed = false;
let seeOpponentSkillReady = false; // Le skill est prêt après le délai de lecture
const opponentAnswerChoice = {{ $opponentAnswerChoice ?? 'null' }};

// Activer le skill après le délai de lecture de l'IA
document.addEventListener('DOMContentLoaded', function() {
    const seeOpponentBtn = document.getElementById('seeOpponentSkillBtn');
    if (seeOpponentBtn && opponentAnswerChoice !== null) {
        const readingDelay = parseInt(seeOpponentBtn.dataset.readingDelay) || 0;
        setTimeout(function() {
            seeOpponentBtn.classList.remove('waiting');
            seeOpponentSkillReady = true;
        }, readingDelay);
    }
});

function activateSeeOpponentSkill() {
    if (answered || seeOpponentSkillUsed) return;
    if (opponentAnswerChoice === null) return; // L'adversaire n'a pas choisi
    if (!seeOpponentSkillReady) return; // Le skill n'est pas encore prêt (délai de lecture)
    
    seeOpponentSkillUsed = true;
    
    // Marquer le bouton comme utilisé
    const skillBtn = document.getElementById('seeOpponentSkillBtn');
    if (skillBtn) {
        skillBtn.classList.add('used');
    }
    
    // Illuminer le choix de l'adversaire
    const bubbles = document.querySelectorAll('.answer-bubble');
    if (bubbles[opponentAnswerChoice]) {
        bubbles[opponentAnswerChoice].classList.add('opponent-choice');
    }
    
    // Marquer le skill comme utilisé dans le formulaire
    let seeOpponentInput = document.getElementById('seeOpponentSkillUsedInput');
    if (!seeOpponentInput) {
        seeOpponentInput = document.createElement('input');
        seeOpponentInput.type = 'hidden';
        seeOpponentInput.id = 'seeOpponentSkillUsedInput';
        seeOpponentInput.name = 'see_opponent_skill_used';
        document.getElementById('answerForm').appendChild(seeOpponentInput);
    }
    seeOpponentInput.value = '1';
}

// IA Junior: Skill Suggestion IA (90% de chance de suggérer la bonne réponse)
let aiSuggestionSkillUsed = false;

function activateAiSuggestionSkill() {
    if (answered || aiSuggestionSkillUsed) return;
    
    aiSuggestionSkillUsed = true;
    
    // Marquer le bouton comme utilisé
    const skillBtn = document.getElementById('aiSuggestionSkillBtn');
    if (skillBtn) {
        skillBtn.classList.add('used');
    }
    
    const bubbles = document.querySelectorAll('.answer-bubble');
    const isCorrect = Math.random() < 0.9; // 90% de chance
    
    let suggestionIndex;
    if (isCorrect) {
        suggestionIndex = correctIndex;
    } else {
        // Trouver une mauvaise réponse aléatoire
        const wrongIndices = [];
        for (let i = 0; i < bubbles.length; i++) {
            if (i !== correctIndex) wrongIndices.push(i);
        }
        suggestionIndex = wrongIndices[Math.floor(Math.random() * wrongIndices.length)];
    }
    
    // Illuminer la réponse suggérée
    if (bubbles[suggestionIndex]) {
        bubbles[suggestionIndex].classList.add('ai-suggested');
    }
    
    // Marquer le skill comme utilisé dans le formulaire
    let aiSuggestionInput = document.getElementById('aiSuggestionSkillUsedInput');
    if (!aiSuggestionInput) {
        aiSuggestionInput = document.createElement('input');
        aiSuggestionInput.type = 'hidden';
        aiSuggestionInput.id = 'aiSuggestionSkillUsedInput';
        aiSuggestionInput.name = 'ai_suggestion_skill_used';
        document.getElementById('answerForm').appendChild(aiSuggestionInput);
    }
    aiSuggestionInput.value = '1';
}

// IA Junior: Skill Élimination (élimine 2 mauvaises réponses)
let eliminateTwoSkillUsed = false;

function activateEliminateTwoSkill() {
    if (answered || eliminateTwoSkillUsed) return;
    
    eliminateTwoSkillUsed = true;
    
    // Marquer le bouton comme utilisé
    const skillBtn = document.getElementById('eliminateTwoSkillBtn');
    if (skillBtn) {
        skillBtn.classList.add('used');
    }
    
    const bubbles = document.querySelectorAll('.answer-bubble');
    
    // Trouver toutes les mauvaises réponses (non déjà éliminées)
    let wrongIndices = [];
    for (let i = 0; i < bubbles.length; i++) {
        if (i !== correctIndex && !bubbles[i].classList.contains('eliminated')) {
            wrongIndices.push(i);
        }
    }
    
    // Mélanger et prendre 2 mauvaises réponses
    for (let i = wrongIndices.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [wrongIndices[i], wrongIndices[j]] = [wrongIndices[j], wrongIndices[i]];
    }
    const toEliminate = wrongIndices.slice(0, 2);
    
    // Éliminer les 2 mauvaises réponses
    toEliminate.forEach(index => {
        bubbles[index].classList.add('eliminated');
    });
    
    // Marquer le skill comme utilisé dans le formulaire
    let eliminateTwoInput = document.getElementById('eliminateTwoSkillUsedInput');
    if (!eliminateTwoInput) {
        eliminateTwoInput = document.createElement('input');
        eliminateTwoInput.type = 'hidden';
        eliminateTwoInput.id = 'eliminateTwoSkillUsedInput';
        eliminateTwoInput.name = 'eliminate_two_skill_used';
        document.getElementById('answerForm').appendChild(eliminateTwoInput);
    }
    eliminateTwoInput.value = '1';
}

// Animation de la barre de temps
const timerBar = document.getElementById('timerBar');
timerBar.style.width = '100%';

// Démarrer le son tic-tac en boucle dès le début
const tickSound = document.getElementById('tickSound');
tickSound.currentTime = 0;
tickSound.play().catch(e => console.log('Audio play failed:', e));

// Détecter la durée des sons correct/incorrect : 100ms APRÈS la fin du son
const correctSound = document.getElementById('correctSound');
correctSound.addEventListener('loadedmetadata', function() {
    correctSoundDuration = Math.floor(correctSound.duration * 1000) + 100;
});

const incorrectSound = document.getElementById('incorrectSound');
incorrectSound.addEventListener('loadedmetadata', function() {
    incorrectSoundDuration = Math.floor(incorrectSound.duration * 1000) + 100;
});

const timerInterval = setInterval(() => {
    timeLeft--;
    const percentage = (timeLeft / totalTime) * 100;
    timerBar.style.width = percentage + '%';
    document.getElementById('timerText').textContent = timeLeft + 's';
    
    // Changement de couleur à 3 secondes
    if (timeLeft <= 3) {
        timerBar.classList.add('warning');
    }
    
    // Temps écoulé
    if (timeLeft <= 0) {
        clearInterval(timerInterval);
        tickSound.pause(); // Arrêter le son tic-tac
        if (!answered) {
            handleTimeout();
        }
    }
}, 1000);

const featherActive = {{ $featherActive ? 'true' : 'false' }};
const replaySkillAvailable = {{ $replaySkillAvailable ? 'true' : 'false' }};
let replayModeActive = false;
let wrongAnswerIndex = -1;

function selectAnswer(index) {
    // Si en mode replay, c'est la deuxième tentative
    if (replayModeActive) {
        selectReplayAnswer(index);
        return;
    }
    
    if (answered) return;
    answered = true;
    
    clearInterval(timerInterval);
    
    // Arrêter le shuffle si actif
    if (shuffleInterval) {
        clearInterval(shuffleInterval);
        shuffleInterval = null;
    }
    
    // Arrêter le son tic-tac
    const tickSound = document.getElementById('tickSound');
    tickSound.pause();
    
    // Marquer la réponse choisie
    document.getElementById('answerIndex').value = index;
    
    // Si la Plume est active, marquer le skill comme utilisé (le joueur a cliqué sur une réponse)
    if (featherActive) {
        document.getElementById('featherSkillUsed').value = '1';
    }
    
    // Vérifier si la réponse est correcte
    const isCorrect = (index === correctIndex);
    
    // Si réponse incorrecte ET skill Rejouer disponible → activer le mode replay
    if (!isCorrect && replaySkillAvailable) {
        activateReplayMode(index);
        return;
    }
    
    // Désactiver tous les boutons
    document.querySelectorAll('.answer-bubble').forEach(bubble => {
        bubble.classList.add('disabled');
    });
    
    // Jouer le son approprié
    let soundDelay = 500;
    
    if (isCorrect) {
        const correctSound = document.getElementById('correctSound');
        correctSound.currentTime = 0;
        correctSound.play().catch(e => console.log('Audio play failed:', e));
        soundDelay = correctSoundDuration;
    } else {
        const incorrectSound = document.getElementById('incorrectSound');
        incorrectSound.currentTime = 0;
        incorrectSound.play().catch(e => console.log('Audio play failed:', e));
        soundDelay = incorrectSoundDuration;
    }
    
    // Soumettre le formulaire 100ms après la fin du son
    setTimeout(() => {
        document.getElementById('answerForm').submit();
    }, soundDelay);
}

// Activer le mode Replay après une erreur
function activateReplayMode(wrongIndex) {
    replayModeActive = true;
    wrongAnswerIndex = wrongIndex;
    answered = false; // Permettre une nouvelle réponse
    
    // Marquer le skill replay comme utilisé IMMÉDIATEMENT (même si le joueur abandonne)
    document.getElementById('replaySkillUsedInput').value = '1';
    
    // Jouer le son d'erreur
    const incorrectSound = document.getElementById('incorrectSound');
    incorrectSound.currentTime = 0;
    incorrectSound.play().catch(e => console.log('Audio play failed:', e));
    
    // Marquer la mauvaise réponse comme sélectionnée (grisée)
    const bubbles = document.querySelectorAll('.answer-bubble');
    bubbles.forEach((bubble, idx) => {
        const originalIndex = parseInt(bubble.getAttribute('data-original-index'));
        if (originalIndex === wrongIndex) {
            bubble.classList.add('wrong-selected');
            bubble.classList.add('disabled');
        } else {
            // Afficher ↩️ sur les 3 autres réponses
            bubble.classList.add('replay-available');
            const iconDiv = bubble.querySelector('.answer-icon');
            if (iconDiv) {
                iconDiv.textContent = '↩️';
                iconDiv.classList.add('replay-active');
            }
        }
    });
    
    // Masquer les boutons de skills (ils ne peuvent plus être utilisés)
    const skillBtns = document.querySelectorAll('.ai-suggestion-skill-btn, .eliminate-two-skill-btn, .acidify-skill-btn, .illuminate-skill-btn, .see-opponent-skill-btn');
    skillBtns.forEach(btn => btn.style.display = 'none');
    const skillLabels = document.querySelectorAll('.skill-label');
    skillLabels.forEach(label => label.style.display = 'none');
}

// Sélectionner une réponse en mode Replay
function selectReplayAnswer(index) {
    if (!replayModeActive) return;
    if (index === wrongAnswerIndex) return; // Ne peut pas re-cliquer sur la mauvaise réponse
    
    answered = true;
    replayModeActive = false;
    
    // Marquer le skill replay comme utilisé
    document.getElementById('replaySkillUsedInput').value = '1';
    document.getElementById('answerIndex').value = index;
    
    // Désactiver tous les boutons
    document.querySelectorAll('.answer-bubble').forEach(bubble => {
        bubble.classList.add('disabled');
        bubble.classList.remove('replay-available');
    });
    
    // Vérifier si la nouvelle réponse est correcte
    const isCorrect = (index === correctIndex);
    let soundDelay = 500;
    
    if (isCorrect) {
        const correctSound = document.getElementById('correctSound');
        correctSound.currentTime = 0;
        correctSound.play().catch(e => console.log('Audio play failed:', e));
        soundDelay = correctSoundDuration;
    } else {
        const incorrectSound = document.getElementById('incorrectSound');
        incorrectSound.currentTime = 0;
        incorrectSound.play().catch(e => console.log('Audio play failed:', e));
        soundDelay = incorrectSoundDuration;
    }
    
    // Soumettre le formulaire
    setTimeout(() => {
        document.getElementById('answerForm').submit();
    }, soundDelay);
}

function handleTimeout() {
    if (answered) return;
    answered = true;
    
    // Jouer son de timeout
    const timeoutSound = document.getElementById('timeoutSound');
    timeoutSound.play().catch(e => console.log('Audio play failed:', e));
    
    // Désactiver tous les boutons
    document.querySelectorAll('.answer-bubble').forEach(bubble => {
        bubble.classList.add('disabled');
    });
    
    // Marquer explicitement "Aucun choix" avec -1 (BUG #2 FIX)
    document.getElementById('answerIndex').value = -1;
    
    // Soumettre le formulaire sans réponse (timeout)
    setTimeout(() => {
        document.getElementById('answerForm').submit();
    }, 2000);
}
</script>
@endsection
