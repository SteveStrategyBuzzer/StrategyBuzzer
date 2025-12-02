@extends('layouts.app')

@section('content')
<div class="duo-lobby-container">
    <div class="duo-header">
        <button onclick="window.location.href='{{ route('menu') }}'" class="back-button">
            {{ __('Retour') }}
        </button>
        <h1>{{ __('MODE DUO') }}</h1>
        <div class="header-avatar">
            @if(Auth::user()->avatar_url)
                <img src="/{{ Auth::user()->avatar_url }}" alt="Avatar">
            @else
                <div class="default-avatar">{{ substr(Auth::user()->name, 0, 1) }}</div>
            @endif
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
        <button id="inviteSelectedBtn" class="btn-invite-selected" disabled>
            {{ __('INVITER LE JOUEUR SÉLECTIONNÉ') }}
        </button>
        <div id="contactsList" class="contacts-list">
            <p class="loading-contacts">{{ __('Chargement...') }}</p>
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
    'Manches Décisives' => __('Manches Décisives')
];
@endphp
<script>
const duoTranslations = @json($duoTranslations);
function t(key) { return duoTranslations[key] || key; }

document.addEventListener('DOMContentLoaded', function() {
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
                if (data.success) {
                    showToast(t('Invitation envoyée !'), 'success');
                    inviteInput.value = '';
                } else {
                    showToast(data.message || t("Erreur lors de l'invitation"), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast(t('Erreur de connexion'), 'error');
            });
        });
    }

    // Vérifier les invitations reçues
    function checkInvitations() {
        fetch('{{ route("duo.invitations") }}')
            .then(response => response.json())
            .then(data => {
                if (data.invitations && data.invitations.length > 0) {
                    displayInvitations(data.invitations);
                }
            });
    }

    function displayInvitations(invitations) {
        const list = document.getElementById('invitationsList');
        
        if (invitations.length === 0) {
            list.innerHTML = '<p class="no-invitations">' + t('Aucune invitation pour le moment - Les invitations apparaîtront ici automatiquement') + '</p>';
        } else {
            list.innerHTML = invitations.map(inv => `
                <div class="invitation-item">
                    <span>${inv.from_player.name} ${t('vous invite')}</span>
                    <button onclick="acceptInvitation(${inv.match_id})" class="btn-accept">${t('Accepter')}</button>
                    <button onclick="declineInvitation(${inv.match_id})" class="btn-decline">${t('Refuser')}</button>
                </div>
            `).join('');
        }
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
            window.location.href = '/duo/game/' + matchId;
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
    // TODO: Implémenter le refus d'invitation
    location.reload();
}

let selectedContactId = null;

function openContactsModal() {
    document.getElementById('contactsModal').style.display = 'flex';
    loadContacts();
}

function closeContactsModal() {
    document.getElementById('contactsModal').style.display = 'none';
    selectedContactId = null;
    updateInviteButton();
}

function loadContacts() {
    const contactsList = document.getElementById('contactsList');
    contactsList.innerHTML = '<p class="loading-contacts">' + t('Chargement...') + '</p>';

    fetch('/api/duo/contacts', {
        headers: {
            'Authorization': 'Bearer ' + document.querySelector('meta[name="auth-token"]')?.content,
            'Accept': 'application/json'
        }
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
    
    contactsList.innerHTML = contacts.map(contact => `
        <div class="contact-card" data-contact-id="${contact.id}">
            <div class="contact-header" onclick="toggleContactSelection(${contact.id})">
                <div class="contact-checkbox" id="checkbox-${contact.id}">☐</div>
                <div class="contact-info">
                    <div class="contact-name-code">
                        <span class="contact-name">${contact.name}</span>
                        <span class="contact-code">${contact.player_code}</span>
                    </div>
                    <div class="contact-stats">
                        ⭐ ${t('Niveau')} ${contact.level} | 🏆 ${contact.division} (#${contact.division_rank})
                    </div>
                </div>
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
        if (data.success) {
            showToast(t('Invitation envoyée !'), 'success');
            closeContactsModal();
        } else {
            showToast(data.message || t("Erreur lors de l'invitation"), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast(t('Erreur de connexion'), 'error');
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
});
</script>
@endsection
