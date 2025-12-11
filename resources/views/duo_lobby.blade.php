@extends('layouts.app')

@section('content')
<div class="duo-lobby-container">
    <div class="duo-header">
        <button onclick="window.location.href='{{ route('menu') }}'" class="back-button">
            {{ __('Retour') }}
        </button>
        <h1>{{ __('MODE DUO') }}</h1>
        <div class="header-right">
            <button id="playerInfoBtn" class="player-info-btn" title="{{ __('Mon profil') }}">?</button>
            <div class="header-avatar">
                @if(Auth::user()->avatar_url)
                    <img src="/{{ Auth::user()->avatar_url }}" alt="Avatar">
                @else
                    <div class="default-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="lobby-content">

        @if(!($duoFullUnlocked ?? false))
        {{-- Avertissement : Fonctionnalités limitées avant niveau 11 --}}
        <div class="stats-warning-banner">
            <span class="warning-icon-topright">⚠️</span>
            <div class="warning-text">
                <strong>{{ __('Mode Duo Complet') }}</strong>
                <p>{{ __('Vos statistiques, mode Aléatoire et inviter, ne seront pas fonctionnel avant le niveau 10 du mode Solo.') }}</p>
                <div class="progress-indicator">
                    {{ __('Progression') }} : {{ __('Niveau') }} <strong>{{ max(1, ($choixNiveau ?? 1) - 1) }}</strong><span class="progress-max"> / 10</span>
                </div>
            </div>
        </div>
        @endif

        <div class="matchmaking-options">
            @if($duoFullUnlocked ?? false)
            {{-- Accès COMPLET : Matchmaking et Invitations disponibles --}}
            <div class="option-card">
                <h3>🎯 {{ __('MATCHMAKING ALÉATOIRE') }}</h3>
                <p>{{ __('Affrontez un adversaire de votre division') }}</p>
                <button id="randomMatchBtn" class="btn-primary btn-large">
                    {{ __('CHERCHER UN ADVERSAIRE') }}
                </button>
            </div>

            <div class="divider">{{ __('OU') }}</div>

            <div class="option-card">
                <h3>👥 {{ __('INVITER UN AMI') }}</h3>
                <p>{{ __('Défiez un joueur spécifique') }}</p>
                <div class="invite-section">
                    <input type="text" id="inviteInput" placeholder="{{ __('Code du joueur (ex: SB-4X2K)...') }}" class="invite-input">
                    <button id="inviteBtn" class="btn-secondary btn-large">
                        {{ __('INVITER') }}
                    </button>
                </div>
                <button id="openContactsBtn" class="btn-contacts">
                    📒 {{ __('Carnet') }}
                </button>
            </div>
            @else
            {{-- Mode Entraînement : Seulement le carnet pour recevoir des invitations --}}
            <div class="option-card training-mode-card">
                <h3>📒 {{ __('Carnet de contacts') }}</h3>
                <p>{{ __('Consultez vos contacts et recevez des invitations') }}</p>
                <button id="openContactsBtn" class="btn-contacts btn-large">
                    📒 {{ __('Ouvrir le Carnet') }}
                </button>
            </div>
            @endif
        </div>

        <div class="pending-invitations" id="pendingInvitations">
            <h3>📬 {{ __('Invitations reçues') }}</h3>
            <div id="invitationsList">
                <p class="no-invitations">{{ __('Aucune invitation pour le moment - Les invitations apparaîtront ici automatiquement') }}</p>
            </div>
        </div>
    </div>

    <div class="ranking-preview ranking-{{ strtolower($division['name'] ?? 'bronze') }}">
        <h3>🏆 {{ __('Classement') }} {{ $division['name'] ?? 'Bronze' }}</h3>
        <div class="ranking-list">
            @foreach($rankings ?? [] as $index => $player)
            <div class="ranking-item {{ $player['user_id'] == Auth::id() ? 'current-player' : '' }}">
                <span class="rank">#{{ $index + 1 }}</span>
                <div class="player-info-ranking">
                    <span class="player-name">{{ $player['user']['name'] }}</span>
                    <span class="player-stats-small">{{ $player['matches_won'] ?? 0 }}V - {{ $player['matches_lost'] ?? 0 }}D ({{ number_format(($player['matches_won'] ?? 0) / max(($player['matches_won'] ?? 0) + ($player['matches_lost'] ?? 0), 1) * 100, 1) }}%)</span>
                </div>
                <span class="player-points">{{ $player['points'] }} pts</span>
            </div>
            @endforeach
        </div>
        <button onclick="window.location.href='{{ route('duo.rankings') }}'" class="btn-link">
            {{ __('Voir le classement complet') }} →
        </button>
    </div>
</div>

<div id="contactsModal" class="modal-backdrop" style="display: none;">
    <div class="modal-content contacts-modal">
        <div class="modal-header">
            <h2>📒 {{ __('CARNET DE JOUEURS') }}</h2>
            <button class="modal-close" onclick="closeContactsModal()">&times;</button>
        </div>
        
        <div class="contacts-tabs">
            <button class="contacts-tab active" onclick="switchContactsTab('players')">👤 {{ __('Joueurs') }}</button>
            <button class="contacts-tab" onclick="switchContactsTab('groups')">👥 {{ __('Groupes') }}</button>
        </div>
        
        <button id="inviteSelectedBtn" class="btn-invite-selected" disabled>
            {{ __('INVITER LE JOUEUR SÉLECTIONNÉ') }}
        </button>
        
        <div id="playersTab" class="contacts-tab-content">
            <div class="multi-select-toolbar" id="multiSelectToolbar">
                <span class="multi-select-count" id="multiSelectCount">0 {{ __('contacts sélectionnés') }}</span>
                <div class="multi-select-actions">
                    <button class="btn-multi-action" onclick="createGroupFromSelection()">👥 {{ __('Créer groupe avec sélection') }}</button>
                    <button class="btn-multi-action cancel" onclick="cancelMultiSelect()">✕</button>
                </div>
            </div>
            <div style="padding: 10px 20px; text-align: right;">
                <button class="btn-create-group" onclick="toggleMultiSelectMode()" style="font-size: 0.85em; padding: 8px 12px;">
                    ☑ {{ __('Sélection multiple') }}
                </button>
            </div>
            <div id="contactsList" class="contacts-list">
                <p class="loading-contacts">{{ __('Chargement...') }}</p>
            </div>
        </div>
        
        <div id="groupsTab" class="contacts-tab-content" style="display: none;">
            <div class="group-create-section">
                <input type="text" id="newGroupName" class="group-name-input" placeholder="{{ __('Nom du nouveau groupe...') }}">
                <button class="btn-create-group" onclick="createNewGroup()">{{ __('Créer') }}</button>
            </div>
            <div id="groupsList" class="groups-list">
                <p class="loading-contacts">{{ __('Chargement...') }}</p>
            </div>
        </div>
    </div>
</div>

<div id="chatModal" class="modal-backdrop" style="display: none;">
    <div class="modal-content chat-modal">
        <div class="modal-header chat-header">
            <button class="chat-back-btn" onclick="closeChatModal()">←</button>
            <h2 id="chatContactName">{{ __('Chat') }}</h2>
            <button class="modal-close" onclick="closeChatModal()">&times;</button>
        </div>
        <div id="chatMessages" class="chat-messages">
            <p class="chat-loading">{{ __('Chargement...') }}</p>
        </div>
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="{{ __('Écrivez votre message...') }}" maxlength="500">
            <button id="sendMessageBtn" onclick="sendMessage()">{{ __('Envoyer') }}</button>
        </div>
    </div>
</div>

<div id="playerCardModal" class="modal-backdrop" style="display: none;">
    <div class="player-card-modal">
        <div class="player-card-header">
            <button class="close-btn" onclick="closePlayerCardModal()">&times;</button>
            <div class="player-card-avatar">
                @if(Auth::user()->avatar_url)
                    <img src="/{{ Auth::user()->avatar_url }}" alt="Avatar">
                @else
                    <div class="default-avatar" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.2); font-size: 2rem;">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                @endif
            </div>
            <div class="player-card-name">{{ Auth::user()->name }}</div>
            <div style="font-size: 0.9rem; opacity: 0.9;">{{ Auth::user()->player_code ?? 'SB-XXXX' }}</div>
        </div>
        <div class="player-card-body">
            <div class="player-card-item" onclick="window.location.href='{{ route('boutique') }}?section=coins&return_to=duo_lobby'">
                <span class="player-card-item-icon">🪙</span>
                <span class="player-card-item-label">{{ __('Pièces de compétence') }}</span>
                <span class="player-card-item-value">{{ number_format(Auth::user()->coins ?? 0) }}</span>
                <span class="player-card-item-arrow">→</span>
            </div>
            
            <div class="player-card-item" onclick="window.location.href='{{ route('avatars') }}?tab=strategic&return_to=duo_lobby'">
                <span class="player-card-item-icon">✨</span>
                <span class="player-card-item-label">{{ __('Avatar Stratégique') }}</span>
                <span class="player-card-item-value">{{ session('avatar', 'Aucun') }}</span>
                <span class="player-card-item-arrow">→</span>
            </div>
            
            <div class="player-card-item" onclick="window.location.href='{{ route('boutique') }}?section=lives&return_to=duo_lobby'">
                <span class="player-card-item-icon">❤️</span>
                <span class="player-card-item-label">{{ __('Vies') }}</span>
                <span class="player-card-item-value">{{ Auth::user()->lives ?? 0 }} / {{ config('game.life_max', 3) }}</span>
                <span class="player-card-item-arrow">→</span>
            </div>
            
            <div class="player-card-item" onclick="window.location.href='{{ route('avatars') }}?tab=player&return_to=duo_lobby'">
                <span class="player-card-item-icon">👤</span>
                <span class="player-card-item-label">{{ __('Avatar Joueur') }}</span>
                <span class="player-card-item-value">{{ __('Modifier') }}</span>
                <span class="player-card-item-arrow">→</span>
            </div>
            
            <div class="player-card-item no-click">
                <span class="player-card-item-icon">📊</span>
                <span class="player-card-item-label">{{ __('Cote') }}</span>
                <span class="player-card-item-value" id="playerOddsValue">--</span>
            </div>
        </div>
    </div>
</div>

<style>
.duo-lobby-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.duo-header {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.back-button {
    justify-self: start;
}

.duo-header h1 {
    font-size: 2.5em;
    color: white;
    margin: 0;
    text-align: center;
}

.header-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid white;
    flex-shrink: 0;
    justify-self: end;
}

.header-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.header-avatar .default-avatar {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 2em;
    font-weight: bold;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    justify-self: end;
}

.player-info-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: 2px solid white;
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.player-info-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.5);
}

.player-card-modal {
    max-width: 400px;
    width: 90%;
    background: white;
    border-radius: 20px;
    overflow: hidden;
}

.player-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    text-align: center;
    position: relative;
}

.player-card-header .close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
}

.player-card-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid white;
    margin: 0 auto 10px;
    overflow: hidden;
}

.player-card-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.player-card-name {
    font-size: 1.3rem;
    font-weight: bold;
    margin: 5px 0;
}

.player-card-body {
    padding: 20px;
}

.player-card-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 12px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.player-card-item:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.player-card-item.no-click {
    cursor: default;
}

.player-card-item.no-click:hover {
    transform: none;
}

.player-card-item-icon {
    font-size: 1.5rem;
    margin-right: 10px;
}

.player-card-item-label {
    flex: 1;
    font-weight: 600;
    color: #333;
}

.player-card-item-value {
    font-weight: bold;
    color: #667eea;
}

.player-card-item-arrow {
    color: #aaa;
    margin-left: 10px;
}

.division-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: white;
    padding: 10px 20px;
    border-radius: 12px;
}

.division-bronze {
    background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%);
}

.division-argent {
    background: linear-gradient(135deg, #C0C0C0 0%, #808080 100%);
}

.division-or {
    background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
}

.division-platine {
    background: linear-gradient(135deg, #E5E4E2 0%, #B0C4DE 100%);
}

.division-diamant {
    background: linear-gradient(135deg, #B9F2FF 0%, #00CED1 100%);
}

.division-légende {
    background: linear-gradient(135deg, #FF1493 0%, #8B008B 100%);
}

.division-info {
    font-size: 1em;
    font-weight: bold;
    white-space: nowrap;
}

.division-points {
    font-size: 0.85em;
    opacity: 0.9;
}

.lobby-content {
    display: grid;
    gap: 30px;
    margin-bottom: 40px;
}

/* Bannière d'avertissement Mode Duo Complet */
.stats-warning-banner {
    background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 10px;
    position: relative;
}

.stats-warning-banner .warning-icon-topright {
    position: absolute;
    top: 10px;
    right: 12px;
    font-size: 1.5em;
}

.stats-warning-banner .warning-text {
    color: white;
    text-align: center;
    padding-right: 30px;
}

.stats-warning-banner .warning-text strong {
    font-size: 1.15em;
    display: block;
    margin-bottom: 8px;
}

.stats-warning-banner .warning-text p {
    margin: 0 0 12px 0;
    opacity: 0.95;
    font-size: 0.9em;
    line-height: 1.4;
}

.stats-warning-banner .progress-indicator {
    background: rgba(255,255,255,0.25);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.95em;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.stats-warning-banner .progress-indicator strong {
    font-size: 1.3em;
    margin: 0 2px;
}

.stats-warning-banner .progress-max {
    opacity: 0.9;
}

/* Carte mode entraînement */
.training-mode-card {
    text-align: center;
}

.training-mode-card .btn-contacts.btn-large {
    width: 100%;
    padding: 15px 20px;
    font-size: 1.1em;
}

.player-card {
    display: flex;
    align-items: center;
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.player-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 20px;
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
    color: white;
    font-size: 2em;
    font-weight: bold;
}

.player-info h3 {
    margin: 0;
    font-size: 1.5em;
    color: #1a1a1a;
}

.player-stats {
    margin: 5px 0 0 0;
    color: #666;
}

.matchmaking-options {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 30px;
    align-items: center;
}

.option-card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    text-align: center;
}

.option-card h3 {
    margin: 0 0 10px 0;
    color: #1a1a1a;
}

.option-card p {
    margin: 0 0 20px 0;
    color: #666;
}

.divider {
    text-align: center;
    color: #999;
    font-weight: bold;
}

.invite-section {
    display: flex;
    gap: 10px;
}

.invite-input {
    flex: 1;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1em;
}

.invite-input:focus {
    outline: none;
    border-color: #667eea;
}

.btn-primary, .btn-secondary {
    padding: 15px 30px;
    border: none;
    border-radius: 8px;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #f0f0f0;
    color: #1a1a1a;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.btn-large {
    width: 100%;
}

.pending-invitations {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.pending-invitations h3 {
    margin: 0 0 15px 0;
    color: #1a1a1a;
}

.ranking-preview {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    position: relative;
}

.ranking-preview h3 {
    margin: 0 0 20px 0;
    color: #1a1a1a;
}

/* Contours par division */
.ranking-bronze {
    border: 4px solid transparent;
    background-image: 
        linear-gradient(white, white),
        linear-gradient(135deg, #CD7F32 0%, #8B4513 100%);
    background-origin: border-box;
    background-clip: padding-box, border-box;
}

.ranking-argent {
    border: 4px solid transparent;
    background-image: 
        linear-gradient(white, white),
        linear-gradient(135deg, #C0C0C0 0%, #808080 100%);
    background-origin: border-box;
    background-clip: padding-box, border-box;
}

.ranking-or {
    border: 4px solid transparent;
    background-image: 
        linear-gradient(white, white),
        linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
    background-origin: border-box;
    background-clip: padding-box, border-box;
}

.ranking-platine {
    border: 4px solid transparent;
    background-image: 
        linear-gradient(white, white),
        linear-gradient(135deg, #E5E4E2 0%, #B0C4DE 100%);
    background-origin: border-box;
    background-clip: padding-box, border-box;
}

.ranking-diamant {
    border: 4px solid #00CED1;
    box-shadow: 
        0 4px 6px rgba(0,0,0,0.1),
        0 0 60px rgba(0, 206, 209, 0.4),
        inset 0 0 80px rgba(185, 242, 255, 0.3);
}

.ranking-diamant::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 200px;
    height: 200px;
    background: 
        linear-gradient(135deg, transparent 40%, rgba(185, 242, 255, 0.5) 50%, transparent 60%),
        linear-gradient(225deg, transparent 40%, rgba(0, 206, 209, 0.3) 50%, transparent 60%);
    pointer-events: none;
    opacity: 0.6;
    z-index: 0;
}

.ranking-légende {
    border: 4px solid #FF1493;
    animation: flame-border 2s ease-in-out infinite;
    box-shadow: 
        0 4px 6px rgba(0,0,0,0.1),
        0 0 30px rgba(255, 20, 147, 0.6),
        0 0 60px rgba(139, 0, 139, 0.4);
}

@keyframes flame-border {
    0%, 100% {
        box-shadow: 
            0 4px 6px rgba(0,0,0,0.1),
            0 0 30px rgba(255, 20, 147, 0.6),
            0 0 60px rgba(139, 0, 139, 0.4),
            0 0 90px rgba(255, 69, 0, 0.3);
    }
    50% {
        box-shadow: 
            0 4px 6px rgba(0,0,0,0.1),
            0 0 40px rgba(255, 69, 0, 0.8),
            0 0 80px rgba(255, 20, 147, 0.6),
            0 0 120px rgba(139, 0, 139, 0.5);
    }
}

.ranking-preview > * {
    position: relative;
    z-index: 1;
}

.ranking-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 15px;
}

.ranking-item {
    display: grid;
    grid-template-columns: 50px 1fr auto;
    gap: 15px;
    align-items: center;
    padding: 12px;
    border-radius: 8px;
    background: #f9f9f9;
}

.ranking-item.current-player {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    border: 2px solid #667eea;
}

.rank {
    font-weight: bold;
    color: #667eea;
}

.player-info-ranking {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.player-name {
    font-weight: 500;
}

.player-stats-small {
    font-size: 0.85em;
    color: #666;
}

.player-level, .player-points {
    color: #666;
    font-size: 0.9em;
}

.btn-link {
    background: none;
    border: none;
    color: #667eea;
    cursor: pointer;
    font-size: 1em;
    padding: 10px;
}

.btn-link:hover {
    text-decoration: underline;
}

.back-button {
    background: #f0f0f0;
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    cursor: pointer;
    font-size: 1em;
}

.back-button:hover {
    background: #e0e0e0;
}

@media (max-width: 768px) {
    .matchmaking-options {
        grid-template-columns: 1fr;
    }
    
    .divider {
        display: none;
    }
    
    .duo-header {
        flex-direction: column;
        gap: 15px;
    }
}

/* Mobile Portrait - Compactage optimal */
@media (max-width: 480px) and (orientation: portrait) {
    body {
        overflow-x: hidden;
        padding: 0;
    }
    
    .duo-lobby-container {
        padding: 8px;
        max-width: 100%;
    }
    
    .duo-header {
        gap: 8px;
        margin-bottom: 12px;
    }
    
    .back-button {
        padding: 6px 10px;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    
    .duo-header h1 {
        font-size: 1.4rem;
        margin: 0;
    }
    
    .header-avatar {
        width: 55px;
        height: 55px;
        border: 2px solid white;
    }
    
    .header-avatar .default-avatar {
        font-size: 1.6em;
    }
    
    .lobby-content {
        gap: 12px;
        margin-bottom: 15px;
    }
    
    .player-card {
        padding: 12px;
        flex-direction: column;
        text-align: center;
    }
    
    .player-avatar {
        width: 60px;
        height: 60px;
        margin-right: 0;
        margin-bottom: 10px;
    }
    
    .player-info h3 {
        font-size: 1.2rem;
    }
    
    .player-stats {
        font-size: 0.9rem;
    }
    
    .matchmaking-options {
        gap: 12px;
    }
    
    .option-card {
        padding: 15px;
    }
    
    .option-card h3 {
        font-size: 1.1rem;
        margin-bottom: 8px;
    }
    
    .option-card p {
        font-size: 0.9rem;
        margin-bottom: 12px;
    }
    
    .invite-section {
        flex-direction: column;
        gap: 8px;
    }
    
    .invite-input {
        padding: 10px;
        font-size: 0.95rem;
    }
    
    .btn-primary, .btn-secondary {
        padding: 12px 20px;
        font-size: 1rem;
    }
    
    .pending-invitations {
        padding: 12px;
    }
    
    .pending-invitations h3 {
        font-size: 1.1rem;
        margin-bottom: 10px;
    }
    
    .ranking-preview {
        padding: 12px;
    }
    
    .ranking-preview h3 {
        font-size: 1.1rem;
        margin-bottom: 12px;
    }
    
    .ranking-list {
        gap: 8px;
        margin-bottom: 10px;
    }
    
    .ranking-item {
        grid-template-columns: 35px 1fr auto;
        gap: 8px;
        padding: 8px;
        font-size: 0.9rem;
    }
    
    .player-info-ranking {
        gap: 1px;
    }
    
    .player-name {
        font-size: 0.9rem;
    }
    
    .player-stats-small {
        font-size: 0.75rem;
    }
    
    .player-level {
        display: none;
    }
    
    .player-points {
        font-size: 0.85rem;
    }
    
    .rank {
        font-size: 0.9rem;
    }
    
    .btn-link {
        font-size: 0.9rem;
        padding: 8px;
    }
}

.btn-contacts {
    margin-top: 15px;
    width: 100%;
    padding: 12px 20px;
    background: #f0f0f0;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-contacts:hover {
    background: #e0e0e0;
}

.no-invitations {
    color: #999;
    font-style: italic;
    text-align: center;
    margin: 10px 0;
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 16px;
    padding: 0;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 2px solid #e0e0e0;
}

.modal-header h2 {
    margin: 0;
    color: #1a1a1a;
    font-size: 1.5em;
}

.modal-close {
    background: none;
    border: none;
    font-size: 2em;
    color: #999;
    cursor: pointer;
    padding: 0;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: #1a1a1a;
}

/* Chat Modal Styles */
.chat-modal {
    max-width: 500px;
    height: 70vh;
    max-height: 600px;
}

.chat-header {
    gap: 15px;
}

.chat-back-btn {
    background: none;
    border: none;
    font-size: 1.5em;
    color: #666;
    cursor: pointer;
    padding: 0 10px;
}

.chat-back-btn:hover {
    color: #1a1a1a;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    background: #f5f5f5;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.chat-loading {
    text-align: center;
    color: #666;
    padding: 20px;
}

.chat-empty {
    text-align: center;
    color: #999;
    padding: 40px 20px;
    font-style: italic;
}

.chat-message {
    max-width: 80%;
    padding: 10px 15px;
    border-radius: 16px;
    word-wrap: break-word;
}

.chat-message.mine {
    align-self: flex-end;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-bottom-right-radius: 4px;
}

.chat-message.theirs {
    align-self: flex-start;
    background: white;
    color: #1a1a1a;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.chat-message .message-text {
    margin-bottom: 5px;
}

.chat-message .message-time {
    font-size: 0.75em;
    opacity: 0.7;
}

.chat-input-area {
    display: flex;
    gap: 10px;
    padding: 15px;
    border-top: 1px solid #e0e0e0;
    background: white;
}

.chat-input-area input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 25px;
    font-size: 1em;
    outline: none;
}

.chat-input-area input:focus {
    border-color: #667eea;
}

.chat-input-area button {
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 25px;
    font-weight: bold;
    cursor: pointer;
}

.chat-input-area button:hover {
    transform: scale(1.05);
}

/* Chat button on invitations */
.btn-chat {
    background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 1.2em;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-chat:hover {
    transform: scale(1.1);
}

/* Invitation item layout */
.invitation-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 10px;
}

.invitation-item > span {
    font-weight: 600;
    color: #333;
    text-align: center;
}

.invitation-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: center;
    width: 100%;
}

.btn-decline {
    background: white;
    color: #333;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    flex: 1;
    max-width: 100px;
}

.btn-decline:hover {
    background: #f0f0f0;
    border-color: #ccc;
}

.btn-accept {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    flex: 1;
    max-width: 100px;
}

.btn-accept:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(243, 156, 18, 0.4);
}

/* Unread badge */
.chat-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #e74c3c;
    color: white;
    font-size: 0.7em;
    font-weight: bold;
    min-width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2px;
}

.contact-chat-btn {
    position: relative;
    background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 1.1em;
    cursor: pointer;
    transition: all 0.3s;
}

.contact-chat-btn:hover {
    transform: scale(1.1);
}

.btn-invite-selected {
    margin: 15px 25px;
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1.1em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-invite-selected:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.4);
}

.btn-invite-selected:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.contacts-tabs {
    display: flex;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    margin: 0 25px;
}

.contacts-tab {
    flex: 1;
    padding: 12px;
    background: transparent;
    border: none;
    color: #666;
    cursor: pointer;
    font-size: 0.95em;
    transition: all 0.3s ease;
}

.contacts-tab.active {
    color: #667eea;
    border-bottom: 2px solid #667eea;
    font-weight: bold;
}

.contacts-tab:hover {
    color: #667eea;
}

.contacts-tab-content {
    overflow-y: auto;
    max-height: 50vh;
}

.group-create-section {
    display: flex;
    gap: 10px;
    padding: 15px 25px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.group-name-input {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #fff;
    color: #333;
    font-size: 0.95em;
}

.group-name-input::placeholder {
    color: #999;
}

.btn-create-group {
    padding: 10px 20px;
    background: #4CAF50;
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
}

.btn-create-group:hover {
    background: #45a049;
}

.groups-list {
    padding: 15px 25px;
}

.group-card {
    background: #f5f5f5;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.group-card:hover {
    background: #ececec;
}

.group-card.selected {
    background: rgba(102, 126, 234, 0.1);
    border-color: #667eea;
}

.group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.group-name {
    font-weight: bold;
    font-size: 1.1em;
    color: #333;
}

.group-member-count {
    color: #666;
    font-size: 0.9em;
}

.group-members-preview {
    margin-top: 8px;
    color: #888;
    font-size: 0.85em;
}

.group-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.group-action-btn {
    padding: 5px 10px;
    background: #e0e0e0;
    border: none;
    border-radius: 5px;
    color: #333;
    cursor: pointer;
    font-size: 0.85em;
}

.group-action-btn:hover {
    background: #d0d0d0;
}

.group-action-btn.delete {
    background: rgba(244, 67, 54, 0.2);
    color: #c62828;
}

.group-action-btn.delete:hover {
    background: rgba(244, 67, 54, 0.3);
}

.no-groups {
    text-align: center;
    color: #999;
    padding: 40px 20px;
}

.group-action-btn.view {
    background: rgba(102, 126, 234, 0.2);
    color: #667eea;
}

.group-action-btn.view:hover {
    background: rgba(102, 126, 234, 0.3);
}

.group-action-btn.invite {
    background: rgba(243, 156, 18, 0.2);
    color: #e67e22;
}

.group-action-btn.invite:hover {
    background: rgba(243, 156, 18, 0.3);
}

.group-detail-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
}

.group-detail-content {
    background: white;
    border-radius: 16px;
    max-width: 500px;
    width: 100%;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.group-detail-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.group-detail-header h3 {
    margin: 0;
    color: #333;
}

.group-detail-close {
    background: none;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: #999;
}

.group-members-list {
    flex: 1;
    overflow-y: auto;
    padding: 15px 20px;
}

.group-member-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 8px;
}

.group-member-info {
    display: flex;
    flex-direction: column;
}

.group-member-name {
    font-weight: 600;
    color: #333;
}

.group-member-code {
    font-size: 0.85em;
    color: #666;
}

.group-member-actions {
    display: flex;
    gap: 8px;
}

.btn-remove-member {
    padding: 6px 12px;
    background: rgba(244, 67, 54, 0.1);
    color: #c62828;
    border: 1px solid rgba(244, 67, 54, 0.3);
    border-radius: 6px;
    font-size: 0.8em;
    cursor: pointer;
}

.btn-remove-member:hover {
    background: rgba(244, 67, 54, 0.2);
}

.btn-remove-everywhere {
    padding: 6px 12px;
    background: rgba(244, 67, 54, 0.2);
    color: #b71c1c;
    border: 1px solid rgba(244, 67, 54, 0.4);
    border-radius: 6px;
    font-size: 0.8em;
    cursor: pointer;
}

.btn-remove-everywhere:hover {
    background: rgba(244, 67, 54, 0.3);
}

.multi-select-toolbar {
    display: none;
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
}

.multi-select-toolbar.active {
    display: flex;
}

.multi-select-count {
    font-weight: 600;
}

.multi-select-actions {
    display: flex;
    gap: 8px;
}

.btn-multi-action {
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 8px;
    font-size: 0.9em;
    cursor: pointer;
    font-weight: 500;
}

.btn-multi-action:hover {
    background: rgba(255, 255, 255, 0.3);
}

.btn-multi-action.cancel {
    background: transparent;
    border-color: rgba(255, 255, 255, 0.3);
}

.invitation-group-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.btn-add-contact {
    padding: 6px 12px;
    background: #4CAF50;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.85em;
    cursor: pointer;
}

.btn-add-contact:hover {
    background: #43a047;
}

.btn-create-group-invite {
    padding: 6px 12px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.85em;
    cursor: pointer;
}

.btn-create-group-invite:hover {
    background: #5a6fd6;
}

.contacts-list {
    overflow-y: auto;
    padding: 10px 25px 25px 25px;
}

.contact-card {
    border-bottom: 1px solid #e0e0e0;
    padding: 15px 0;
    cursor: pointer;
    transition: background 0.2s;
}

.contact-card:hover {
    background: #f9f9f9;
}

.contact-header {
    display: flex;
    align-items: center;
    gap: 15px;
}

.contact-checkbox {
    font-size: 1.5em;
    color: #999;
    min-width: 20px;
}

.contact-checkbox.selected {
    color: #667eea;
}

.contact-info {
    flex: 1;
}

.contact-name-code {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.contact-name {
    font-weight: 600;
    color: #1a1a1a;
}

.contact-code {
    color: #999;
    font-size: 0.9em;
}

.contact-stats {
    color: #666;
    font-size: 0.95em;
}

.contact-details {
    margin-top: 10px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 8px;
    display: none;
}

.contact-details.expanded {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 500px;
    }
}

.contact-details h4 {
    margin: 0 0 10px 0;
    color: #667eea;
    font-size: 0.9em;
    text-transform: uppercase;
}

.contact-details p {
    margin: 5px 0;
    color: #666;
    font-size: 0.9em;
}

.loading-contacts {
    text-align: center;
    color: #999;
    padding: 40px 0;
}

.no-contacts {
    text-align: center;
    color: #999;
    padding: 40px 20px;
}

/* Styles pour l'accès partiel */
.partial-access-notice {
    display: flex;
    justify-content: center;
    padding: 20px;
}

.unlock-progress-card {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    border: 2px solid #667eea;
    border-radius: 16px;
    padding: 30px;
    max-width: 500px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
}

.unlock-progress-card h3 {
    margin: 0 0 10px 0;
    color: #667eea;
    font-size: 1.4em;
}

.unlock-progress-card > p {
    margin: 0 0 25px 0;
    color: #1a1a1a;
    font-size: 1.1em;
}

.unlock-requirement {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    background: white;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: left;
}

.unlock-icon {
    font-size: 2em;
}

.unlock-text strong {
    color: #1a1a1a;
    font-size: 1em;
}

.unlock-text p {
    margin: 5px 0 10px 0;
    color: #666;
}

.progress-indicator {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    display: inline-block;
    font-size: 0.95em;
}

.progress-indicator strong {
    color: #fff;
}

.btn-solo-link {
    display: inline-block;
    padding: 15px 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    text-decoration: none;
    border-radius: 10px;
    font-weight: bold;
    font-size: 1.1em;
    transition: all 0.3s;
}

.btn-solo-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

@media (max-width: 480px) {
    .unlock-progress-card {
        padding: 20px;
    }
    
    .unlock-progress-card h3 {
        font-size: 1.2em;
    }
    
    .unlock-requirement {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .btn-solo-link {
        padding: 12px 24px;
        font-size: 1em;
    }
}
</style>

@php
$duoTranslations = [
    'RECHERCHE EN COURS...' => __('RECHERCHE EN COURS...'),
    'Erreur lors de la recherche' => __('Erreur lors de la recherche'),
    'CHERCHER UN ADVERSAIRE' => __('CHERCHER UN ADVERSAIRE'),
    'Erreur de connexion' => __('Erreur de connexion'),
    'Entrez le code du joueur (ex: SB-4X2K)' => __('Entrez le code du joueur (ex: SB-4X2K)'),
    'Invitation envoyée !' => __('Invitation envoyée !'),
    "Erreur lors de l'invitation" => __("Erreur lors de l'invitation"),
    'Aucune invitation pour le moment - Les invitations apparaîtront ici automatiquement' => __('Aucune invitation pour le moment - Les invitations apparaîtront ici automatiquement'),
    'vous invite' => __('vous invite'),
    'Accepter' => __('Accepter'),
    'Refuser' => __('Refuser'),
    'Annuler' => __('Annuler'),
    'Invitation envoyée à' => __('Invitation envoyée à'),
    'Invitations envoyées' => __('Invitations envoyées'),
    'Invitations reçues' => __('Invitations reçues'),
    'Voir le salon' => __('Voir le salon'),
    'Joueur' => __('Joueur'),
    'Invitation annulée' => __('Invitation annulée'),
    "Erreur lors de l'annulation" => __("Erreur lors de l'annulation"),
    'Aucun joueur dans votre carnet' => __('Aucun joueur dans votre carnet'),
    'Historique' => __('Historique'),
    'matchs joués' => __('matchs joués'),
    'Dernière partie' => __('Dernière partie'),
    'Victoires' => __('Victoires'),
    'Défaites' => __('Défaites'),
    'Chargement...' => __('Chargement...'),
    'Aucun contact pour le moment' => __('Aucun contact pour le moment'),
    'Jouez des parties Duo pour créer votre carnet !' => __('Jouez des parties Duo pour créer votre carnet !'),
    'Erreur lors du chargement des contacts' => __('Erreur lors du chargement des contacts'),
    'Niveau' => __('Niveau'),
    'STATS PERSONNELLES DUO' => __('STATS PERSONNELLES DUO'),
    'Efficacité' => __('Efficacité'),
    'Parties totales' => __('Parties totales'),
    'Bilan global' => __('Bilan global'),
    'CONTRE VOUS' => __('CONTRE VOUS'),
    'Bilan' => __('Bilan'),
    'Parties jouées' => __('Parties jouées'),
    'Manches Décisives' => __('Manches Décisives'),
    'Aucun groupe' => __('Aucun groupe'),
    'Créez un groupe pour organiser vos contacts !' => __('Créez un groupe pour organiser vos contacts !'),
    'Erreur lors du chargement des groupes' => __('Erreur lors du chargement des groupes'),
    'membre(s)' => __('membre(s)'),
    'Supprimer' => __('Supprimer'),
    'Groupe sélectionné' => __('Groupe sélectionné'),
    'Entrez un nom de groupe' => __('Entrez un nom de groupe'),
    'Groupe créé' => __('Groupe créé'),
    'Erreur' => __('Erreur'),
    'Supprimer ce groupe ?' => __('Supprimer ce groupe ?'),
    'Groupe supprimé' => __('Groupe supprimé'),
    'Voir' => __('Voir'),
    'Fermer' => __('Fermer'),
    'Membres du groupe' => __('Membres du groupe'),
    'Retirer du groupe' => __('Retirer du groupe'),
    'Supprimer partout' => __('Supprimer partout'),
    'Membre retiré' => __('Membre retiré'),
    'Ajouter au groupe' => __('Ajouter au groupe'),
    'Sélectionnez des contacts' => __('Sélectionnez des contacts'),
    'contacts sélectionnés' => __('contacts sélectionnés'),
    'Créer groupe avec sélection' => __('Créer groupe avec sélection'),
    'Inviter le groupe' => __('Inviter le groupe'),
    'Invitations envoyées' => __('Invitations envoyées'),
    'Ajouter au carnet' => __('Ajouter au carnet'),
    'Créer un groupe' => __('Créer un groupe'),
    'Contact ajouté' => __('Contact ajouté'),
    'Aucun membre dans ce groupe' => __('Aucun membre dans ce groupe'),
    'Code joueur manquant' => __('Code joueur manquant'),
    'Contact supprimé partout' => __('Contact supprimé partout'),
    'Supprimer ce contact de tous les groupes et du carnet ?' => __('Supprimer ce contact de tous les groupes et du carnet ?')
];
@endphp
<script>
const duoTranslations = @json($duoTranslations);
function t(key) { return duoTranslations[key] || key; }

function openPlayerCardModal() {
    document.getElementById('playerCardModal').style.display = 'flex';
    document.getElementById('playerCardModal').style.alignItems = 'center';
    document.getElementById('playerCardModal').style.justifyContent = 'center';
    
    fetch('/api/player/stats', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.odds !== undefined) {
            document.getElementById('playerOddsValue').textContent = data.odds.toFixed(2) + 'x';
        }
    })
    .catch(() => {});
}

function closePlayerCardModal() {
    document.getElementById('playerCardModal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const playerInfoBtn = document.getElementById('playerInfoBtn');
    if (playerInfoBtn) {
        playerInfoBtn.addEventListener('click', openPlayerCardModal);
    }
    
    document.getElementById('playerCardModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePlayerCardModal();
        }
    });

    const randomMatchBtn = document.getElementById('randomMatchBtn');
    const inviteBtn = document.getElementById('inviteBtn');
    const inviteInput = document.getElementById('inviteInput');

    // Ces éléments n'existent que si l'utilisateur a l'accès complet
    if (randomMatchBtn) {
        randomMatchBtn.addEventListener('click', function() {
            this.disabled = true;
            this.textContent = t('RECHERCHE EN COURS...');
            
            fetch('{{ route("duo.matchmaking.random") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '{{ route("duo.matchmaking") }}?match_id=' + data.match_id;
                } else {
                    showToast(data.message || t('Erreur lors de la recherche'), 'error');
                    this.disabled = false;
                    this.textContent = t('CHERCHER UN ADVERSAIRE');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast(t('Erreur de connexion'), 'error');
                this.disabled = false;
                this.textContent = t('CHERCHER UN ADVERSAIRE');
            });
        });
    }

    if (inviteBtn && inviteInput) {
        inviteBtn.addEventListener('click', function() {
            const playerCode = inviteInput.value.trim().toUpperCase();
            if (!playerCode) {
                showToast(t('Entrez le code du joueur (ex: SB-4X2K)'), 'warning');
                return;
            }

            inviteBtn.disabled = true;
            inviteBtn.textContent = t('Envoi...');

            fetch('{{ route("duo.invite") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ player_code: playerCode })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.redirect_url) {
                    showToast(t('Invitation envoyée ! Redirection vers le salon...'), 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 500);
                } else {
                    showToast(data.message || t("Erreur lors de l'invitation"), 'error');
                    inviteBtn.disabled = false;
                    inviteBtn.textContent = t('INVITER');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast(t('Erreur de connexion'), 'error');
                inviteBtn.disabled = false;
                inviteBtn.textContent = t('INVITER');
            });
        });
    }

    // Vérifier les invitations reçues
    function checkInvitations() {
        fetch('{{ route("duo.invitations") }}')
            .then(response => response.json())
            .then(data => {
                displayInvitations(data.invitations || [], data.sent_invitations || []);
            });
    }

    function displayInvitations(receivedInvitations, sentInvitations) {
        const list = document.getElementById('invitationsList');
        let html = '';
        
        if (sentInvitations.length > 0) {
            html += `<div class="invitations-section-title" style="font-size: 0.9rem; color: rgba(255,255,255,0.7); margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid rgba(255,255,255,0.1);">📤 ${t('Invitations envoyées')}</div>`;
            html += sentInvitations.map(inv => `
                <div class="invitation-item sent-invitation" style="background: rgba(102, 126, 234, 0.1); border-left: 3px solid #667eea;">
                    <span>${t('Invitation envoyée à')} <strong>${inv.to_player?.name || t('Joueur')}</strong></span>
                    <div class="invitation-actions">
                        <button onclick="cancelInvitation(${inv.match_id})" class="btn-decline">${t('Annuler')}</button>
                        ${inv.lobby_code ? `<button onclick="window.location.href='/lobby/${inv.lobby_code}'" class="btn-accept">${t('Voir le salon')}</button>` : ''}
                    </div>
                </div>
            `).join('');
        }
        
        if (receivedInvitations.length > 0) {
            if (sentInvitations.length > 0) {
                html += `<div class="invitations-section-title" style="font-size: 0.9rem; color: rgba(255,255,255,0.7); margin: 15px 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid rgba(255,255,255,0.1);">📥 ${t('Invitations reçues')}</div>`;
            }
            html += receivedInvitations.map(inv => `
                <div class="invitation-item">
                    <span>${inv.from_player.name} ${t('vous invite')}</span>
                    <div class="invitation-actions">
                        <button onclick="declineInvitation(${inv.match_id})" class="btn-decline">${t('Refuser')}</button>
                        <button onclick="openChat(${inv.from_player.id}, '${inv.from_player.name}')" class="btn-chat" title="${t('Envoyer un message')}">💬</button>
                        <button onclick="acceptInvitation(${inv.match_id})" class="btn-accept">${t('Accepter')}</button>
                    </div>
                    <div class="invitation-group-actions">
                        <button onclick="addToCarnet('${inv.from_player.player_code || ''}')" class="btn-add-contact">📒 ${t('Ajouter au carnet')}</button>
                        <button onclick="createGroupFromInvitation(${inv.from_player.id}, '${inv.from_player.name}')" class="btn-create-group-invite">👥 ${t('Créer un groupe')}</button>
                    </div>
                </div>
            `).join('');
        }
        
        if (receivedInvitations.length === 0 && sentInvitations.length === 0) {
            html = '<p class="no-invitations">' + t('Aucune invitation pour le moment - Les invitations apparaîtront ici automatiquement') + '</p>';
        }
        
        list.innerHTML = html;
    }

    checkInvitations();
    setInterval(checkInvitations, 5000);
});

function acceptInvitation(matchId) {
    fetch(`/duo/matches/${matchId}/accept`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            window.location.href = data.redirect_url || '/lobby/' + data.lobby_code;
        } else {
            showToast(data.message || t('Erreur lors de l\'acceptation'), 'error');
        }
    })
    .catch(error => {
        console.error('Accept invitation error:', error);
        showToast(t('Erreur lors de l\'acceptation'), 'error');
    });
}

function declineInvitation(matchId) {
    fetch(`/duo/matches/${matchId}/decline`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(t('Invitation refusée'), 'info');
            location.reload();
        } else {
            showToast(data.message || t('Erreur lors du refus'), 'error');
        }
    })
    .catch(error => {
        console.error('Decline invitation error:', error);
        showToast(t('Erreur lors du refus'), 'error');
    });
}

function cancelInvitation(matchId) {
    fetch(`/duo/matches/${matchId}/cancel`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(t('Invitation annulée'), 'info');
            location.reload();
        } else {
            showToast(data.message || t('Erreur lors de l\'annulation'), 'error');
        }
    })
    .catch(error => {
        console.error('Cancel invitation error:', error);
        showToast(t('Erreur lors de l\'annulation'), 'error');
    });
}

let selectedContactId = null;

let contactsRefreshInterval = null;

function openContactsModal() {
    document.getElementById('contactsModal').style.display = 'flex';
    loadContacts();
    // Fallback: slow refresh every 60 seconds (Firestore handles real-time updates)
    if (!contactsRefreshInterval) {
        contactsRefreshInterval = setInterval(loadContacts, 60000);
    }
}

function closeContactsModal() {
    document.getElementById('contactsModal').style.display = 'none';
    selectedContactId = null;
    updateInviteButton();
    // Stop fallback refresh when modal is closed
    if (contactsRefreshInterval) {
        clearInterval(contactsRefreshInterval);
        contactsRefreshInterval = null;
    }
}

let allGroups = [];

function switchContactsTab(tab) {
    document.querySelectorAll('.contacts-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.contacts-tab-content').forEach(c => c.style.display = 'none');
    
    if (tab === 'players') {
        document.querySelector('.contacts-tab:first-child').classList.add('active');
        document.getElementById('playersTab').style.display = 'block';
    } else {
        document.querySelector('.contacts-tab:last-child').classList.add('active');
        document.getElementById('groupsTab').style.display = 'block';
        loadGroups();
    }
}

function loadGroups() {
    const groupsList = document.getElementById('groupsList');
    groupsList.innerHTML = '<p class="loading-contacts">' + t('Chargement...') + '</p>';
    
    fetch('/duo/contacts/groups', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.groups.length > 0) {
            allGroups = data.groups;
            displayGroups(data.groups);
        } else {
            groupsList.innerHTML = '<p class="no-groups">' + t('Aucun groupe') + '<br>' + t('Créez un groupe pour organiser vos contacts !') + '</p>';
        }
    })
    .catch(error => {
        console.error('Error loading groups:', error);
        groupsList.innerHTML = '<p class="no-groups">' + t('Erreur lors du chargement des groupes') + '</p>';
    });
}

function displayGroups(groups) {
    const groupsList = document.getElementById('groupsList');
    
    if (groups.length === 0) {
        groupsList.innerHTML = '<p class="no-groups">' + t('Aucun groupe') + '<br>' + t('Créez un groupe pour organiser vos contacts !') + '</p>';
        return;
    }
    
    groupsList.innerHTML = groups.map(group => `
        <div class="group-card" data-group-id="${group.id}">
            <div class="group-header">
                <span class="group-name">👥 ${group.name}</span>
                <span class="group-member-count">${group.member_count} ${t('membre(s)')}</span>
            </div>
            <div class="group-members-preview">
                ${group.members.slice(0, 3).map(m => m.name).join(', ')}${group.member_count > 3 ? '...' : ''}
            </div>
            <div class="group-actions" onclick="event.stopPropagation();">
                <button class="group-action-btn view" onclick="openGroupDetail(${group.id})">👁️ ${t('Voir')}</button>
                <button class="group-action-btn invite" onclick="inviteGroup(${group.id})">📨 ${t('Inviter le groupe')}</button>
                <button class="group-action-btn delete" onclick="deleteGroup(${group.id})">🗑️ ${t('Supprimer')}</button>
            </div>
        </div>
    `).join('');
}

let currentGroupDetailId = null;

function openGroupDetail(groupId) {
    const group = allGroups.find(g => g.id === groupId);
    if (!group) return;
    
    currentGroupDetailId = groupId;
    
    let modalHtml = `
        <div class="group-detail-modal" id="groupDetailModal" onclick="if(event.target === this) closeGroupDetail()">
            <div class="group-detail-content">
                <div class="group-detail-header">
                    <h3>👥 ${group.name} - ${t('Membres du groupe')}</h3>
                    <button class="group-detail-close" onclick="closeGroupDetail()">✕</button>
                </div>
                <div class="group-members-list" id="groupMembersList">
                    ${renderGroupMembers(group.members)}
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function renderGroupMembers(members) {
    if (members.length === 0) {
        return '<p class="no-groups">' + t('Aucun membre dans ce groupe') + '</p>';
    }
    
    return members.map(member => `
        <div class="group-member-item" data-member-id="${member.id}">
            <div class="group-member-info">
                <span class="group-member-name">${member.name}</span>
                <span class="group-member-code">${member.player_code}</span>
            </div>
            <div class="group-member-actions">
                <button class="btn-remove-member" onclick="removeMemberFromGroup(${currentGroupDetailId}, ${member.id})">${t('Retirer du groupe')}</button>
                <button class="btn-remove-everywhere" onclick="removeMemberEverywhere(${member.id})">${t('Supprimer partout')}</button>
            </div>
        </div>
    `).join('');
}

function closeGroupDetail() {
    const modal = document.getElementById('groupDetailModal');
    if (modal) modal.remove();
    currentGroupDetailId = null;
}

function removeMemberFromGroup(groupId, memberId) {
    fetch(`/duo/contacts/groups/${groupId}/members`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({ member_ids: [memberId] })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(t('Membre retiré'), 'success');
            loadGroups();
            if (currentGroupDetailId) {
                const group = allGroups.find(g => g.id === currentGroupDetailId);
                if (group) {
                    group.members = group.members.filter(m => m.id !== memberId);
                    document.getElementById('groupMembersList').innerHTML = renderGroupMembers(group.members);
                }
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function removeMemberEverywhere(memberId) {
    if (!confirm(t('Supprimer ce contact de tous les groupes et du carnet ?'))) return;
    
    fetch(`/duo/contacts/${memberId}`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(t('Contact supprimé partout'), 'success');
            loadContacts();
            loadGroups();
            closeGroupDetail();
        }
    })
    .catch(error => console.error('Error:', error));
}

function inviteGroup(groupId) {
    const group = allGroups.find(g => g.id === groupId);
    if (!group || group.members.length === 0) {
        showToast(t('Aucun membre dans ce groupe'));
        return;
    }
    
    selectedContactId = group.members[0].id;
    updateInviteButton();
    closeContactsModal();
    showToast(t('Groupe sélectionné') + ': ' + group.name + ' (' + group.member_count + ' ' + t('membre(s)') + ')', 'success');
}

function selectGroupForInvite(groupId) {
    inviteGroup(groupId);
}

function createNewGroup() {
    const nameInput = document.getElementById('newGroupName');
    const name = nameInput.value.trim();
    
    if (!name) {
        showToast(t('Entrez un nom de groupe'));
        return;
    }
    
    const memberIds = selectedContactId ? [selectedContactId] : [];
    
    fetch('/duo/contacts/groups', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({ name: name, member_ids: memberIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            nameInput.value = '';
            loadGroups();
            showToast(data.message || t('Groupe créé'), 'success');
        } else {
            showToast(data.message || t('Erreur'));
        }
    })
    .catch(error => {
        console.error('Error creating group:', error);
        showToast(t('Erreur'));
    });
}

function deleteGroup(groupId) {
    if (!confirm(t('Supprimer ce groupe ?'))) return;
    
    fetch(`/duo/contacts/groups/${groupId}`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadGroups();
            showToast(data.message || t('Groupe supprimé'), 'success');
        }
    })
    .catch(error => {
        console.error('Error deleting group:', error);
    });
}

function addToCarnet(playerCode) {
    if (!playerCode) {
        showToast(t('Code joueur manquant'));
        return;
    }
    
    fetch('/duo/contacts/add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({ player_code: playerCode })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(t('Contact ajouté'), 'success');
            loadContacts();
        } else {
            showToast(data.message || t('Erreur'));
        }
    })
    .catch(error => {
        console.error('Error adding contact:', error);
        showToast(t('Erreur'));
    });
}

function createGroupFromInvitation(playerId, playerName) {
    const groupName = prompt(t('Entrez un nom de groupe'), playerName);
    if (!groupName) return;
    
    fetch('/duo/contacts/groups', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({ name: groupName, member_ids: [playerId] })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(t('Groupe créé') + ': ' + groupName, 'success');
            loadGroups();
        } else {
            showToast(data.message || t('Erreur'));
        }
    })
    .catch(error => {
        console.error('Error creating group:', error);
        showToast(t('Erreur'));
    });
}

let selectedContactIds = [];
let multiSelectMode = false;

function toggleMultiSelectMode() {
    multiSelectMode = !multiSelectMode;
    selectedContactIds = [];
    updateMultiSelectToolbar();
    loadContacts();
}

function updateMultiSelectToolbar() {
    const toolbar = document.getElementById('multiSelectToolbar');
    if (toolbar) {
        if (multiSelectMode && selectedContactIds.length > 0) {
            toolbar.classList.add('active');
            document.getElementById('multiSelectCount').textContent = selectedContactIds.length + ' ' + t('contacts sélectionnés');
        } else {
            toolbar.classList.remove('active');
        }
    }
}

function toggleMultiContactSelection(contactId) {
    const idx = selectedContactIds.indexOf(contactId);
    if (idx > -1) {
        selectedContactIds.splice(idx, 1);
    } else {
        selectedContactIds.push(contactId);
    }
    updateMultiSelectToolbar();
    
    const checkbox = document.getElementById('checkbox-' + contactId);
    if (checkbox) {
        checkbox.classList.toggle('selected', selectedContactIds.includes(contactId));
        checkbox.textContent = selectedContactIds.includes(contactId) ? '☑' : '☐';
    }
}

function createGroupFromSelection() {
    if (selectedContactIds.length === 0) {
        showToast(t('Sélectionnez des contacts'));
        return;
    }
    
    const groupName = prompt(t('Entrez un nom de groupe'));
    if (!groupName) return;
    
    fetch('/duo/contacts/groups', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin',
        body: JSON.stringify({ name: groupName, member_ids: selectedContactIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(t('Groupe créé') + ': ' + groupName, 'success');
            multiSelectMode = false;
            selectedContactIds = [];
            updateMultiSelectToolbar();
            loadGroups();
            loadContacts();
        } else {
            showToast(data.message || t('Erreur'));
        }
    })
    .catch(error => {
        console.error('Error creating group:', error);
        showToast(t('Erreur'));
    });
}

function cancelMultiSelect() {
    multiSelectMode = false;
    selectedContactIds = [];
    updateMultiSelectToolbar();
    loadContacts();
}

function loadContacts() {
    const contactsList = document.getElementById('contactsList');
    // Only show loading if list is empty (first load)
    if (!contactsList.querySelector('.contact-card')) {
        contactsList.innerHTML = '<p class="loading-contacts">' + t('Chargement...') + '</p>';
    }

    fetch('/duo/contacts', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.contacts.length > 0) {
            displayContacts(data.contacts);
        } else {
            contactsList.innerHTML = '<p class="no-contacts">' + t('Aucun contact pour le moment') + '<br>' + t('Jouez des parties Duo pour créer votre carnet !') + '</p>';
        }
    })
    .catch(error => {
        console.error('Error loading contacts:', error);
        contactsList.innerHTML = '<p class="no-contacts">' + t('Erreur lors du chargement des contacts') + '</p>';
    });
}

function displayContacts(contacts) {
    const contactsList = document.getElementById('contactsList');
    const previousSelectedId = selectedContactId;
    
    const isMultiSelected = (id) => multiSelectMode && selectedContactIds.includes(id);
    const isSelected = (id) => multiSelectMode ? isMultiSelected(id) : id === previousSelectedId;
    const clickHandler = (id) => multiSelectMode ? `toggleMultiContactSelection(${id})` : `toggleContactSelection(${id})`;
    
    contactsList.innerHTML = contacts.map(contact => `
        <div class="contact-card" data-contact-id="${contact.id}">
            <div class="contact-header" onclick="${clickHandler(contact.id)}">
                <div class="contact-checkbox ${isSelected(contact.id) ? 'selected' : ''}" id="checkbox-${contact.id}">${isSelected(contact.id) ? '☑' : '☐'}</div>
                <div class="contact-info">
                    <div class="contact-name-code">
                        <span class="contact-name">${contact.name}</span>
                        <span class="contact-code">${contact.player_code}</span>
                    </div>
                    <div class="contact-stats">
                        ⭐ ${t('Niveau')} ${contact.level} | 🏆 ${contact.division} (#${contact.division_rank})
                    </div>
                </div>
                <button class="contact-chat-btn" data-contact-id="${contact.id}" onclick="event.stopPropagation(); openChat(${contact.id}, '${contact.name.replace(/'/g, "\\'")}');" title="${t('Envoyer un message')}">💬</button>
            </div>
            <div class="contact-details" id="details-${contact.id}">
                <h4>👤 ${t('STATS PERSONNELLES DUO')}</h4>
                <p>📊 ${t('Efficacité')}: ${contact.duo_efficiency}%</p>
                <p>🎮 ${t('Parties totales')}: ${contact.duo_total_matches}</p>
                <p>🏆 ${t('Bilan global')}: ${contact.duo_wins}V - ${contact.duo_losses}D</p>
                
                <h4 style="margin-top: 15px;">🤝 ${t('CONTRE VOUS')}</h4>
                <p>🏆 ${t('Bilan')}: ${contact.matches_won}V - ${contact.matches_lost}D (${contact.win_rate}%)</p>
                <p>🎮 ${t('Parties jouées')}: ${contact.matches_played_together}</p>
                <p>⚡ ${t('Manches Décisives')}: ${contact.decisive_rounds_stats}</p>
                <p>⏱️ ${t('Dernière partie')}: ${contact.last_played_at}</p>
            </div>
        </div>
    `).join('');
    
    setTimeout(updateContactUnreadBadges, 100);

    document.querySelectorAll('.contact-card').forEach(card => {
        card.querySelector('.contact-header').addEventListener('dblclick', (e) => {
            e.stopPropagation();
            toggleContactDetails(card.dataset.contactId);
        });
    });
}

function toggleContactSelection(contactId) {
    if (selectedContactId === contactId) {
        selectedContactId = null;
        document.getElementById(`checkbox-${contactId}`).textContent = '☐';
        document.getElementById(`checkbox-${contactId}`).classList.remove('selected');
    } else {
        if (selectedContactId) {
            document.getElementById(`checkbox-${selectedContactId}`).textContent = '☐';
            document.getElementById(`checkbox-${selectedContactId}`).classList.remove('selected');
        }
        selectedContactId = contactId;
        document.getElementById(`checkbox-${contactId}`).textContent = '☑';
        document.getElementById(`checkbox-${contactId}`).classList.add('selected');
    }
    updateInviteButton();
}

function toggleContactDetails(contactId) {
    const details = document.getElementById(`details-${contactId}`);
    details.classList.toggle('expanded');
}

function updateInviteButton() {
    const inviteBtn = document.getElementById('inviteSelectedBtn');
    inviteBtn.disabled = !selectedContactId;
}

function inviteSelectedContact() {
    if (!selectedContactId) return;

    const contact = Array.from(document.querySelectorAll('.contact-card'))
        .find(card => card.dataset.contactId == selectedContactId);
    
    if (!contact) return;

    const playerCode = contact.querySelector('.contact-code').textContent;
    const inviteBtn = document.getElementById('inviteSelectedBtn');
    inviteBtn.disabled = true;
    inviteBtn.textContent = t('Envoi...');

    fetch('{{ route("duo.invite") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ player_code: playerCode })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.redirect_url) {
            showToast(t('Invitation envoyée ! Redirection vers le salon...'), 'success');
            closeContactsModal();
            setTimeout(() => {
                window.location.href = data.redirect_url;
            }, 500);
        } else {
            showToast(data.message || t("Erreur lors de l'invitation"), 'error');
            inviteBtn.disabled = false;
            inviteBtn.textContent = t('INVITER LE JOUEUR SÉLECTIONNÉ');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast(t('Erreur de connexion'), 'error');
        inviteBtn.disabled = false;
        inviteBtn.textContent = t('INVITER LE JOUEUR SÉLECTIONNÉ');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('openContactsBtn').addEventListener('click', openContactsModal);
    document.getElementById('inviteSelectedBtn').addEventListener('click', inviteSelectedContact);
    
    document.getElementById('contactsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeContactsModal();
        }
    });

    document.getElementById('chatModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeChatModal();
        }
    });

    document.getElementById('chatInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
});

let currentChatContactId = null;
let currentChatContactName = '';
let unreadCountsPerContact = {};

function openChat(contactId, contactName) {
    currentChatContactId = contactId;
    currentChatContactName = contactName;
    document.getElementById('chatContactName').textContent = contactName;
    document.getElementById('chatModal').style.display = 'flex';
    document.getElementById('chatInput').value = '';
    loadConversation();
}

function closeChatModal() {
    document.getElementById('chatModal').style.display = 'none';
    currentChatContactId = null;
    currentChatContactName = '';
}

function loadConversation() {
    const messagesDiv = document.getElementById('chatMessages');
    messagesDiv.innerHTML = '<p class="chat-loading">' + t('Chargement...') + '</p>';

    fetch(`/chat/conversation/${currentChatContactId}`, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMessages(data.messages);
            unreadCountsPerContact[currentChatContactId] = 0;
            updateContactUnreadBadges();
        } else {
            messagesDiv.innerHTML = '<p class="chat-empty">' + t('Erreur de chargement') + '</p>';
        }
    })
    .catch(error => {
        console.error('Error loading conversation:', error);
        messagesDiv.innerHTML = '<p class="chat-empty">' + t('Erreur de connexion') + '</p>';
    });
}

function displayMessages(messages) {
    const messagesDiv = document.getElementById('chatMessages');
    
    if (messages.length === 0) {
        messagesDiv.innerHTML = '<p class="chat-empty">' + t('Aucun message. Commencez la conversation !') + '</p>';
        return;
    }

    messagesDiv.innerHTML = messages.map(msg => `
        <div class="chat-message ${msg.is_mine ? 'mine' : 'theirs'}">
            <div class="message-text">${escapeHtml(msg.message)}</div>
            <div class="message-time">${msg.time_ago}</div>
        </div>
    `).join('');

    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message || !currentChatContactId) return;
    
    input.disabled = true;

    fetch('/chat/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            receiver_id: currentChatContactId,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        input.disabled = false;
        if (data.success) {
            input.value = '';
            const messagesDiv = document.getElementById('chatMessages');
            const emptyMsg = messagesDiv.querySelector('.chat-empty');
            if (emptyMsg) emptyMsg.remove();
            
            messagesDiv.innerHTML += `
                <div class="chat-message mine">
                    <div class="message-text">${escapeHtml(data.message.message)}</div>
                    <div class="message-time">${data.message.time_ago}</div>
                </div>
            `;
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
            input.focus();
        } else {
            showToast(t("Erreur lors de l'envoi du message"), 'error');
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        input.disabled = false;
        showToast(t('Erreur de connexion'), 'error');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadUnreadCounts() {
    fetch('/chat/unread', {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            unreadCountsPerContact = data.per_contact || {};
            updateContactUnreadBadges();
        }
    })
    .catch(error => console.error('Error loading unread counts:', error));
}

function updateContactUnreadBadges() {
    document.querySelectorAll('.contact-chat-btn').forEach(btn => {
        const contactId = btn.dataset.contactId;
        const badge = btn.querySelector('.chat-badge');
        const unreadCount = unreadCountsPerContact[contactId] || 0;
        
        if (unreadCount > 0) {
            if (badge) {
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            } else {
                btn.innerHTML = '💬<span class="chat-badge">' + (unreadCount > 99 ? '99+' : unreadCount) + '</span>';
            }
        } else if (badge) {
            badge.remove();
        }
    });
}

setInterval(loadUnreadCounts, 10000);
setTimeout(loadUnreadCounts, 1000);
</script>

<!-- Firebase SDK pour synchronisation temps réel des contacts -->
<script type="module">
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { getAuth, signInAnonymously, onAuthStateChanged } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';
import { getFirestore, doc, onSnapshot } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js';

const firebaseConfig = {
    apiKey: "AIzaSyAB5-A0NsX9I9eFX76ZBYQQG_bqWp_dHw",
    authDomain: "strategybuzzergame.firebaseapp.com",
    projectId: "strategybuzzergame",
    storageBucket: "strategybuzzergame.appspot.com",
    messagingSenderId: "68047817391",
    appId: "1:68047817391:web:ba6b3bc148ef187bfeae9a"
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);

const currentUserId = {{ Auth::id() }};
let contactsUnsubscribe = null;
let matchUnsubscribe = null;
let lastVersion = null;
let firebaseInitialized = false;

onAuthStateChanged(auth, (user) => {
    if (user && !firebaseInitialized) {
        firebaseInitialized = true;
        console.log('[Firebase] Duo lobby authenticated');
        startContactsListener();
    }
});

signInAnonymously(auth).catch(e => console.error('[Firebase] Auth error:', e));

function startContactsListener() {
    if (contactsUnsubscribe) return;
    
    const contactDocRef = doc(db, 'duoContacts', `user-${currentUserId}`);
    
    contactsUnsubscribe = onSnapshot(contactDocRef, (docSnapshot) => {
        if (docSnapshot.exists()) {
            const data = docSnapshot.data();
            
            if (lastVersion && data.version !== lastVersion) {
                console.log('Nouveau contact détecté via Firestore:', data.lastContactName);
                
                const contactsModal = document.getElementById('contactsModal');
                if (contactsModal && contactsModal.style.display === 'flex') {
                    window.loadContacts();
                }
                
                if (window.showToast) {
                    window.showToast(`${data.lastContactName} ajouté à votre carnet !`, 'success');
                }
            }
            
            lastVersion = data.version;
        }
    }, (error) => {
        console.error('Firestore contacts listener error:', error);
    });
    
    console.log('Firestore contacts listener started for user', currentUserId);
}

function startMatchListener(matchId) {
    if (matchUnsubscribe) {
        matchUnsubscribe();
        matchUnsubscribe = null;
    }
    
    const matchDocRef = doc(db, 'duoMatches', `match-${matchId}`);
    
    matchUnsubscribe = onSnapshot(matchDocRef, (docSnapshot) => {
        if (docSnapshot.exists()) {
            const data = docSnapshot.data();
            console.log('Match update received:', data);
            
            if (data.status === 'lobby' && data.lobby_code) {
                console.log('Invitation accepted! Redirecting to lobby:', data.lobby_code);
                if (window.showToast) {
                    window.showToast('Invitation acceptée ! Redirection vers le salon...', 'success');
                }
                setTimeout(() => {
                    window.location.href = '/lobby/' + data.lobby_code;
                }, 500);
            }
        }
    }, (error) => {
        console.error('Firestore match listener error:', error);
    });
    
    console.log('Firestore match listener started for match', matchId);
}

window.startMatchListener = startMatchListener;

function stopContactsListener() {
    if (contactsUnsubscribe) {
        contactsUnsubscribe();
        contactsUnsubscribe = null;
        console.log('Firestore contacts listener stopped');
    }
}

function stopMatchListener() {
    if (matchUnsubscribe) {
        matchUnsubscribe();
        matchUnsubscribe = null;
        console.log('Firestore match listener stopped');
    }
}

startContactsListener();

window.addEventListener('beforeunload', () => {
    stopContactsListener();
    stopMatchListener();
});
</script>
@endsection
