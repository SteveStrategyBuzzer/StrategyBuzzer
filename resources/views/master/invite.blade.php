@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.invite-container {
    max-width: 700px;
    margin: 0 auto;
    padding: 1rem;
}

.invite-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    text-align: center;
    color: #FFD700;
}

.invite-subtitle {
    font-size: 1rem;
    text-align: center;
    opacity: 0.8;
    margin-bottom: 2rem;
}

.game-code-card {
    background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(255, 165, 0, 0.2));
    border: 3px solid #FFD700;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    text-align: center;
}

.game-code-label {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}

.game-code {
    font-size: 2.5rem;
    font-weight: 900;
    letter-spacing: 0.5rem;
    color: #FFD700;
    font-family: 'Courier New', monospace;
    margin-bottom: 1rem;
}

.copy-btn {
    background: #FFD700;
    color: #003DA5;
    border: none;
    padding: 0.6rem 1.5rem;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.95rem;
}

.copy-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
}

.copy-btn.copied {
    background: #00D400;
    color: white;
}

.join-link-section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 2rem;
}

.join-link-label {
    font-size: 0.9rem;
    opacity: 0.8;
    margin-bottom: 0.5rem;
}

.join-link-input {
    width: 100%;
    padding: 0.8rem;
    border-radius: 8px;
    border: 2px solid rgba(255, 215, 0, 0.3);
    background: rgba(255, 255, 255, 0.9);
    color: #003DA5;
    font-size: 0.85rem;
    text-overflow: ellipsis;
}

.contacts-section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.contacts-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #FFD700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.contacts-list {
    max-height: 400px;
    overflow-y: auto;
}

.contact-item {
    display: flex;
    align-items: center;
    padding: 0.8rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.contact-item:hover {
    background: rgba(255, 255, 255, 0.1);
}

.contact-item.selected {
    background: rgba(255, 215, 0, 0.2);
    border: 2px solid #FFD700;
}

.contact-item.invited {
    opacity: 0.6;
    background: rgba(0, 212, 0, 0.1);
    border: 2px solid #00D400;
    cursor: default;
}

.contact-checkbox {
    margin-right: 1rem;
    width: 22px;
    height: 22px;
    accent-color: #FFD700;
    cursor: pointer;
}

.contact-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 1rem;
    border: 2px solid rgba(255, 215, 0, 0.5);
    background: rgba(255, 255, 255, 0.2);
}

.contact-avatar-placeholder {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    margin-right: 1rem;
    border: 2px solid rgba(255, 215, 0, 0.5);
    background: rgba(255, 255, 255, 0.2);
}

.contact-info {
    flex: 1;
}

.contact-name {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.2rem;
}

.contact-stats {
    font-size: 0.8rem;
    opacity: 0.7;
}

.contact-status {
    font-size: 0.75rem;
    padding: 0.3rem 0.6rem;
    border-radius: 12px;
    font-weight: 600;
}

.status-invited {
    background: rgba(0, 212, 0, 0.2);
    color: #00D400;
}

.no-contacts {
    text-align: center;
    padding: 2rem;
    opacity: 0.7;
}

.no-contacts-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}

.btn-invite {
    background: linear-gradient(135deg, #00D400, #00A000);
    color: white;
    padding: 1rem 3rem;
    border-radius: 12px;
    font-size: 1.2rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: block;
    width: 100%;
    text-align: center;
}

.btn-invite:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 212, 0, 0.4);
}

.btn-invite:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.btn-lobby {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 1rem 2rem;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: block;
    width: 100%;
    text-align: center;
    text-decoration: none;
    margin-top: 1rem;
}

.btn-lobby:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.4);
}

.header-back {
    position: absolute;
    top: 20px;
    left: 20px;
    background: white;
    color: #003DA5;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.header-back:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
}

.selection-count {
    background: rgba(255, 215, 0, 0.2);
    border-radius: 8px;
    padding: 0.5rem 1rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.selection-count-text {
    font-weight: 600;
}

.select-all-btn {
    background: transparent;
    border: 1px solid #FFD700;
    color: #FFD700;
    padding: 0.3rem 0.8rem;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.select-all-btn:hover {
    background: rgba(255, 215, 0, 0.2);
}

.alert-success {
    background: rgba(0, 212, 0, 0.2);
    border: 2px solid #00D400;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    text-align: center;
    color: #00D400;
    font-weight: 600;
}

@media (max-width: 768px) {
    .header-back {
        top: 10px;
        left: 10px;
        padding: 6px 12px;
        font-size: 0.9rem;
    }
    
    .invite-title {
        font-size: 1.5rem;
        margin-top: 2rem;
    }
    
    .game-code {
        font-size: 2rem;
        letter-spacing: 0.3rem;
    }
    
    .contact-item {
        padding: 0.6rem;
    }
    
    .contact-avatar,
    .contact-avatar-placeholder {
        width: 38px;
        height: 38px;
    }
}
</style>

<a href="{{ route('master.structure', $game->id) }}" class="header-back">← {{ __('Retour') }}</a>

<div class="invite-container">
    <h1 class="invite-title">📋 {{ __('Carnet de Groupe') }}</h1>
    <p class="invite-subtitle">{{ __('Invitez vos contacts à rejoindre la partie') }}</p>
    
    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    
    <div class="game-code-card">
        <div class="game-code-label">{{ __('Code de la partie') }}</div>
        <div class="game-code" id="gameCode">{{ $game->game_code }}</div>
        <button type="button" class="copy-btn" id="copyCodeBtn" onclick="copyGameCode()">
            📋 {{ __('Copier le code') }}
        </button>
    </div>
    
    <div class="join-link-section">
        <div class="join-link-label">{{ __('Lien d\'invitation') }}</div>
        <input type="text" class="join-link-input" id="joinLink" value="{{ route('master.join.form') }}" readonly onclick="this.select()">
        <p style="font-size: 0.8rem; opacity: 0.7; margin-top: 0.5rem;">{{ __('Les joueurs devront entrer le code') }}: <strong>{{ $game->game_code }}</strong></p>
    </div>
    
    <form action="{{ route('master.invite.send', $game->id) }}" method="POST" id="inviteForm">
        @csrf
        
        <div class="contacts-section">
            <div class="contacts-title">
                📇 {{ __('Mes contacts') }}
            </div>
            
            @if($contacts->count() > 0)
                <div class="selection-count">
                    <span class="selection-count-text" id="selectionCount">{{ __('0 contact(s) sélectionné(s)') }}</span>
                    <button type="button" class="select-all-btn" onclick="toggleSelectAll()">{{ __('Tout sélectionner') }}</button>
                </div>
                
                <div class="contacts-list">
                    @foreach($contacts as $contact)
                        @php
                            $contactUser = $contact->contact;
                            $isInvited = in_array($contactUser->id, $invitedUserIds);
                        @endphp
                        <label class="contact-item {{ $isInvited ? 'invited' : '' }}" data-contact-id="{{ $contactUser->id }}">
                            @if(!$isInvited)
                                <input type="checkbox" name="contact_ids[]" value="{{ $contactUser->id }}" class="contact-checkbox" onchange="updateSelectionCount()">
                            @endif
                            
                            @if($contactUser->avatar_url)
                                <img src="{{ $contactUser->avatar_url }}" alt="{{ __('Avatar') }}" class="contact-avatar">
                            @else
                                <div class="contact-avatar-placeholder">👤</div>
                            @endif
                            
                            <div class="contact-info">
                                <div class="contact-name">{{ $contactUser->display_name }}</div>
                                <div class="contact-stats">
                                    {{ __('Parties jouées ensemble') }}: {{ $contact->matches_played_together ?? 0 }}
                                </div>
                            </div>
                            
                            @if($isInvited)
                                <span class="contact-status status-invited">✓ {{ __('Invité') }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                
                <button type="submit" class="btn-invite" id="inviteBtn" disabled>
                    ✉️ {{ __('Envoyer les invitations') }}
                </button>
            @else
                <div class="no-contacts">
                    <div class="no-contacts-icon">📭</div>
                    <p>{{ __('Aucun contact disponible') }}</p>
                    <p style="font-size: 0.9rem; opacity: 0.7;">{{ __('Partagez le code pour inviter des joueurs') }}</p>
                </div>
            @endif
        </div>
    </form>
    
    <a href="{{ route('master.lobby', $game->id) }}" class="btn-lobby">
        🚀 {{ __('Aller au lobby') }}
    </a>
</div>

<script>
function copyGameCode() {
    const code = document.getElementById('gameCode').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.getElementById('copyCodeBtn');
        btn.textContent = '✓ {{ __('Copié !') }}';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.textContent = '📋 {{ __('Copier le code') }}';
            btn.classList.remove('copied');
        }, 2000);
    });
}

function updateSelectionCount() {
    const checkboxes = document.querySelectorAll('.contact-checkbox:checked');
    const count = checkboxes.length;
    document.getElementById('selectionCount').textContent = count + ' {{ __('contact(s) sélectionné(s)') }}';
    document.getElementById('inviteBtn').disabled = count === 0;
}

function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.contact-checkbox');
    const allChecked = [...checkboxes].every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
    updateSelectionCount();
}

document.addEventListener('DOMContentLoaded', function() {
    updateSelectionCount();
});
</script>
@endsection
