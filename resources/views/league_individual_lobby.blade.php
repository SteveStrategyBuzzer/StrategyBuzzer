@extends('layouts.app')

@section('content')
<div class="league-lobby-container">
    <a href="{{ route('menu') }}" class="back-button-fixed">← Retour</a>
    
    @if($activeMatch ?? false)
    @php
        $opponent = $activeMatch->player1_id == Auth::id() ? $activeMatch->player2 : $activeMatch->player1;
        $opponentName = $opponent ? $opponent->name : __('Adversaire');
    @endphp
    <div class="active-match-banner" style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); border-radius: 15px; padding: 20px; margin-bottom: 20px; text-align: center; box-shadow: 0 4px 15px rgba(255, 152, 0, 0.4); animation: pulse-glow-orange 2s infinite;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 15px; flex-wrap: wrap;">
            <span style="font-size: 2rem;">⚔️</span>
            <div style="text-align: left;">
                <div style="font-weight: bold; font-size: 1.1rem; color: #fff;">{{ __('Match en cours') }}</div>
                <div style="font-size: 0.9rem; color: rgba(255,255,255,0.8);">
                    {{ __('Contre') }} {{ $opponentName }}
                </div>
            </div>
            <button onclick="window.location.href='{{ route('league.individual.game', $activeMatch->id) }}'" style="background: #fff; color: #F57C00; border: none; border-radius: 25px; padding: 12px 30px; font-weight: bold; font-size: 1rem; cursor: pointer; transition: transform 0.2s; margin-left: 10px;">
                {{ __('REPRENDRE') }} →
            </button>
        </div>
    </div>
    <style>
        @keyframes pulse-glow-orange {
            0%, 100% { box-shadow: 0 4px 15px rgba(255, 152, 0, 0.4); }
            50% { box-shadow: 0 4px 25px rgba(255, 152, 0, 0.7); }
        }
    </style>
    @endif
    
    <div class="league-header">
        <h1>⚔️ LIGUE INDIVIDUEL</h1>
        <div class="current-division">
            <span class="division-emoji">{{ $divisionEmoji ?? '🥉' }}</span>
            <span class="division-name">{{ ucfirst($division->division ?? 'Bronze') }}</span>
            <span class="division-points">{{ $division->points ?? 0 }} pts</span>
        </div>
    </div>

    <!-- Division Selector -->
    <div class="division-selector-section">
        <h3>🎯 Sélectionner une division</h3>
        <p class="hint-text">Vous pouvez jouer jusqu'à 2 divisions au-dessus de la vôtre</p>
        <div class="division-selector" id="divisionSelector">
            @php
                $divisions = ['bronze', 'argent', 'or', 'platine', 'diamant', 'legende'];
                $currentDivIndex = array_search($division->division ?? 'bronze', $divisions);
                $maxDivIndex = min($currentDivIndex + 2, count($divisions) - 1);
                $divisionEmojis = ['🥉', '🥈', '🥇', '💎', '💠', '👑'];
                // Gains par victoire pour chaque division
                $divisionRewards = [10, 15, 25, 50, 100, 250];
                // Coûts d'accès = 2x les gains (seulement pour divisions supérieures)
                $divisionFees = [20, 30, 50, 100, 200, 500];
                $divisionLabels = [
                    'bronze' => __('Bronze'),
                    'argent' => __('Argent'),
                    'or' => __('Or'),
                    'platine' => __('Platine'),
                    'diamant' => __('Diamant'),
                    'legende' => __('Légende')
                ];
            @endphp
            @for($i = $currentDivIndex; $i <= $maxDivIndex; $i++)
                <button class="division-option {{ $i == $currentDivIndex ? 'selected current' : '' }}" 
                        data-division="{{ $divisions[$i] }}"
                        data-division-label="{{ $divisionLabels[$divisions[$i]] }}"
                        data-fee="{{ $i > $currentDivIndex ? $divisionFees[$i] : 0 }}"
                        data-reward="{{ $divisionRewards[$i] }}">
                    <span class="div-emoji">{{ $divisionEmojis[$i] }}</span>
                    <span class="div-name">{{ ucfirst($divisions[$i]) }}</span>
                    <span class="div-reward">🏆 {{ $divisionRewards[$i] }} 💰</span>
                    @if($i > $currentDivIndex)
                        <span class="div-fee">{{ __('Accès') }}: {{ $divisionFees[$i] }} 💰</span>
                    @endif
                </button>
            @endfor
        </div>
    </div>

    <!-- Matchmaking Area -->
    <div class="matchmaking-area">
        <!-- Available Opponents (Left Side) -->
        <div class="opponents-section">
            <h3>🎮 Adversaires disponibles</h3>
            <div class="opponents-list" id="opponentsList">
                <div class="loading-opponents">
                    <div class="spinner"></div>
                    <p>Recherche de joueurs...</p>
                </div>
            </div>
            <p class="empty-message" id="noOpponentsMsg" style="display: none;">
                Aucun adversaire disponible pour le moment. En attente...
            </p>
        </div>

        <!-- VS Divider -->
        <div class="vs-divider">
            <span>VS</span>
        </div>

        <!-- Current Player (Right Side) -->
        <div class="player-section">
            <h3>👤 Vous</h3>
            <div class="player-card current-player">
                <div class="player-avatar">
                    @if(Auth::user()->avatar_url)
                        <img src="{{ Auth::user()->avatar_url }}" alt="Avatar">
                    @else
                        <div class="default-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
                    @endif
                </div>
                <div class="player-info">
                    <h4>{{ Auth::user()->name }}</h4>
                    <div class="player-stats-row">
                        <span class="efficiency">🎯 {{ number_format($efficiency ?? 0, 1) }}%</span>
                        <span class="division-badge-small {{ $division->division ?? 'bronze' }}">
                            {{ $divisionEmojis[$currentDivIndex] ?? '🥉' }}
                        </span>
                    </div>
                    <div class="player-record">
                        {{ $stats->matches_won ?? 0 }}V - {{ $stats->matches_lost ?? 0 }}D
                    </div>
                </div>
                <div class="ready-status ready">
                    <span>✓ Prêt</span>
                </div>
            </div>
            
            <div class="my-stats-summary">
                <div class="stat-item">
                    <span class="stat-value">{{ $stats->matches_played ?? 0 }}</span>
                    <span class="stat-label">Matchs</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">{{ $division->level ?? 1 }}</span>
                    <span class="stat-label">Niveau</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">#{{ $rank ?? '-' }}</span>
                    <span class="stat-label">Rang</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <button id="joinQueueBtn" class="btn-primary btn-large">
            <span class="btn-icon">🎯</span>
            REJOINDRE LA FILE D'ATTENTE
        </button>
        <button id="leaveQueueBtn" class="btn-secondary btn-large" style="display: none;">
            <span class="btn-icon">✕</span>
            QUITTER LA FILE
        </button>
        <div id="queueStatus" class="queue-status" style="display: none;">
            <div class="spinner"></div>
            <p>En attente d'adversaires...</p>
        </div>
    </div>

    <!-- Rankings Link -->
    <div class="rankings-link">
        <a href="{{ route('league.individual.rankings') }}" class="btn-link">
            🏆 Voir les classements →
        </a>
    </div>
</div>

<!-- Player Stats Popup Modal -->
<div class="stats-modal" id="statsModal" style="display: none;">
    <div class="stats-modal-content">
        <button class="modal-close" onclick="closeStatsModal()">×</button>
        <div class="modal-header">
            <div class="modal-avatar" id="modalAvatar"></div>
            <div class="modal-player-info">
                <h3 id="modalPlayerName">Joueur</h3>
                <span class="modal-division" id="modalDivision"></span>
            </div>
        </div>
        <div class="modal-stats">
            <div class="stats-row">
                <div class="stat-box">
                    <span class="stat-value" id="modalMatches">0</span>
                    <span class="stat-label">Matchs</span>
                </div>
                <div class="stat-box">
                    <span class="stat-value" id="modalWins">0</span>
                    <span class="stat-label">Victoires</span>
                </div>
                <div class="stat-box">
                    <span class="stat-value" id="modalLosses">0</span>
                    <span class="stat-label">Défaites</span>
                </div>
            </div>
            <div class="stats-row">
                <div class="stat-box">
                    <span class="stat-value" id="modalTiebreakersWon">0</span>
                    <span class="stat-label">Tiebreakers gagnés</span>
                </div>
                <div class="stat-box">
                    <span class="stat-value" id="modalTiebreakersLost">0</span>
                    <span class="stat-label">Tiebreakers perdus</span>
                </div>
            </div>
            <div class="efficiency-section">
                <h4>🎯 Efficacité par catégorie (100 derniers matchs)</h4>
                <canvas id="efficiencyRadar" width="300" height="300"></canvas>
            </div>
            <div class="category-breakdown" id="categoryBreakdown">
                <!-- Categories will be populated here -->
            </div>
        </div>
        <button class="btn-primary btn-select-opponent" id="selectOpponentBtn" onclick="selectOpponent()">
            ⚔️ AFFRONTER CE JOUEUR
        </button>
    </div>
</div>

<!-- Confirm Match Modal -->
<div class="confirm-modal" id="confirmMatchModal" style="display: none;">
    <div class="confirm-modal-content">
        <h3>⚔️ Confirmer le match</h3>
        <p id="confirmMatchText">Voulez-vous affronter ce joueur ?</p>
        <div id="feeWarning" style="display: none;" class="fee-warning">
            <span class="fee-icon">💰</span>
            <span class="fee-text">Frais d'entrée: <strong id="feeAmount">0</strong> pièces</span>
        </div>
        <div class="confirm-buttons">
            <button class="btn-secondary" onclick="closeConfirmModal()">Annuler</button>
            <button class="btn-primary" id="confirmMatchBtn" onclick="confirmMatch()">Confirmer</button>
        </div>
    </div>
</div>

<style>
.league-lobby-container {
    min-height: 100vh;
    background: linear-gradient(135deg, #0a0a15 0%, #1a1a2e 50%, #16213e 100%);
    padding: 20px;
    padding-top: 60px;
}

.back-button-fixed {
    position: fixed;
    top: 10px;
    left: 10px;
    padding: 8px 14px;
    background: rgba(26, 26, 46, 0.9);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    font-size: 0.9em;
    text-decoration: none;
    z-index: 100;
    backdrop-filter: blur(5px);
}

.league-header {
    text-align: center;
    margin-bottom: 25px;
}

.league-header h1 {
    color: #00d4ff;
    font-size: 2rem;
    margin: 0 0 10px;
    text-shadow: 0 0 20px rgba(0, 212, 255, 0.5);
}

.current-division {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.1);
    padding: 8px 20px;
    border-radius: 20px;
}

.division-emoji {
    font-size: 1.5rem;
}

.division-name {
    color: #fff;
    font-weight: bold;
    text-transform: uppercase;
}

.division-points {
    color: #00d4ff;
    font-size: 0.9rem;
}

/* Division Selector */
.division-selector-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    text-align: center;
}

.division-selector-section h3 {
    color: #fff;
    margin: 0 0 5px;
}

.division-selector-section .hint-text {
    color: #888;
    font-size: 0.9rem;
    margin-bottom: 15px;
}

.division-selector {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.division-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px 25px;
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid transparent;
    border-radius: 12px;
    color: #fff;
    cursor: pointer;
    transition: all 0.3s;
    min-width: 100px;
}

.division-option:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
}

.division-option.selected {
    border-color: #00d4ff;
    background: rgba(0, 212, 255, 0.2);
}

.division-option.current::after {
    content: "(Actuelle)";
    font-size: 0.7rem;
    color: #00d4ff;
    margin-top: 5px;
}

.div-emoji {
    font-size: 2rem;
    margin-bottom: 5px;
}

.div-name {
    font-weight: bold;
    text-transform: uppercase;
}

.div-reward {
    font-size: 0.75rem;
    color: #27ae60;
    font-weight: 600;
    margin-top: 3px;
}

.div-fee {
    font-size: 0.75rem;
    color: #e67e22;
    font-weight: 600;
    margin-top: 3px;
}

/* Matchmaking Area */
.matchmaking-area {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 20px;
    align-items: start;
    margin-bottom: 25px;
}

.opponents-section, .player-section {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 20px;
}

.opponents-section h3, .player-section h3 {
    color: #fff;
    margin: 0 0 15px;
    text-align: center;
}

.opponents-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 200px;
}

.loading-opponents {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: #888;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid rgba(255, 255, 255, 0.1);
    border-top-color: #00d4ff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.empty-message {
    color: #888;
    text-align: center;
    padding: 40px 20px;
    font-style: italic;
}

/* Player Card */
.player-card {
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}

.player-card:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(5px);
}

.player-card.current-player {
    border: 2px solid #00d4ff;
    cursor: default;
}

.player-card.current-player:hover {
    transform: none;
}

.player-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.player-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-size: 1.5rem;
    font-weight: bold;
}

.player-info {
    flex: 1;
}

.player-info h4 {
    color: #fff;
    margin: 0 0 5px;
    font-size: 1.1rem;
}

.player-stats-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 5px;
}

.efficiency {
    color: #00d4ff;
    font-size: 0.9rem;
}

.division-badge-small {
    font-size: 1.2rem;
}

.player-record {
    color: #888;
    font-size: 0.85rem;
}

.ready-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: bold;
}

.ready-status.ready {
    background: rgba(0, 255, 0, 0.2);
    color: #00ff00;
}

.select-checkbox {
    width: 24px;
    height: 24px;
    border: 2px solid #00d4ff;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #00d4ff;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.select-checkbox:hover, .select-checkbox.selected {
    background: #00d4ff;
    color: #000;
}

/* VS Divider */
.vs-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.vs-divider span {
    font-size: 2.5rem;
    font-weight: bold;
    color: #ff4444;
    text-shadow: 0 0 20px rgba(255, 68, 68, 0.5);
}

/* My Stats Summary */
.my-stats-summary {
    display: flex;
    justify-content: space-around;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.my-stats-summary .stat-item {
    text-align: center;
}

.my-stats-summary .stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: #00d4ff;
}

.my-stats-summary .stat-label {
    font-size: 0.8rem;
    color: #888;
}

/* Action Buttons */
.action-buttons {
    text-align: center;
    margin-bottom: 20px;
}

.btn-primary {
    background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
    color: #000;
    border: none;
    padding: 15px 40px;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0, 212, 255, 0.4);
}

.btn-primary:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 15px 40px;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
}

.btn-large {
    padding: 18px 50px;
}

.btn-icon {
    margin-right: 10px;
}

.queue-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-top: 15px;
    color: #00d4ff;
}

/* Rankings Link */
.rankings-link {
    text-align: center;
}

.btn-link {
    color: #00d4ff;
    text-decoration: none;
    font-size: 1rem;
    transition: opacity 0.3s;
}

.btn-link:hover {
    opacity: 0.8;
}

/* Stats Modal */
.stats-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 20px;
}

.stats-modal-content {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-radius: 20px;
    padding: 25px;
    max-width: 500px;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    color: #fff;
    font-size: 1.5rem;
    cursor: pointer;
}

.modal-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.modal-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.modal-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.modal-player-info h3 {
    color: #fff;
    margin: 0 0 5px;
}

.modal-division {
    font-size: 1.2rem;
}

.modal-stats {
    margin-bottom: 20px;
}

.stats-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.stat-box {
    flex: 1;
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 10px;
    text-align: center;
}

.stat-box .stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: #00d4ff;
}

.stat-box .stat-label {
    font-size: 0.8rem;
    color: #888;
}

.efficiency-section {
    margin: 20px 0;
    text-align: center;
}

.efficiency-section h4 {
    color: #fff;
    margin-bottom: 15px;
}

.efficiency-section canvas {
    max-width: 100%;
}

.category-breakdown {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.category-item {
    background: rgba(255, 255, 255, 0.05);
    padding: 10px;
    border-radius: 8px;
}

.category-name {
    font-size: 0.8rem;
    color: #888;
}

.category-value {
    font-size: 1rem;
    color: #00d4ff;
    font-weight: bold;
}

.btn-select-opponent {
    width: 100%;
    margin-top: 15px;
}

/* Confirm Modal */
.confirm-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1001;
}

.confirm-modal-content {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    max-width: 400px;
    width: 90%;
}

.confirm-modal-content h3 {
    color: #fff;
    margin: 0 0 15px;
}

.confirm-modal-content p {
    color: #ccc;
    margin-bottom: 20px;
}

.fee-warning {
    background: rgba(255, 215, 0, 0.2);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    color: #ffd700;
}

.confirm-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.confirm-buttons button {
    min-width: 120px;
}

/* Mobile Responsive */
@media (max-width: 900px) {
    .matchmaking-area {
        grid-template-columns: 1fr;
    }
    
    .vs-divider {
        padding: 10px;
    }
    
    .vs-divider span {
        font-size: 1.5rem;
    }
    
    .opponents-section {
        order: 2;
    }
    
    .player-section {
        order: 1;
    }
}

@media (max-width: 600px) {
    .division-selector {
        flex-direction: column;
        align-items: center;
    }
    
    .division-option {
        width: 100%;
        max-width: 200px;
    }
    
    .stats-row {
        flex-wrap: wrap;
    }
    
    .stat-box {
        min-width: calc(50% - 10px);
    }
}
</style>

<script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-firestore-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Firebase config
const firebaseConfig = {
    projectId: "{{ config('services.firebase.project_id') }}",
    apiKey: "{{ config('services.firebase.api_key') }}",
};

// Initialize Firebase
if (!firebase.apps.length) {
    firebase.initializeApp(firebaseConfig);
}
const db = firebase.firestore();

// Current user data
const currentUser = {
    id: {{ Auth::id() }},
    name: "{{ Auth::user()->name }}",
    avatar_url: "{{ Auth::user()->avatar_url ?? '' }}",
    division: "{{ $division->division ?? 'bronze' }}",
    efficiency: {{ $efficiency ?? 0 }},
    matches_played: {{ $stats->matches_played ?? 0 }},
    matches_won: {{ $stats->matches_won ?? 0 }},
    matches_lost: {{ $stats->matches_lost ?? 0 }}
};

let selectedDivision = currentUser.division;
let selectedOpponent = null;
let inQueue = false;
let queueUnsubscribe = null;

// Division selection
document.querySelectorAll('.division-option').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.division-option').forEach(b => b.classList.remove('selected'));
        this.classList.add('selected');
        selectedDivision = this.dataset.division;
        
        // Refresh opponents for new division
        if (inQueue) {
            leaveQueue();
            joinQueue();
        }
    });
});

// Join Queue
document.getElementById('joinQueueBtn').addEventListener('click', joinQueue);
document.getElementById('leaveQueueBtn').addEventListener('click', leaveQueue);

async function joinQueue() {
    const joinBtn = document.getElementById('joinQueueBtn');
    const leaveBtn = document.getElementById('leaveQueueBtn');
    const queueStatus = document.getElementById('queueStatus');
    
    joinBtn.style.display = 'none';
    leaveBtn.style.display = 'inline-block';
    queueStatus.style.display = 'flex';
    inQueue = true;
    
    // Add self to Firestore queue
    const queueRef = db.collection('leagueIndividualQueue').doc(currentUser.id.toString());
    await queueRef.set({
        id: currentUser.id,
        name: currentUser.name,
        avatar_url: currentUser.avatar_url,
        division: selectedDivision,
        efficiency: currentUser.efficiency,
        matches_played: currentUser.matches_played,
        matches_won: currentUser.matches_won,
        matches_lost: currentUser.matches_lost,
        joined_at: firebase.firestore.FieldValue.serverTimestamp(),
        ready: true
    });
    
    // Listen for other players in queue
    listenForOpponents();
}

async function leaveQueue() {
    const joinBtn = document.getElementById('joinQueueBtn');
    const leaveBtn = document.getElementById('leaveQueueBtn');
    const queueStatus = document.getElementById('queueStatus');
    
    joinBtn.style.display = 'inline-block';
    leaveBtn.style.display = 'none';
    queueStatus.style.display = 'none';
    inQueue = false;
    
    // Remove from queue
    await db.collection('leagueIndividualQueue').doc(currentUser.id.toString()).delete();
    
    // Stop listening
    if (queueUnsubscribe) {
        queueUnsubscribe();
        queueUnsubscribe = null;
    }
    
    // Clear opponents list
    document.getElementById('opponentsList').innerHTML = '<div class="empty-message">Rejoignez la file pour voir les adversaires disponibles</div>';
}

function listenForOpponents() {
    const opponentsList = document.getElementById('opponentsList');
    const noOpponentsMsg = document.getElementById('noOpponentsMsg');
    
    // Listen for players in the same division
    queueUnsubscribe = db.collection('leagueIndividualQueue')
        .where('division', '==', selectedDivision)
        .where('ready', '==', true)
        .onSnapshot(snapshot => {
            const opponents = [];
            snapshot.forEach(doc => {
                const data = doc.data();
                if (data.id !== currentUser.id) {
                    opponents.push(data);
                }
            });
            
            renderOpponents(opponents);
        });
}

function renderOpponents(opponents) {
    const opponentsList = document.getElementById('opponentsList');
    
    if (opponents.length === 0) {
        opponentsList.innerHTML = '<div class="empty-message">Aucun adversaire disponible. En attente...</div>';
        return;
    }
    
    // Show max 3 opponents
    const displayOpponents = opponents.slice(0, 3);
    
    opponentsList.innerHTML = displayOpponents.map(opp => `
        <div class="player-card opponent-card" onclick="showPlayerStats(${JSON.stringify(opp).replace(/"/g, '&quot;')})">
            <div class="player-avatar">
                ${opp.avatar_url ? 
                    `<img src="${opp.avatar_url}" alt="Avatar">` : 
                    `<div class="default-avatar">${opp.name.charAt(0).toUpperCase()}</div>`
                }
            </div>
            <div class="player-info">
                <h4>${opp.name}</h4>
                <div class="player-stats-row">
                    <span class="efficiency">🎯 ${opp.efficiency.toFixed(1)}%</span>
                    <span class="division-badge-small">${getDivisionEmoji(opp.division)}</span>
                </div>
                <div class="player-record">${opp.matches_won}V - ${opp.matches_lost}D</div>
            </div>
            <div class="select-checkbox" onclick="event.stopPropagation(); quickSelectOpponent(${opp.id}, '${opp.name}')">
                ✓
            </div>
        </div>
    `).join('');
}

function getDivisionEmoji(division) {
    const emojis = {
        'bronze': '🥉',
        'argent': '🥈',
        'or': '🥇',
        'platine': '💎',
        'diamant': '💠',
        'legende': '👑'
    };
    return emojis[division] || '🥉';
}

function showPlayerStats(playerData) {
    selectedOpponent = playerData;
    
    const modal = document.getElementById('statsModal');
    document.getElementById('modalPlayerName').textContent = playerData.name;
    document.getElementById('modalDivision').textContent = getDivisionEmoji(playerData.division) + ' ' + playerData.division.charAt(0).toUpperCase() + playerData.division.slice(1);
    document.getElementById('modalMatches').textContent = playerData.matches_played;
    document.getElementById('modalWins').textContent = playerData.matches_won;
    document.getElementById('modalLosses').textContent = playerData.matches_lost;
    document.getElementById('modalTiebreakersWon').textContent = playerData.tiebreakers_won || 0;
    document.getElementById('modalTiebreakersLost').textContent = playerData.tiebreakers_lost || 0;
    
    // Set avatar
    const modalAvatar = document.getElementById('modalAvatar');
    if (playerData.avatar_url) {
        modalAvatar.innerHTML = `<img src="${playerData.avatar_url}" alt="Avatar">`;
    } else {
        modalAvatar.innerHTML = `<div class="default-avatar" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2rem;">${playerData.name.charAt(0).toUpperCase()}</div>`;
    }
    
    modal.style.display = 'flex';
    
    // Draw radar chart (simplified - would need real category data)
    drawEfficiencyRadar(playerData);
}

function closeStatsModal() {
    document.getElementById('statsModal').style.display = 'none';
}

function drawEfficiencyRadar(playerData) {
    const canvas = document.getElementById('efficiencyRadar');
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart if any
    if (window.efficiencyChart) {
        window.efficiencyChart.destroy();
    }
    
    // Sample categories (would be real data from API)
    const categories = ['Général', 'Histoire', 'Science', 'Sport', 'Géographie', 'Culture', 'Art', 'Divertissement'];
    const values = categories.map(() => Math.floor(Math.random() * 100)); // Placeholder
    
    window.efficiencyChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: categories,
            datasets: [{
                label: 'Efficacité',
                data: values,
                fill: true,
                backgroundColor: 'rgba(0, 212, 255, 0.2)',
                borderColor: '#00d4ff',
                pointBackgroundColor: '#00d4ff',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#00d4ff'
            }]
        },
        options: {
            elements: {
                line: { borderWidth: 3 }
            },
            scales: {
                r: {
                    angleLines: { color: 'rgba(255,255,255,0.1)' },
                    grid: { color: 'rgba(255,255,255,0.1)' },
                    pointLabels: { color: '#fff' },
                    ticks: { display: false },
                    suggestedMin: 0,
                    suggestedMax: 100
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
    
    // Populate category breakdown
    const breakdown = document.getElementById('categoryBreakdown');
    breakdown.innerHTML = categories.map((cat, i) => `
        <div class="category-item">
            <div class="category-name">${cat}</div>
            <div class="category-value">${values[i]}%</div>
        </div>
    `).join('');
}

function selectOpponent() {
    if (!selectedOpponent) return;
    closeStatsModal();
    showConfirmModal();
}

function quickSelectOpponent(opponentId, opponentName) {
    // Find opponent in list
    db.collection('leagueIndividualQueue').doc(opponentId.toString()).get()
        .then(doc => {
            if (doc.exists) {
                selectedOpponent = doc.data();
                showConfirmModal();
            }
        });
}

function showConfirmModal() {
    const modal = document.getElementById('confirmMatchModal');
    const fee = document.querySelector('.division-option.selected')?.dataset.fee || 0;
    
    document.getElementById('confirmMatchText').textContent = 
        `Voulez-vous affronter ${selectedOpponent.name} ?`;
    
    if (parseInt(fee) > 0) {
        document.getElementById('feeWarning').style.display = 'block';
        document.getElementById('feeAmount').textContent = fee;
    } else {
        document.getElementById('feeWarning').style.display = 'none';
    }
    
    modal.style.display = 'flex';
}

function closeConfirmModal() {
    document.getElementById('confirmMatchModal').style.display = 'none';
}

async function confirmMatch() {
    if (!selectedOpponent) return;
    
    const confirmBtn = document.getElementById('confirmMatchBtn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Création du match...';
    
    try {
        const response = await fetch('/api/league/individual/create-match', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                opponent_id: selectedOpponent.id,
                division: selectedDivision
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove both players from queue
            await db.collection('leagueIndividualQueue').doc(currentUser.id.toString()).delete();
            await db.collection('leagueIndividualQueue').doc(selectedOpponent.id.toString()).delete();
            
            // Redirect to game
            window.location.href = `/league/individual/game/${data.match_id}`;
        } else {
            showToast(data.message || 'Erreur lors de la création du match', 'error');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirmer';
        }
    } catch (error) {
        console.error('Error creating match:', error);
        showToast('Erreur lors de la création du match', 'error');
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Confirmer';
    }
}

function showToast(message, type = 'info') {
    // Simple toast notification
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'error' ? '#ff4444' : type === 'success' ? '#00cc00' : '#00d4ff'};
        color: #fff;
        padding: 15px 25px;
        border-radius: 10px;
        z-index: 2000;
        animation: fadeIn 0.3s;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Cleanup on page unload
window.addEventListener('beforeunload', async () => {
    if (inQueue) {
        await db.collection('leagueIndividualQueue').doc(currentUser.id.toString()).delete();
    }
});
</script>
@endsection
