@extends('layouts.app')

@section('content')
<div class="league-lobby-container">
    <div class="league-header">
        <button onclick="window.location.href='{{ route('menu') }}'" class="back-button">
            ← Retour
        </button>
        <h1>GESTION D'ÉQUIPE</h1>
    </div>

    <div class="team-management-content">
        @if(!$team)
            <div class="menu-cards-grid">
                <a href="{{ route('league.team.search') }}" class="menu-action-card">
                    <div class="menu-card-icon">🔍</div>
                    <h3>{{ __('Chercher Équipe') }}</h3>
                    <p>{{ __('Trouvez une équipe qui recrute et rejoignez-la') }}</p>
                </a>
                <div class="menu-action-card" onclick="toggleCreateForm()">
                    <div class="menu-card-icon">➕</div>
                    <h3>{{ __('Créer une équipe') }}</h3>
                    <p>{{ __('Formez votre propre équipe et invitez des joueurs') }}</p>
                </div>
            </div>

            <div class="create-team-section" id="createTeamSection" style="display: none;">
                <h2>🛡️ {{ __('Créer une Équipe') }}</h2>
                <p>{{ __('Formez une équipe de 5 joueurs pour participer à la Ligue par Équipe') }}</p>
                
                <div class="create-team-form">
                    <div class="form-group">
                        <label>{{ __('Nom de l\'équipe') }} <span class="char-limit">({{ __('max 10 caractères') }})</span></label>
                        <input type="text" id="teamName" placeholder="{{ __('ex: CHAMPIONS') }}" maxlength="10">
                        <div class="char-counter"><span id="nameCharCount">0</span>/10</div>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ __('Emblème de l\'équipe') }}</label>
                        
                        <div class="emblem-selector">
                            <div class="emblem-preview" id="emblemPreview">
                                <div class="emblem-placeholder">🛡️</div>
                            </div>
                            
                            <div class="emblem-tabs">
                                <button type="button" class="emblem-tab active" data-tab="categories">{{ __('Choisir') }}</button>
                                <button type="button" class="emblem-tab" data-tab="upload">{{ __('Importer') }}</button>
                            </div>
                            
                            <div class="emblem-tab-content" id="categoriesTab">
                                <div class="emblem-categories">
                                    @php
                                    $categories = [
                                        'animals' => ['name' => 'Animaux', 'icon' => '🦁'],
                                        'warriors' => ['name' => 'Guerriers', 'icon' => '⚔️'],
                                        'sports' => ['name' => 'Sport', 'icon' => '🏆'],
                                        'symbols' => ['name' => 'Symboles', 'icon' => '🌟'],
                                        'elements' => ['name' => 'Éléments', 'icon' => '🔥'],
                                        'gaming' => ['name' => 'Gaming', 'icon' => '🎮'],
                                        'royalty' => ['name' => 'Royauté', 'icon' => '👑'],
                                        'flags' => ['name' => 'Drapeaux', 'icon' => '🌍'],
                                        'masks' => ['name' => 'Masques', 'icon' => '🎭'],
                                        'gems' => ['name' => 'Gemmes', 'icon' => '💎'],
                                    ];
                                    @endphp
                                    @foreach($categories as $key => $cat)
                                        <button type="button" class="category-btn" data-category="{{ $key }}">
                                            <span class="cat-icon">{{ $cat['icon'] }}</span>
                                            <span class="cat-name">{{ __($cat['name']) }}</span>
                                        </button>
                                    @endforeach
                                </div>
                                
                                <div class="emblem-grid" id="emblemGrid" style="display: none;">
                                    <button type="button" class="back-to-categories" id="backToCategories">← {{ __('Retour') }}</button>
                                    <div class="emblems-container" id="emblemsContainer"></div>
                                </div>
                            </div>
                            
                            <div class="emblem-tab-content" id="uploadTab" style="display: none;">
                                <div class="upload-zone" id="uploadZone">
                                    <input type="file" id="emblemUpload" accept="image/png,image/jpeg,image/gif,image/webp" style="display: none;">
                                    <div class="upload-placeholder">
                                        <span class="upload-icon">📁</span>
                                        <p>{{ __('Cliquez ou déposez une image') }}</p>
                                        <small>PNG, JPG, GIF, WEBP (max 2MB)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" id="emblemCategory" value="animals">
                        <input type="hidden" id="emblemIndex" value="1">
                        <input type="hidden" id="customEmblem" value="">
                    </div>
                    
                    <button id="createTeamBtn" class="btn-primary btn-large">
                        <span class="btn-icon">⚔️</span>
                        {{ __('CRÉER L\'ÉQUIPE') }}
                    </button>
                </div>
                <div id="createError" class="error-message" style="display: none;"></div>
            </div>

            @if($pendingInvitations->isNotEmpty())
                <div class="invitations-section">
                    <h3>📨 Invitations Reçues</h3>
                    @foreach($pendingInvitations as $invitation)
                        <div class="invitation-card">
                            <div class="invitation-info">
                                <p class="team-name">{{ $invitation->team->name }} [{{ $invitation->team->tag }}]</p>
                                <p class="captain-name">Capitaine: {{ $invitation->team->captain->name }}</p>
                            </div>
                            <div class="invitation-actions">
                                <button onclick="acceptInvitation({{ $invitation->id }})" class="btn-accept">✓ Accepter</button>
                                <button onclick="declineInvitation({{ $invitation->id }})" class="btn-decline">✗ Refuser</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="team-info-section">
                <div class="team-header-card">
                    <div class="team-header-row">
                        <div class="team-emblem">
                            @if($team->custom_emblem_path)
                                <img src="{{ asset('storage/' . $team->custom_emblem_path) }}" alt="Emblem">
                            @else
                                @php
                                    $emblems = [
                                        'animals' => ['🦁', '🐯', '🐻', '🦊', '🐺', '🦅', '🦈', '🐍', '🦎', '🐊', '🦂', '🦀', '🐙', '🦑', '🐋', '🐬', '🦭', '🐘', '🦏', '🦛', '🐪', '🦒', '🦘', '🦬', '🐃', '🦌', '🦙', '🐎', '🦓', '🐗', '🐺', '🦇', '🐀', '🐉', '🦎', '🦖', '🦕', '🐢', '🐸', '🐊', '🦜', '🦩', '🦚', '🦢', '🦤', '🕊️', '🐝', '🦋', '🐞', '🦗'],
                                        'warriors' => ['⚔️', '🗡️', '🛡️', '🏹', '🪓', '🔱', '⚒️', '🪃', '💣', '🧨', '💥', '🎯', '🥷', '👺', '👹', '💀', '☠️', '👻', '🤖', '👾', '🦾', '🦿', '🧠', '👁️', '🫀', '🪖', '🎖️', '🏅', '🥇', '⭐', '🌟', '✨', '💫', '🔥', '❄️', '⚡', '💨', '🌪️', '🌊', '🌋', '☄️', '🌙', '☀️', '🌈', '🎭', '👑', '💎', '🔮', '🧿', '⚱️'],
                                        'sports' => ['🏆', '🥇', '🥈', '🥉', '🏅', '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸', '🥊', '🥋', '⛳', '⛸️', '🎿', '🛷', '🏂', '🏋️', '🤸', '🚴', '🏊', '🤽', '🚣', '🧗', '🤺', '🏄', '🎳', '♟️', '🎯', '🏹', '🥏', '🪀', '🛹', '🛼', '⛹️', '🤾', '🤿', '🪂', '🏇', '🚵', '🧘', '🎽', '🥅', '🪁', '🎣'],
                                        'symbols' => ['🌟', '⭐', '✨', '💫', '🔥', '💧', '💎', '❤️', '💜', '💙', '💚', '💛', '🧡', '🖤', '🤍', '❤️‍🔥', '💝', '💖', '💗', '💓', '💕', '💞', '💘', '💌', '🎀', '🎁', '🎊', '🎉', '🎈', '🎆', '🎇', '✳️', '❇️', '💠', '🔷', '🔶', '🔹', '🔸', '🟠', '🟡', '🟢', '🔵', '🟣', '🟤', '⚫', '⚪', '🔴', '🟥', '🟧', '🟨'],
                                        'elements' => ['🔥', '💧', '🌊', '💨', '🌪️', '⚡', '❄️', '☃️', '🌙', '☀️', '🌈', '⭐', '🌟', '✨', '💫', '☄️', '🌋', '🏔️', '⛰️', '🌍', '🌎', '🌏', '🪐', '💎', '🔮', '🧊', '🌡️', '🌀', '🌁', '🌫️', '🌤️', '⛅', '🌥️', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️', '☔', '💦', '💥', '🏝️', '🏜️', '🌵', '🌴', '🌲', '🌳', '🌾', '🍀', '🍁'],
                                        'gaming' => ['🎮', '🕹️', '👾', '🤖', '🎯', '🎲', '♟️', '🃏', '🀄', '🎰', '🎪', '🎭', '🎬', '🎥', '📺', '📱', '💻', '🖥️', '⌨️', '🖱️', '💾', '💿', '📀', '🔌', '🔋', '💡', '🔦', '🏮', '📡', '🛸', '🚀', '🛰️', '✈️', '🚁', '🎪', '🎢', '🎡', '🎠', '⚙️', '🔧', '🔩', '⛓️', '🔗', '📌', '📍', '🗺️', '🧭', '🎴', '🎨', '🖼️'],
                                        'royalty' => ['👑', '💎', '💍', '🏰', '🏯', '👸', '🤴', '🦁', '🦅', '🐉', '🗡️', '⚔️', '🛡️', '🔱', '⚜️', '🎖️', '🏅', '🥇', '🏆', '✨', '🌟', '⭐', '💫', '👼', '😇', '🙏', '🪔', '🕯️', '🔮', '🧿', '📿', '📜', '🖋️', '✒️', '🪶', '📖', '📚', '🎓', '🧙', '🧝', '🧚', '🧞', '🧜', '🧛', '🦸', '🦹', '🥷', '🤺', '♔', '♕'],
                                        'flags' => ['🏴', '🏳️', '🚩', '🎌', '🏁', '🇫🇷', '🇬🇧', '🇺🇸', '🇩🇪', '🇮🇹', '🇪🇸', '🇵🇹', '🇧🇷', '🇯🇵', '🇰🇷', '🇨🇳', '🇮🇳', '🇷🇺', '🇦🇺', '🇨🇦', '🇲🇽', '🇦🇷', '🇨🇱', '🇨🇴', '🇵🇪', '🇻🇪', '🇪🇨', '🇧🇴', '🇵🇾', '🇺🇾', '🇳🇱', '🇧🇪', '🇨🇭', '🇦🇹', '🇵🇱', '🇬🇷', '🇹🇷', '🇪🇬', '🇿🇦', '🇳🇬', '🇰🇪', '🇲🇦', '🇹🇳', '🇩🇿', '🇸🇦', '🇦🇪', '🇮🇱', '🇮🇪', '🇸🇪', '🇳🇴'],
                                        'masks' => ['🎭', '👺', '👹', '👻', '💀', '☠️', '👽', '👾', '🤖', '🤡', '😈', '👿', '🙀', '😱', '😰', '🥶', '🥵', '🤯', '😎', '🥸', '🤓', '🧐', '🤠', '😷', '🤒', '🤕', '🤑', '🤥', '🤫', '🤭', '🥳', '🥴', '😵', '🤐', '😮', '😯', '😲', '😳', '🤪', '😜', '😝', '😛', '🤑', '😏', '😒', '🙄', '😬', '😮‍💨', '🥱', '😴'],
                                        'gems' => ['💎', '💍', '👑', '🔮', '🧿', '📿', '💠', '🔷', '🔶', '🔹', '🔸', '❄️', '💧', '🩵', '🩷', '🩶', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💝', '💖', '💗', '💓', '💕', '⭐', '🌟', '✨', '💫', '🪙', '💰', '💳', '🏆', '🎖️', '🏅', '🥇', '🥈', '🥉', '✳️', '❇️', '🔆', '🔅', '💡', '🌸', '🌺'],
                                    ];
                                    $category = $team->emblem_category ?? 'animals';
                                    $index = ($team->emblem_index ?? 1) - 1;
                                    $emoji = $emblems[$category][$index] ?? '🛡️';
                                @endphp
                                {{ $emoji }}
                            @endif
                        </div>
                        <h2 class="team-name-title">{{ $team->name }}</h2>
                        <div class="team-stats-inline">
                            {{ $team->matches_won }}V - {{ $team->matches_lost }}D
                        </div>
                    </div>
                    <div class="team-division {{ $team->division }}">
                        {{ ucfirst($team->division) }} - {{ $team->points }} pts
                    </div>
                    <div class="team-code-display">
                        🏷️ {{ __('Code') }}: <span class="code-value" onclick="copyTeamCode('{{ $team->team_code }}')">{{ $team->team_code }}</span>
                        <span class="copy-hint">{{ __('(cliquer pour copier)') }}</span>
                    </div>
                </div>

                <div class="team-members-section">
                    <h3>👥 {{ __('Membres') }} ({{ $team->members->count() }}/5)</h3>
                    <div class="members-list">
                        @foreach($team->members as $member)
                            <div class="member-card" onclick="window.location.href='{{ route('league.team.details', $team->id) }}'">
                                <div class="member-info">
                                    <div class="member-avatar">
                                        @if($member->avatar_url ?? null)
                                            <img src="{{ $member->avatar_url }}" alt="Avatar">
                                        @else
                                            <div class="default-avatar">{{ strtoupper(substr($member->name, 0, 1)) }}</div>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="member-name">{{ $member->name }}</p>
                                        <p class="member-role">
                                            @if($team->captain_id === $member->id)
                                                👑 {{ __('Capitaine') }}
                                            @else
                                                {{ __('Membre') }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                @if($team->captain_id === Auth::id() && $member->id !== Auth::id())
                                    <div class="member-actions">
                                        <button onclick="event.stopPropagation(); transferCaptain({{ $member->id }}, '{{ addslashes($member->name) }}')" class="btn-captain" title="{{ __('Nommer capitaine') }}">
                                            👑
                                        </button>
                                        <button onclick="event.stopPropagation(); kickMember({{ $member->id }})" class="btn-kick">
                                            {{ __('Expulser') }}
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                
                @if($team->captain_id === Auth::id())
                <div class="captain-actions">
                    <button type="button" class="btn-recruit {{ $team->is_recruiting ? 'active' : '' }}" onclick="toggleRecruiting()">
                        🔍 {{ __('Cherche membre') }}
                        <span class="recruit-status">{{ $team->is_recruiting ? __('Activé') : __('Désactivé') }}</span>
                    </button>
                    <a href="{{ route('league.team.captain', $selectedTeamId ?? $team->id) }}" class="btn-captain">
                        ⚙️ {{ __('Gérer les demandes d\'accès') }}
                        @if($pendingRequestsCount > 0)
                            <span class="request-badge">{{ $pendingRequestsCount }}</span>
                        @endif
                    </a>
                </div>
                @endif

                @if($team->captain_id === Auth::id() && $team->members->count() < 5)
                    <div class="invite-section">
                        <h3>📩 {{ __('Inviter un Joueur') }}</h3>
                        <div class="invite-form">
                            <input type="text" id="playerName" placeholder="{{ __('Nom du joueur') }}">
                            <button id="inviteBtn" class="btn-primary">{{ __('Inviter') }}</button>
                        </div>
                        <div id="inviteError" class="error-message" style="display: none;"></div>
                        <div id="inviteSuccess" class="success-message" style="display: none;"></div>
                        
                        <div class="carnet-section">
                            <button type="button" class="btn-carnet" onclick="toggleCarnet()">
                                📖 {{ __('Carnet de contacts') }}
                            </button>
                            <div id="carnetModal" class="carnet-modal" style="display: none;">
                                <div class="carnet-header">
                                    <h4>📖 {{ __('Sélectionner un contact') }}</h4>
                                    <button type="button" class="close-carnet" onclick="toggleCarnet()">×</button>
                                </div>
                                <div class="carnet-list" id="carnetList">
                                    <p class="loading">{{ __('Chargement...') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="team-actions">
                    @if($team->members->count() >= 5)
                        <button onclick="window.location.href='{{ route('league.team.lobby', $selectedTeamId ?? $team->id) }}'" class="btn-primary btn-large">
                            <span class="btn-icon">🎮</span>
                            {{ __('ALLER AU LOBBY') }}
                        </button>
                    @else
                        <p class="info-message">⚠️ {{ __('Votre équipe doit avoir 5 joueurs pour participer aux matchs') }}</p>
                    @endif
                    
                    <button onclick="leaveTeam()" class="btn-danger">
                        {{ $team->captain_id === Auth::id() && $team->members->count() > 1 ? __('Quitter & Transférer Capitanat') : __('Quitter l\'Équipe') }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

<style>
.team-management-content {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.menu-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.menu-action-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: white;
    display: block;
}

.menu-action-card:hover {
    transform: translateY(-5px);
    border-color: #00d4ff;
    box-shadow: 0 8px 24px rgba(0, 212, 255, 0.2);
}

.menu-card-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.menu-action-card h3 {
    color: #00d4ff;
    margin-bottom: 0.5rem;
    font-size: 1.3rem;
}

.menu-action-card p {
    color: #aaa;
    font-size: 0.95rem;
}

.captain-actions {
    margin: 1.5rem 0;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.btn-recruit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #444 0%, #333 100%);
    color: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    border: 2px solid #555;
    cursor: pointer;
    transition: all 0.3s ease;
}
.btn-recruit.active {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    border-color: #28a745;
}
.btn-recruit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
.btn-recruit .recruit-status {
    font-size: 0.8rem;
    padding: 2px 8px;
    border-radius: 10px;
    background: rgba(255,255,255,0.2);
}

.btn-captain {
    display: inline-block;
    background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
    color: #1a1a2e;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.3s ease;
}

.btn-captain:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
}

.btn-captain {
    position: relative;
}

.request-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ff4444;
    color: #fff;
    font-size: 0.75rem;
    font-weight: bold;
    padding: 4px 8px;
    border-radius: 12px;
    min-width: 20px;
    text-align: center;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.carnet-section {
    margin-top: 20px;
}

.btn-carnet {
    width: 100%;
    padding: 12px 20px;
    background: linear-gradient(135deg, #6b5b95 0%, #4a4063 100%);
    border: 2px solid #8b7bb5;
    border-radius: 10px;
    color: #fff;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-carnet:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(107, 91, 149, 0.4);
}

.carnet-modal {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #6b5b95;
    border-radius: 15px;
    margin-top: 15px;
    max-height: 400px;
    overflow: hidden;
}

.carnet-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #0f3460;
}

.carnet-header h4 {
    color: #00d4ff;
    margin: 0;
}

.close-carnet {
    background: none;
    border: none;
    color: #888;
    font-size: 1.5rem;
    cursor: pointer;
}

.close-carnet:hover {
    color: #fff;
}

.carnet-list {
    max-height: 350px;
    overflow-y: auto;
    padding: 10px;
}

.carnet-list .loading {
    text-align: center;
    color: #888;
    padding: 20px;
}

.contact-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #0a0a15;
    border: 1px solid #0f3460;
    border-radius: 10px;
    padding: 12px 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.contact-card:hover {
    border-color: #00d4ff;
    background: #16213e;
}

.contact-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.contact-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%);
    border: 2px solid #00d4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #00d4ff;
    overflow: hidden;
}

.contact-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.contact-details .contact-name {
    color: #fff;
    font-weight: bold;
    margin: 0;
}

.contact-details .contact-code {
    color: #888;
    font-size: 0.85rem;
    margin: 2px 0 0 0;
}

.contact-stats {
    display: flex;
    gap: 15px;
    color: #aaa;
    font-size: 0.85rem;
}

.no-contacts {
    text-align: center;
    color: #888;
    padding: 30px;
}

.create-team-section, .team-info-section {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 20px;
}

.create-team-section h2 {
    color: #00d4ff;
    margin-bottom: 10px;
}

.create-team-form {
    margin-top: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    color: #fff;
    margin-bottom: 8px;
    font-weight: 600;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #0f3460;
    border-radius: 8px;
    background: #16213e;
    color: #fff;
    font-size: 16px;
}

.form-group input:focus {
    outline: none;
    border-color: #00d4ff;
}

.team-header-card {
    text-align: center;
    margin-bottom: 20px;
    padding: 15px;
    background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%);
    border-radius: 10px;
}

.team-header-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.team-name-title {
    color: #00d4ff;
    margin: 0;
    font-size: 1.3rem;
}

.team-stats-inline {
    color: #aaa;
    font-size: 0.9rem;
    white-space: nowrap;
}

.team-tag {
    color: #ffd700;
    font-weight: bold;
}

.team-division {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    margin-top: 10px;
    font-weight: bold;
    font-size: 0.85rem;
}

.team-division.bronze { background: linear-gradient(135deg, #CD7F32, #8B4513); }
.team-division.argent { background: linear-gradient(135deg, #C0C0C0, #808080); }
.team-division.or { background: linear-gradient(135deg, #FFD700, #FFA500); }
.team-division.platine { background: linear-gradient(135deg, #E5E4E2, #B0B0B0); }
.team-division.diamant { background: linear-gradient(135deg, #B9F2FF, #00CED1); }
.team-division.legende { background: linear-gradient(135deg, #FF00FF, #8B008B); }

.team-code-display {
    margin-top: 12px;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.9);
}
.team-code-display .code-value {
    background: rgba(255,255,255,0.2);
    padding: 4px 10px;
    border-radius: 6px;
    font-family: monospace;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.2s ease;
}
.team-code-display .code-value:hover {
    background: rgba(255,255,255,0.35);
}
.team-code-display .copy-hint {
    font-size: 0.75rem;
    opacity: 0.7;
    margin-left: 5px;
}

.team-members-section {
    margin: 30px 0;
}

.team-members-section h3 {
    color: #00d4ff;
    margin-bottom: 15px;
}

.members-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.member-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #1a1a2e;
    border: 1px solid #0f3460;
    border-radius: 10px;
}

.member-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.member-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
}

.member-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.default-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00d4ff, #0f3460);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    color: #fff;
}

.member-name {
    font-weight: bold;
    color: #fff;
    margin: 0;
}

.member-role {
    color: #aaa;
    font-size: 14px;
    margin: 5px 0 0 0;
}

.member-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.btn-captain {
    padding: 8px 12px;
    background: #28a745;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s;
    font-size: 16px;
}

.btn-captain:hover {
    background: #218838;
}

.btn-kick {
    padding: 8px 16px;
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-kick:hover {
    background: #c82333;
}

.invite-section {
    margin: 20px 0;
}

.invite-section h3 {
    color: #00d4ff;
    margin-bottom: 15px;
}

.invite-form {
    display: flex;
    gap: 10px;
    width: 100%;
    box-sizing: border-box;
}

.invite-form input {
    flex: 1;
    min-width: 0;
    padding: 12px;
    border: 2px solid #0f3460;
    border-radius: 8px;
    background: #16213e;
    color: #fff;
    box-sizing: border-box;
}

.invite-form button {
    flex-shrink: 0;
    white-space: nowrap;
}

.team-actions {
    margin-top: 30px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    align-items: center;
}

.btn-danger {
    padding: 12px 24px;
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-danger:hover {
    background: #c82333;
}

.info-message {
    color: #ffd700;
    text-align: center;
    padding: 15px;
    background: rgba(255, 215, 0, 0.1);
    border-radius: 8px;
}

.invitations-section {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #0f3460;
    border-radius: 15px;
    padding: 20px;
    margin-top: 20px;
}

.invitations-section h3 {
    color: #00d4ff;
    margin-bottom: 15px;
}

.invitation-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #1a1a2e;
    border: 1px solid #0f3460;
    border-radius: 10px;
    margin-bottom: 10px;
}

.invitation-info .team-name {
    font-weight: bold;
    color: #00d4ff;
    margin: 0 0 5px 0;
}

.invitation-info .captain-name {
    color: #aaa;
    font-size: 14px;
    margin: 0;
}

.invitation-actions {
    display: flex;
    gap: 10px;
}

.btn-accept, .btn-decline {
    padding: 8px 16px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s;
}

.btn-accept {
    background: #28a745;
    color: #fff;
}

.btn-accept:hover {
    background: #218838;
}

.btn-decline {
    background: #dc3545;
    color: #fff;
}

.btn-decline:hover {
    background: #c82333;
}

.error-message {
    color: #ff6b6b;
    background: rgba(255, 107, 107, 0.1);
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
}

.success-message {
    color: #28a745;
    background: rgba(40, 167, 69, 0.1);
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
}

.char-limit {
    color: #888;
    font-weight: normal;
    font-size: 0.85em;
}

.char-counter {
    text-align: right;
    color: #888;
    font-size: 0.85em;
    margin-top: 5px;
}

.emblem-selector {
    background: #16213e;
    border: 2px solid #0f3460;
    border-radius: 12px;
    padding: 20px;
    margin-top: 10px;
}

.emblem-preview {
    width: 100px;
    height: 100px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%);
    border: 3px solid #00d4ff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.emblem-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.emblem-placeholder {
    font-size: 3rem;
}

.emblem-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    justify-content: center;
}

.emblem-tab {
    padding: 10px 25px;
    background: #1a1a2e;
    border: 2px solid #0f3460;
    color: #aaa;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.emblem-tab:hover {
    border-color: #00d4ff;
    color: #fff;
}

.emblem-tab.active {
    background: linear-gradient(135deg, #00d4ff 0%, #0f3460 100%);
    border-color: #00d4ff;
    color: #fff;
}

.emblem-categories {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
}

@media (max-width: 768px) {
    .emblem-categories {
        grid-template-columns: repeat(2, 1fr);
    }
}

.category-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px 10px;
    background: #1a1a2e;
    border: 2px solid #0f3460;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.category-btn:hover {
    border-color: #00d4ff;
    transform: translateY(-3px);
}

.category-btn .cat-icon {
    font-size: 1.8rem;
    margin-bottom: 5px;
}

.category-btn .cat-name {
    font-size: 0.75rem;
    color: #aaa;
}

.emblem-grid {
    max-height: 300px;
    overflow-y: auto;
}

.back-to-categories {
    background: none;
    border: none;
    color: #00d4ff;
    cursor: pointer;
    margin-bottom: 15px;
    font-size: 0.9rem;
    padding: 5px 10px;
}

.back-to-categories:hover {
    text-decoration: underline;
}

.emblems-container {
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 8px;
}

@media (max-width: 768px) {
    .emblems-container {
        grid-template-columns: repeat(5, 1fr);
    }
}

.emblem-item {
    width: 40px;
    height: 40px;
    border: 2px solid #0f3460;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: #1a1a2e;
    transition: all 0.2s ease;
}

.emblem-item:hover {
    border-color: #00d4ff;
    transform: scale(1.1);
}

.emblem-item.selected {
    border-color: #ffd700;
    background: rgba(255, 215, 0, 0.2);
}

.upload-zone {
    border: 2px dashed #0f3460;
    border-radius: 10px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.upload-zone:hover {
    border-color: #00d4ff;
    background: rgba(0, 212, 255, 0.05);
}

.upload-zone.dragover {
    border-color: #00d4ff;
    background: rgba(0, 212, 255, 0.1);
}

.upload-placeholder .upload-icon {
    font-size: 3rem;
    display: block;
    margin-bottom: 10px;
}

.upload-placeholder p {
    color: #fff;
    margin: 0 0 5px 0;
}

.upload-placeholder small {
    color: #888;
}

.team-emblem {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%);
    border: 2px solid #00d4ff;
    overflow: hidden;
    flex-shrink: 0;
}

.team-emblem img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

@media (max-width: 480px) {
    .create-team-section, .team-info-section {
        padding: 15px;
        border-width: 1px;
        margin: 0 -5px 15px -5px;
        border-radius: 10px;
    }
    
    .team-header-card {
        padding: 10px;
    }
    
    .team-header-row {
        gap: 8px;
    }
    
    .team-name-title {
        font-size: 1.1rem;
    }
    
    .team-emblem {
        width: 40px;
        height: 40px;
        font-size: 1.5rem;
        border-width: 1px;
    }
    
    .team-division {
        padding: 5px 12px;
        font-size: 0.8rem;
    }
    
    .team-members-section {
        margin: 15px 0;
    }
    
    .member-card {
        padding: 10px;
        gap: 8px;
    }
    
    .member-info {
        gap: 10px;
        flex: 1;
        min-width: 0;
    }
    
    .member-avatar {
        width: 40px;
        height: 40px;
    }
    
    .default-avatar {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }
    
    .member-name {
        font-size: 0.9rem;
        word-break: break-word;
    }
    
    .member-actions {
        gap: 5px;
    }
    
    .btn-captain {
        padding: 6px 10px;
        font-size: 14px;
    }
    
    .btn-kick {
        padding: 6px 10px;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    
    .invite-section {
        margin: 15px 0;
    }
    
    .invite-form {
        flex-wrap: nowrap;
    }
    
    .invite-form input {
        padding: 10px;
        font-size: 14px;
    }
    
    .invite-form button {
        padding: 10px 12px;
        font-size: 14px;
    }
    
    .btn-requests, .btn-carnet {
        padding: 10px 15px;
        font-size: 0.85rem;
    }
    
    .carnet-panel {
        width: calc(100% - 20px);
        max-width: none;
        left: 10px;
        right: 10px;
    }
    
    .team-actions {
        margin-top: 20px;
    }
    
    .btn-danger {
        padding: 10px 20px;
        font-size: 14px;
    }
    
    .info-message {
        padding: 10px;
        font-size: 0.85rem;
    }
}
</style>

<script>
const emblems = {
    animals: ['🦁', '🐯', '🐻', '🦊', '🐺', '🦅', '🦈', '🐍', '🦎', '🐊', '🦂', '🦀', '🐙', '🦑', '🐋', '🐬', '🦭', '🐘', '🦏', '🦛', '🐪', '🦒', '🦘', '🦬', '🐃', '🦌', '🦙', '🐎', '🦓', '🐗', '🐺', '🦇', '🐀', '🐉', '🦎', '🦖', '🦕', '🐢', '🐸', '🐊', '🦜', '🦩', '🦚', '🦢', '🦤', '🕊️', '🐝', '🦋', '🐞', '🦗'],
    warriors: ['⚔️', '🗡️', '🛡️', '🏹', '🪓', '🔱', '⚒️', '🪃', '💣', '🧨', '💥', '🎯', '🥷', '👺', '👹', '💀', '☠️', '👻', '🤖', '👾', '🦾', '🦿', '🧠', '👁️', '🫀', '🪖', '🎖️', '🏅', '🥇', '⭐', '🌟', '✨', '💫', '🔥', '❄️', '⚡', '💨', '🌪️', '🌊', '🌋', '☄️', '🌙', '☀️', '🌈', '🎭', '👑', '💎', '🔮', '🧿', '⚱️'],
    sports: ['🏆', '🥇', '🥈', '🥉', '🏅', '⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸', '🥊', '🥋', '⛳', '⛸️', '🎿', '🛷', '🏂', '🏋️', '🤸', '🚴', '🏊', '🤽', '🚣', '🧗', '🤺', '🏄', '🎳', '♟️', '🎯', '🏹', '🥏', '🪀', '🛹', '🛼', '⛹️', '🤾', '🤿', '🪂', '🏇', '🚵', '🧘', '🎽', '🥅', '🪁', '🎣'],
    symbols: ['🌟', '⭐', '✨', '💫', '🔥', '💧', '💎', '❤️', '💜', '💙', '💚', '💛', '🧡', '🖤', '🤍', '❤️‍🔥', '💝', '💖', '💗', '💓', '💕', '💞', '💘', '💌', '🎀', '🎁', '🎊', '🎉', '🎈', '🎆', '🎇', '✳️', '❇️', '💠', '🔷', '🔶', '🔹', '🔸', '🟠', '🟡', '🟢', '🔵', '🟣', '🟤', '⚫', '⚪', '🔴', '🟥', '🟧', '🟨'],
    elements: ['🔥', '💧', '🌊', '💨', '🌪️', '⚡', '❄️', '☃️', '🌙', '☀️', '🌈', '⭐', '🌟', '✨', '💫', '☄️', '🌋', '🏔️', '⛰️', '🌍', '🌎', '🌏', '🪐', '💎', '🔮', '🧊', '🌡️', '🌀', '🌁', '🌫️', '🌤️', '⛅', '🌥️', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️', '☔', '💦', '💥', '🏝️', '🏜️', '🌵', '🌴', '🌲', '🌳', '🌾', '🍀', '🍁'],
    gaming: ['🎮', '🕹️', '👾', '🤖', '🎯', '🎲', '♟️', '🃏', '🀄', '🎰', '🎪', '🎭', '🎬', '🎥', '📺', '📱', '💻', '🖥️', '⌨️', '🖱️', '💾', '💿', '📀', '🔌', '🔋', '💡', '🔦', '🏮', '📡', '🛸', '🚀', '🛰️', '✈️', '🚁', '🎪', '🎢', '🎡', '🎠', '⚙️', '🔧', '🔩', '⛓️', '🔗', '📌', '📍', '🗺️', '🧭', '🎴', '🎨', '🖼️'],
    royalty: ['👑', '💎', '💍', '🏰', '🏯', '👸', '🤴', '🦁', '🦅', '🐉', '🗡️', '⚔️', '🛡️', '🔱', '⚜️', '🎖️', '🏅', '🥇', '🏆', '✨', '🌟', '⭐', '💫', '👼', '😇', '🙏', '🪔', '🕯️', '🔮', '🧿', '📿', '📜', '🖋️', '✒️', '🪶', '📖', '📚', '🎓', '🧙', '🧝', '🧚', '🧞', '🧜', '🧛', '🦸', '🦹', '🥷', '🤺', '♔', '♕'],
    flags: ['🏴', '🏳️', '🚩', '🎌', '🏁', '🇫🇷', '🇬🇧', '🇺🇸', '🇩🇪', '🇮🇹', '🇪🇸', '🇵🇹', '🇧🇷', '🇯🇵', '🇰🇷', '🇨🇳', '🇮🇳', '🇷🇺', '🇦🇺', '🇨🇦', '🇲🇽', '🇦🇷', '🇨🇱', '🇨🇴', '🇵🇪', '🇻🇪', '🇪🇨', '🇧🇴', '🇵🇾', '🇺🇾', '🇳🇱', '🇧🇪', '🇨🇭', '🇦🇹', '🇵🇱', '🇬🇷', '🇹🇷', '🇪🇬', '🇿🇦', '🇳🇬', '🇰🇪', '🇲🇦', '🇹🇳', '🇩🇿', '🇸🇦', '🇦🇪', '🇮🇱', '🇮🇪', '🇸🇪', '🇳🇴'],
    masks: ['🎭', '👺', '👹', '👻', '💀', '☠️', '👽', '👾', '🤖', '🤡', '😈', '👿', '🙀', '😱', '😰', '🥶', '🥵', '🤯', '😎', '🥸', '🤓', '🧐', '🤠', '😷', '🤒', '🤕', '🤑', '🤥', '🤫', '🤭', '🥳', '🥴', '😵', '🤐', '😮', '😯', '😲', '😳', '🤪', '😜', '😝', '😛', '🤑', '😏', '😒', '🙄', '😬', '😮‍💨', '🥱', '😴'],
    gems: ['💎', '💍', '👑', '🔮', '🧿', '📿', '💠', '🔷', '🔶', '🔹', '🔸', '❄️', '💧', '🩵', '🩷', '🩶', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💝', '💖', '💗', '💓', '💕', '⭐', '🌟', '✨', '💫', '🪙', '💰', '💳', '🏆', '🎖️', '🏅', '🥇', '🥈', '🥉', '✳️', '❇️', '🔆', '🔅', '💡', '🌸', '🌺']
};

function toggleCreateForm() {
    const section = document.getElementById('createTeamSection');
    if (section) {
        section.style.display = section.style.display === 'none' ? 'block' : 'none';
    }
}

document.getElementById('teamName')?.addEventListener('input', function() {
    document.getElementById('nameCharCount').textContent = this.value.length;
});

document.querySelectorAll('.emblem-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.emblem-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const tabName = this.dataset.tab;
        document.getElementById('categoriesTab').style.display = tabName === 'categories' ? 'block' : 'none';
        document.getElementById('uploadTab').style.display = tabName === 'upload' ? 'block' : 'none';
    });
});

document.querySelectorAll('.category-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const category = this.dataset.category;
        const container = document.getElementById('emblemsContainer');
        const categoryEmblems = emblems[category] || [];
        
        container.innerHTML = categoryEmblems.map((emb, idx) => 
            `<div class="emblem-item" data-category="${category}" data-index="${idx + 1}">${emb}</div>`
        ).join('');
        
        document.querySelector('.emblem-categories').style.display = 'none';
        document.getElementById('emblemGrid').style.display = 'block';
        
        container.querySelectorAll('.emblem-item').forEach(item => {
            item.addEventListener('click', function() {
                selectEmblem(this.dataset.category, this.dataset.index, this.textContent);
            });
        });
    });
});

document.getElementById('backToCategories')?.addEventListener('click', function() {
    document.querySelector('.emblem-categories').style.display = 'grid';
    document.getElementById('emblemGrid').style.display = 'none';
});

function selectEmblem(category, index, emoji) {
    document.getElementById('emblemCategory').value = category;
    document.getElementById('emblemIndex').value = index;
    document.getElementById('customEmblem').value = '';
    document.getElementById('emblemPreview').innerHTML = `<span style="font-size: 3rem;">${emoji}</span>`;
    
    document.querySelectorAll('.emblem-item').forEach(item => item.classList.remove('selected'));
    document.querySelector(`.emblem-item[data-category="${category}"][data-index="${index}"]`)?.classList.add('selected');
    
    document.querySelector('.emblem-categories').style.display = 'grid';
    document.getElementById('emblemGrid').style.display = 'none';
}

const uploadZone = document.getElementById('uploadZone');
const emblemUpload = document.getElementById('emblemUpload');

uploadZone?.addEventListener('click', () => emblemUpload.click());
uploadZone?.addEventListener('dragover', (e) => { e.preventDefault(); uploadZone.classList.add('dragover'); });
uploadZone?.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone?.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) handleFileUpload(e.dataTransfer.files[0]);
});

emblemUpload?.addEventListener('change', function() {
    if (this.files.length) handleFileUpload(this.files[0]);
});

function handleFileUpload(file) {
    if (file.size > 2 * 1024 * 1024) {
        alert('{{ __("Le fichier est trop volumineux (max 2MB)") }}');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('customEmblem').value = e.target.result;
        document.getElementById('emblemCategory').value = '';
        document.getElementById('emblemIndex').value = '';
        document.getElementById('emblemPreview').innerHTML = `<img src="${e.target.result}" alt="Emblem">`;
    };
    reader.readAsDataURL(file);
}

document.getElementById('createTeamBtn')?.addEventListener('click', async () => {
    const name = document.getElementById('teamName').value.trim();
    const emblemCategory = document.getElementById('emblemCategory').value;
    const emblemIndex = document.getElementById('emblemIndex').value;
    const customEmblem = document.getElementById('customEmblem').value;
    const errorDiv = document.getElementById('createError');

    if (!name) {
        errorDiv.textContent = '{{ __("Veuillez entrer un nom d\'équipe") }}';
        errorDiv.style.display = 'block';
        return;
    }

    if (name.length > 10) {
        errorDiv.textContent = '{{ __("Le nom ne doit pas dépasser 10 caractères") }}';
        errorDiv.style.display = 'block';
        return;
    }

    try {
        const response = await fetch('{{ route("league.team.create") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            },
            body: JSON.stringify({ 
                name, 
                emblem_category: emblemCategory,
                emblem_index: emblemIndex,
                custom_emblem: customEmblem
            })
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            errorDiv.textContent = data.error || '{{ __("Erreur lors de la création de l\'équipe") }}';
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = '{{ __("Erreur de connexion") }}';
        errorDiv.style.display = 'block';
    }
});

document.getElementById('inviteBtn')?.addEventListener('click', async () => {
    const playerName = document.getElementById('playerName').value.trim();
    const errorDiv = document.getElementById('inviteError');
    const successDiv = document.getElementById('inviteSuccess');

    if (!playerName) {
        errorDiv.textContent = 'Veuillez entrer un nom de joueur';
        errorDiv.style.display = 'block';
        successDiv.style.display = 'none';
        return;
    }

    try {
        const response = await fetch('{{ route("league.team.invite") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ 
                player_code: playerName,
                team_id: {{ $team->id }}
            })
        });

        const data = await response.json();

        if (data.success) {
            successDiv.textContent = '{{ __("Invitation envoyée avec succès !") }}';
            successDiv.style.display = 'block';
            errorDiv.style.display = 'none';
            document.getElementById('playerName').value = '';
        } else {
            errorDiv.textContent = data.error || '{{ __("Erreur lors de l\'invitation") }}';
            errorDiv.style.display = 'block';
            successDiv.style.display = 'none';
        }
    } catch (error) {
        errorDiv.textContent = '{{ __("Erreur de connexion") }}';
        errorDiv.style.display = 'block';
        successDiv.style.display = 'none';
    }
});

async function acceptInvitation(invitationId) {
    try {
        const response = await fetch(`/api/league/team/invitation/${invitationId}/accept`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            }
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            showToast(data.error || '{{ __("Erreur lors de l\'acceptation") }}', 'error');
        }
    } catch (error) {
        showToast('{{ __("Erreur de connexion") }}', 'error');
    }
}

async function declineInvitation(invitationId) {
    try {
        const response = await fetch(`/api/league/team/invitation/${invitationId}/decline`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Authorization': 'Bearer ' + localStorage.getItem('api_token')
            }
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        }
    } catch (error) {
        showToast('{{ __("Erreur de connexion") }}', 'error');
    }
}

async function kickMember(memberId) {
    if (!confirm('{{ __("Êtes-vous sûr de vouloir expulser ce membre ?") }}')) return;

    try {
        const response = await fetch('/league/team/kick', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ member_id: memberId })
        });

        const data = await response.json();

        if (data.success) {
            window.location.reload();
        } else {
            showToast(data.error || '{{ __("Erreur lors de l\'expulsion") }}', 'error');
        }
    } catch (error) {
        showToast('{{ __("Erreur de connexion") }}', 'error');
    }
}

async function transferCaptain(memberId, memberName) {
    if (!confirm(`{{ __("Voulez-vous nommer") }} ${memberName} {{ __("comme nouveau capitaine ?") }}`)) return;
    if (!confirm('{{ __("Confirmer: Vous perdrez vos droits de capitaine. Continuer ?") }}')) return;

    try {
        const response = await fetch('/league/team/transfer-captain', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ member_id: memberId })
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message || '{{ __("Capitaine transféré !") }}', 'success');
            window.location.reload();
        } else {
            showToast(data.error || '{{ __("Erreur lors du transfert") }}', 'error');
        }
    } catch (error) {
        showToast('{{ __("Erreur de connexion") }}', 'error');
    }
}

async function leaveTeam() {
    @if($team && $team->captain_id === Auth::id())
    if (!confirm('{{ __("ATTENTION: Vous êtes le capitaine! Si vous quittez, un autre membre deviendra capitaine. Êtes-vous sûr?") }}')) return;
    @endif
    
    if (!confirm('{{ __("Confirmer: Voulez-vous vraiment quitter l\'équipe?") }}')) return;

    try {
        const response = await fetch('/league/team/leave', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = '{{ route("ligue") }}';
        } else {
            showToast(data.error || '{{ __("Erreur lors de la sortie") }}', 'error');
        }
    } catch (error) {
        showToast('{{ __("Erreur de connexion") }}', 'error');
    }
}

function copyTeamCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        showToast('{{ __("Code copié!") }}', 'success');
    }).catch(() => {
        const tempInput = document.createElement('input');
        tempInput.value = code;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        showToast('{{ __("Code copié!") }}', 'success');
    });
}

async function toggleRecruiting() {
    try {
        const response = await fetch('/league/team/{{ $team->id ?? 0 }}/toggle-recruiting', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        const data = await response.json();

        if (data.success) {
            const btn = document.querySelector('.btn-recruit');
            const status = btn.querySelector('.recruit-status');
            if (data.is_recruiting) {
                btn.classList.add('active');
                status.textContent = '{{ __("Activé") }}';
                showToast('{{ __("Votre équipe apparaît maintenant dans la recherche") }}', 'success');
            } else {
                btn.classList.remove('active');
                status.textContent = '{{ __("Désactivé") }}';
                showToast('{{ __("Votre équipe est maintenant masquée") }}', 'info');
            }
        } else {
            showToast(data.error || '{{ __("Erreur") }}', 'error');
        }
    } catch (error) {
        showToast('{{ __("Erreur de connexion") }}', 'error');
    }
}

let carnetLoaded = false;

function toggleCarnet() {
    const modal = document.getElementById('carnetModal');
    if (!modal) return;
    
    const isVisible = modal.style.display !== 'none';
    modal.style.display = isVisible ? 'none' : 'block';
    
    if (!isVisible && !carnetLoaded) {
        loadContacts();
    }
}

async function loadContacts() {
    const listDiv = document.getElementById('carnetList');
    if (!listDiv) return;
    
    try {
        const response = await fetch('{{ route("league.team.contacts.api") }}', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
            }
        });
        
        const data = await response.json();
        carnetLoaded = true;
        
        if (!data.contacts || data.contacts.length === 0) {
            listDiv.innerHTML = '<p class="no-contacts">{{ __("Aucun contact dans votre carnet") }}</p>';
            return;
        }
        
        listDiv.innerHTML = data.contacts.map(contact => `
            <div class="contact-card" onclick="selectContact('${escapeHtml(contact.player_code)}')">
                <div class="contact-info">
                    <div class="contact-avatar">
                        ${contact.avatar_url 
                            ? `<img src="${contact.avatar_url}" alt="Avatar">` 
                            : contact.name.charAt(0).toUpperCase()
                        }
                    </div>
                    <div class="contact-details">
                        <p class="contact-name">${escapeHtml(contact.name)}</p>
                        <p class="contact-code">${contact.player_code}</p>
                    </div>
                </div>
                <div class="contact-stats">
                    <span>ELO: ${contact.elo}</span>
                    <span>${contact.wins}V/${contact.losses}D</span>
                </div>
            </div>
        `).join('');
    } catch (error) {
        listDiv.innerHTML = '<p class="no-contacts">{{ __("Erreur de chargement") }}</p>';
    }
}

function selectContact(playerCode) {
    document.getElementById('playerName').value = playerCode;
    toggleCarnet();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endsection
