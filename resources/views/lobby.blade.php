@extends('layouts.app')

@section('content')
@php
$mode = $lobby['mode'] ?? 'duo';
$lobbyCode = $lobby['code'] ?? '';
$players = $lobby['players'] ?? [];
$settings = $lobby['settings'] ?? [];
$teams = $lobby['teams'] ?? [];
$teamsEnabled = $settings['teams_enabled'] ?? false;
$maxPlayers = $settings['max_players'] ?? 10;
$minPlayers = $settings['min_players'] ?? 2;

$modeLabels = [
    'duo' => __('Duo'),
    'league_individual' => __('League Individuel'),
    'league_team' => __('League Équipe'),
    'master' => __('Master'),
];
$modeLabel = $modeLabels[$mode] ?? $mode;

$colorMap = [];
foreach ($colors as $color) {
    $colorMap[$color['id']] = $color;
}
@endphp

<style>
    body {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        color: #fff;
        min-height: 100vh;
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='white' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 35px !important;
    }
    
    select option {
        background-color: #1a1a2e;
        color: #fff;
        padding: 10px;
    }
    
    .lobby-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .lobby-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .lobby-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 10px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .lobby-info {
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 25px;
    }
    
    .info-badge {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 8px 16px;
        font-size: 0.9rem;
    }
    
    .players-section {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 25px;
    }
    
    .section-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .players-grid {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .player-card {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 15px;
        padding: 15px 20px;
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 15px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .player-card:hover {
        background: rgba(255, 255, 255, 0.12);
        transform: translateX(5px);
    }
    
    .player-card-old {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 15px;
        padding: 15px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        position: relative;
        transition: all 0.3s ease;
        border: 3px solid transparent;
    }
    
    .player-card.is-ready {
        border-color: #4CAF50;
        box-shadow: 0 0 15px rgba(76, 175, 80, 0.3);
    }
    
    .player-card.is-host {
        background: linear-gradient(135deg, rgba(255, 215, 0, 0.1), rgba(255, 193, 7, 0.05));
    }
    
    .player-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid currentColor;
    }
    
    .player-color-indicator {
        width: 8px;
        height: 40px;
        border-radius: 4px;
        flex-shrink: 0;
    }
    
    .player-info {
        flex: 1;
        min-width: 0;
    }
    
    .player-name {
        font-weight: 600;
        font-size: 1rem;
        text-align: left;
    }
    
    .player-code {
        font-family: monospace;
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
        text-align: left;
    }
    
    .player-actions {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }
    
    .player-action-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: none;
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .player-action-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.1);
    }
    
    .player-action-btn.active {
        background: rgba(76, 175, 80, 0.4);
        color: #81C784;
    }
    
    .player-action-btn.muted {
        background: rgba(244, 67, 54, 0.3);
        color: #EF5350;
    }
    
    .player-status {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.85rem;
        padding: 5px 12px;
        border-radius: 15px;
    }
    
    .status-ready {
        background: rgba(76, 175, 80, 0.2);
        color: #81C784;
    }
    
    .status-waiting {
        background: rgba(255, 193, 7, 0.2);
        color: #FFD54F;
    }
    
    .status-host {
        background: linear-gradient(135deg, rgba(255, 215, 0, 0.3), rgba(255, 193, 7, 0.2));
        color: #FFD700;
    }
    
    .color-picker {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 25px;
    }
    
    .colors-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: center;
    }
    
    .color-option {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        cursor: pointer;
        border: 3px solid transparent;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .color-option:hover {
        transform: scale(1.15);
    }
    
    .color-option.selected {
        border-color: #fff;
        box-shadow: 0 0 15px currentColor;
        transform: scale(1.2);
    }
    
    .color-option.taken {
        opacity: 0.3;
        cursor: not-allowed;
    }
    
    .color-option.taken::after {
        content: '✓';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 18px;
        color: #fff;
    }
    
    .teams-section {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 25px;
    }
    
    .teams-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 15px;
    }
    
    .team-card {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 15px;
        padding: 15px;
        border-left: 4px solid;
    }
    
    .team-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .team-color-badge {
        width: 24px;
        height: 24px;
        border-radius: 50%;
    }
    
    .team-name {
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .team-members {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .team-member {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 5px 12px;
        font-size: 0.85rem;
    }
    
    .actions-section {
        display: flex;
        flex-direction: column;
        gap: 15px;
        align-items: center;
    }
    
    .btn {
        padding: 15px 40px;
        border-radius: 30px;
        font-size: 1.1rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .btn-ready {
        background: linear-gradient(135deg, #4CAF50, #45a049);
        color: white;
        width: 100%;
        max-width: 300px;
    }
    
    .btn-ready:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
    }
    
    .btn-ready.is-ready {
        background: linear-gradient(135deg, #f44336, #d32f2f);
    }
    
    .btn-start {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        width: 100%;
        max-width: 300px;
    }
    
    .btn-start:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }
    
    .btn-start:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .btn-leave {
        background: transparent;
        color: rgba(255, 255, 255, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .btn-leave:hover {
        background: rgba(244, 67, 54, 0.2);
        color: #f44336;
        border-color: #f44336;
    }
    
    .waiting-message {
        text-align: center;
        padding: 20px;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.95rem;
    }
    
    .waiting-dots {
        display: inline-block;
        animation: dots 1.5s steps(4, end) infinite;
    }
    
    @keyframes dots {
        0%, 20% { content: ''; }
        40% { content: '.'; }
        60% { content: '..'; }
        80%, 100% { content: '...'; }
    }
    
    .waiting-dots::after {
        content: '...';
        animation: dots 1.5s steps(4, end) infinite;
    }
    
    .empty-slot {
        background: rgba(255, 255, 255, 0.03);
        border: 2px dashed rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 25px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 120px;
    }
    
    .empty-slot-icon {
        font-size: 2rem;
        opacity: 0.3;
        margin-bottom: 10px;
    }
    
    .empty-slot-text {
        color: rgba(255, 255, 255, 0.4);
        font-size: 0.9rem;
    }
    
    .toast {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .toast.show {
        opacity: 1;
    }
    
    @media (max-width: 600px) {
        .player-card {
            padding: 12px 15px;
        }
        
        .player-avatar {
            width: 40px;
            height: 40px;
        }
        
        .player-action-btn {
            width: 35px;
            height: 35px;
            font-size: 1rem;
        }
        
        .player-actions {
            gap: 5px;
        }
    }
</style>

<div class="lobby-container">
    <div class="lobby-header">
        <h1 class="lobby-title">{{ __('Salon d\'attente') }} - {{ $modeLabel }}</h1>
    </div>
    
    @if($isHost)
    <div class="settings-section" style="background: rgba(255, 255, 255, 0.05); border-radius: 15px; padding: 20px; margin-bottom: 25px;">
        <div class="section-title" style="margin-bottom: 15px;">
            <span>⚙️</span>
            <span>{{ __('Paramètres de la partie') }}</span>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-size: 0.85rem; color: rgba(255,255,255,0.7); margin-bottom: 5px;">🎯 {{ __('Thème') }}</label>
                <select id="theme-select" onchange="updateSettings()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: #fff; font-size: 1rem;">
                    <option value="Culture générale" {{ ($settings['theme'] ?? '') == 'Culture générale' ? 'selected' : '' }}>{{ __('Culture générale') }}</option>
                    <option value="Géographie" {{ ($settings['theme'] ?? '') == 'Géographie' ? 'selected' : '' }}>{{ __('Géographie') }}</option>
                    <option value="Histoire" {{ ($settings['theme'] ?? '') == 'Histoire' ? 'selected' : '' }}>{{ __('Histoire') }}</option>
                    <option value="Sports" {{ ($settings['theme'] ?? '') == 'Sports' ? 'selected' : '' }}>{{ __('Sports') }}</option>
                    <option value="Sciences" {{ ($settings['theme'] ?? '') == 'Sciences' ? 'selected' : '' }}>{{ __('Sciences') }}</option>
                    <option value="Cinéma" {{ ($settings['theme'] ?? '') == 'Cinéma' ? 'selected' : '' }}>{{ __('Cinéma') }}</option>
                    <option value="Art" {{ ($settings['theme'] ?? '') == 'Art' ? 'selected' : '' }}>{{ __('Art') }}</option>
                    <option value="Animaux" {{ ($settings['theme'] ?? '') == 'Animaux' ? 'selected' : '' }}>{{ __('Animaux') }}</option>
                    <option value="Cuisine" {{ ($settings['theme'] ?? '') == 'Cuisine' ? 'selected' : '' }}>{{ __('Cuisine') }}</option>
                </select>
            </div>
            <div style="min-width: 150px;">
                <label style="display: block; font-size: 0.85rem; color: rgba(255,255,255,0.7); margin-bottom: 5px;">❓ {{ __('Questions') }}</label>
                <select id="questions-select" onchange="updateSettings()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: #fff; font-size: 1rem;">
                    @foreach([5, 7, 10, 15, 20] as $num)
                        <option value="{{ $num }}" {{ ($settings['nb_questions'] ?? 10) == $num ? 'selected' : '' }}>{{ $num }} {{ __('questions') }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display: flex; align-items: flex-end;">
                <span class="info-badge" style="margin-left: 10px;">👥 {{ count($players) }}/{{ $maxPlayers }}</span>
            </div>
        </div>
    </div>
    @else
    <div class="lobby-info">
        <span class="info-badge">🎯 {{ $settings['theme'] ?? 'Culture générale' }}</span>
        <span class="info-badge">❓ {{ $settings['nb_questions'] ?? 10 }} {{ __('questions') }}</span>
        <span class="info-badge">👥 {{ count($players) }}/{{ $maxPlayers }}</span>
    </div>
    @endif
    
    <div class="players-section">
        <div class="section-title">
            <span>👥</span>
            <span>{{ __('Joueurs') }} ({{ count($players) }}/{{ $maxPlayers }})</span>
        </div>
        
        <div class="players-grid">
            @foreach($players as $playerId => $player)
                @php
                    $playerColor = $colorMap[$player['color']] ?? $colorMap['blue'];
                    $isCurrentPlayer = $playerId == $currentPlayerId;
                    $avatarRaw = $player['avatar'] ?? 'default';
                    if (str_contains($avatarRaw, '/') || str_contains($avatarRaw, '.png')) {
                        $avatarSrc = '/' . ltrim(preg_replace('/\.png$/', '', $avatarRaw), '/') . '.png';
                    } else {
                        $avatarSrc = asset('images/avatars/standard/' . $avatarRaw . '.png');
                    }
                @endphp
                <div class="player-card {{ $player['ready'] ? 'is-ready' : '' }} {{ $player['is_host'] ? 'is-host' : '' }}" 
                     style="border-left: 4px solid {{ $playerColor['hex'] }};"
                     data-player-id="{{ $playerId }}"
                     onclick="showPlayerStats({{ $playerId }}, '{{ addslashes($player['name']) }}')">
                    
                    <div class="player-color-indicator" style="background: {{ $playerColor['hex'] }};"></div>
                    
                    <img src="{{ $avatarSrc }}" 
                         alt="{{ $player['name'] }}" 
                         class="player-avatar"
                         style="width: 50px; height: 50px; border-color: {{ $playerColor['hex'] }};"
                         onerror="this.src='{{ asset('images/avatars/standard/default.png') }}'">
                    
                    <div class="player-info">
                        <div class="player-name">
                            {{ $player['name'] }}
                            @if($isCurrentPlayer)
                                <span style="font-size: 0.8rem; opacity: 0.7;">({{ __('vous') }})</span>
                            @endif
                        </div>
                        <div class="player-code">{{ $player['player_code'] ?? 'SB-????' }}</div>
                    </div>
                    
                    @if($player['is_host'])
                        <div class="player-status status-host">👑</div>
                    @elseif($player['ready'])
                        <div class="player-status status-ready">✓</div>
                    @else
                        <div class="player-status status-waiting">⏳</div>
                    @endif
                    
                    <div class="player-actions" onclick="event.stopPropagation()">
                        @if(!$isCurrentPlayer)
                            <button class="player-action-btn" onclick="openPlayerChat({{ $playerId }}, '{{ addslashes($player['name']) }}')" title="{{ __('Chat') }}">💬</button>
                        @endif
                        <button class="player-action-btn {{ $isCurrentPlayer ? 'active' : '' }}" 
                                id="mic-btn-{{ $playerId }}" 
                                onclick="toggleMic({{ $playerId }})" 
                                title="{{ __('Micro') }}">🎤</button>
                    </div>
                </div>
            @endforeach
            
            @for($i = count($players); $i < min($maxPlayers, 8); $i++)
                <div class="empty-slot" style="padding: 15px; display: flex; align-items: center; gap: 15px;">
                    <div class="empty-slot-icon" style="font-size: 1.5rem; margin: 0;">👤</div>
                    <div class="empty-slot-text">{{ __('En attente d\'un joueur...') }}</div>
                </div>
            @endfor
        </div>
    </div>
    
    <div class="color-picker">
        <div class="section-title">
            <span>🎨</span>
            <span>{{ __('Choisissez votre couleur') }}</span>
        </div>
        
        <div class="colors-grid">
            @php
                $takenColors = collect($players)->pluck('color')->toArray();
                $currentPlayerColor = $players[$currentPlayerId]['color'] ?? 'blue';
            @endphp
            
            @foreach($colors as $color)
                @php
                    $isTaken = in_array($color['id'], $takenColors) && $color['id'] !== $currentPlayerColor;
                    $isSelected = $color['id'] === $currentPlayerColor;
                @endphp
                <div class="color-option {{ $isSelected ? 'selected' : '' }} {{ $isTaken ? 'taken' : '' }}"
                     style="background: {{ $color['hex'] }}; color: {{ $color['hex'] }};"
                     data-color-id="{{ $color['id'] }}"
                     title="{{ $color['name'] }}"
                     @if(!$isTaken) onclick="selectColor('{{ $color['id'] }}')" @endif>
                </div>
            @endforeach
        </div>
    </div>
    
    @if($teamsEnabled && !empty($teams))
        <div class="teams-section">
            <div class="section-title">
                <span>⚔️</span>
                <span>{{ __('Équipes') }}</span>
            </div>
            
            <div class="teams-grid">
                @foreach($teams as $teamId => $team)
                    @php
                        $teamColor = $colorMap[$team['color']] ?? $colorMap['blue'];
                        $teamMembers = collect($players)->filter(fn($p) => ($p['team'] ?? null) === $teamId);
                    @endphp
                    <div class="team-card" style="border-left-color: {{ $teamColor['hex'] }};">
                        <div class="team-header">
                            <div class="team-color-badge" style="background: {{ $teamColor['hex'] }};"></div>
                            <div class="team-name">{{ $team['name'] }}</div>
                        </div>
                        <div class="team-members">
                            @forelse($teamMembers as $member)
                                <span class="team-member">{{ $member['name'] }}</span>
                            @empty
                                <span class="team-member" style="opacity: 0.5;">{{ __('Aucun joueur') }}</span>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    
    <div class="actions-section">
        @if(!$isHost)
            <button class="btn btn-ready {{ ($players[$currentPlayerId]['ready'] ?? false) ? 'is-ready' : '' }}" 
                    onclick="toggleReady()"
                    id="ready-btn">
                <span id="ready-text">
                    {{ ($players[$currentPlayerId]['ready'] ?? false) ? __('Annuler') : __('Je suis prêt !') }}
                </span>
            </button>
        @endif
        
        @if($isHost)
            <button class="btn btn-start" 
                    onclick="startGame()"
                    id="start-btn"
                    {{ $canStart ? '' : 'disabled' }}>
                {{ __('Lancer la partie') }}
            </button>
            
            @if(!$canStart)
                <div class="waiting-message">
                    @if(count($players) < $minPlayers)
                        {{ __('En attente de joueurs') }} ({{ count($players) }}/{{ $minPlayers }} {{ __('minimum') }})<span class="waiting-dots"></span>
                    @else
                        {{ __('En attente que tous les joueurs soient prêts') }}<span class="waiting-dots"></span>
                    @endif
                </div>
            @endif
        @endif
        
        <button class="btn btn-leave" onclick="leaveLobby()">
            {{ __('Quitter le salon') }}
        </button>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    const lobbyCode = '{{ $lobbyCode }}';
    const currentPlayerId = {{ $currentPlayerId }};
    const isHost = {{ $isHost ? 'true' : 'false' }};
    let isReady = {{ ($players[$currentPlayerId]['ready'] ?? false) ? 'true' : 'false' }};
    let pollingInterval = null;
    
    function showToast(message, duration = 3000) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), duration);
    }
    
    function showPlayerStats(playerId, playerName) {
        showToast('{{ __("Statistiques de") }} ' + playerName);
    }
    
    function openPlayerChat(playerId, playerName) {
        showToast('{{ __("Chat avec") }} ' + playerName);
    }
    
    let micStates = {};
    
    function toggleMic(playerId) {
        const btn = document.getElementById('mic-btn-' + playerId);
        if (!btn) return;
        
        if (playerId === currentPlayerId) {
            micStates[playerId] = !micStates[playerId];
            if (micStates[playerId]) {
                btn.classList.add('active');
                btn.classList.remove('muted');
                showToast('{{ __("Micro activé") }}');
            } else {
                btn.classList.remove('active');
                btn.classList.add('muted');
                showToast('{{ __("Micro désactivé") }}');
            }
        } else {
            micStates[playerId] = !micStates[playerId];
            if (micStates[playerId]) {
                btn.classList.remove('muted');
                showToast('{{ __("Son activé") }}');
            } else {
                btn.classList.add('muted');
                showToast('{{ __("Son désactivé") }}');
            }
        }
    }
    
    function submitGameStart(mode, settings) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/game/${mode}/start`;
        form.style.display = 'none';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
        form.appendChild(csrfInput);
        
        const themeInput = document.createElement('input');
        themeInput.type = 'hidden';
        themeInput.name = 'theme';
        themeInput.value = settings.theme || '{{ __("Culture générale") }}';
        form.appendChild(themeInput);
        
        const nbQuestionsInput = document.createElement('input');
        nbQuestionsInput.type = 'hidden';
        nbQuestionsInput.name = 'nb_questions';
        nbQuestionsInput.value = settings.nb_questions || 10;
        form.appendChild(nbQuestionsInput);
        
        const lobbyInput = document.createElement('input');
        lobbyInput.type = 'hidden';
        lobbyInput.name = 'lobby_code';
        lobbyInput.value = lobbyCode;
        form.appendChild(lobbyInput);
        
        const niveauInput = document.createElement('input');
        niveauInput.type = 'hidden';
        niveauInput.name = 'niveau';
        niveauInput.value = 1;
        form.appendChild(niveauInput);
        
        document.body.appendChild(form);
        form.submit();
    }
    
    async function selectColor(colorId) {
        try {
            const response = await fetch(`/lobby/${lobbyCode}/color`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({ color: colorId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                location.reload();
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error selecting color:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    async function toggleReady() {
        try {
            const newReadyState = !isReady;
            
            const response = await fetch(`/lobby/${lobbyCode}/ready`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({ ready: newReadyState })
            });
            
            const data = await response.json();
            
            if (data.success) {
                isReady = newReadyState;
                updateReadyButton();
                refreshLobbyState();
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error toggling ready:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    function updateReadyButton() {
        const btn = document.getElementById('ready-btn');
        const text = document.getElementById('ready-text');
        
        if (isReady) {
            btn.classList.add('is-ready');
            text.textContent = '{{ __("Annuler") }}';
        } else {
            btn.classList.remove('is-ready');
            text.textContent = '{{ __("Je suis prêt !") }}';
        }
    }
    
    async function updateSettings() {
        if (!isHost) return;
        
        const themeSelect = document.getElementById('theme-select');
        const questionsSelect = document.getElementById('questions-select');
        
        if (!themeSelect || !questionsSelect) return;
        
        try {
            const response = await fetch(`/lobby/${lobbyCode}/settings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    theme: themeSelect.value,
                    nb_questions: parseInt(questionsSelect.value)
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('{{ __("Paramètres mis à jour") }}');
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error updating settings:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    async function startGame() {
        try {
            const response = await fetch(`/lobby/${lobbyCode}/start`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                const mode = data.lobby?.mode || 'duo';
                const settings = data.lobby?.settings || {};
                submitGameStart(mode, settings);
            } else {
                showToast(data.error || '{{ __("Impossible de lancer la partie") }}');
            }
        } catch (error) {
            console.error('Error starting game:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    async function leaveLobby() {
        if (!confirm('{{ __("Voulez-vous vraiment quitter le salon ?") }}')) {
            return;
        }
        
        try {
            const response = await fetch(`/lobby/${lobbyCode}/leave`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                }
            });
            
            window.location.href = '/';
        } catch (error) {
            console.error('Error leaving lobby:', error);
            window.location.href = '/';
        }
    }
    
    const colorMap = @json($colorMap);
    const maxPlayers = {{ $maxPlayers }};
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    const translations = {
        you: '{{ __("vous") }}',
        waitingPlayer: '{{ __("En attente d\'un joueur...") }}',
        chat: '{{ __("Chat") }}',
        micro: '{{ __("Micro") }}',
        players: '{{ __("Joueurs") }}',
        lobbyClosed: '{{ __("Le salon a été fermé") }}',
        waitingMessage: '{{ __("En attente de joueurs") }}',
        waitingReady: '{{ __("En attente que tous les joueurs soient prêts") }}',
        minimum: '{{ __("minimum") }}'
    };
    
    function updatePlayersUI(players) {
        const playersGrid = document.querySelector('.players-grid');
        if (!playersGrid) return;
        
        const playerEntries = Object.entries(players || {});
        let html = '';
        
        playerEntries.forEach(([playerId, player]) => {
            const playerColor = colorMap[player.color] || colorMap['blue'];
            const isCurrentPlayer = parseInt(playerId) === currentPlayerId;
            const readyClass = player.ready ? 'is-ready' : '';
            const hostClass = player.is_host ? 'is-host' : '';
            
            let statusHtml = '';
            if (player.is_host) {
                statusHtml = '<div class="player-status status-host">👑</div>';
            } else if (player.ready) {
                statusHtml = '<div class="player-status status-ready">✓</div>';
            } else {
                statusHtml = '<div class="player-status status-waiting">⏳</div>';
            }
            
            let avatarRaw = player.avatar || 'default';
            let avatarSrc;
            if (avatarRaw.includes('/') || avatarRaw.includes('.png')) {
                avatarSrc = '/' + avatarRaw.replace(/^\//, '').replace(/\.png$/, '') + '.png';
            } else {
                avatarSrc = '/images/avatars/standard/' + avatarRaw + '.png';
            }
            const safeName = escapeHtml(player.name);
            const safeCode = escapeHtml(player.player_code || 'SB-????');
            const youLabel = isCurrentPlayer ? `<span style="font-size: 0.8rem; opacity: 0.7;">(${translations.you})</span>` : '';
            const chatBtn = !isCurrentPlayer ? `<button class="player-action-btn" data-player-id="${playerId}" data-action="chat" title="${translations.chat}">💬</button>` : '';
            
            html += `
                <div class="player-card ${readyClass} ${hostClass}" 
                     style="border-left: 4px solid ${playerColor.hex};"
                     data-player-id="${playerId}"
                     data-player-name="${safeName}">
                    
                    <div class="player-color-indicator" style="background: ${playerColor.hex};"></div>
                    
                    <img src="${avatarSrc}" 
                         alt="${safeName}" 
                         class="player-avatar"
                         style="width: 50px; height: 50px; border-color: ${playerColor.hex};"
                         onerror="this.src='/images/avatars/standard/default.png'">
                    
                    <div class="player-info">
                        <div class="player-name">
                            ${safeName}
                            ${youLabel}
                        </div>
                        <div class="player-code">${safeCode}</div>
                    </div>
                    
                    ${statusHtml}
                    
                    <div class="player-actions" onclick="event.stopPropagation()">
                        ${chatBtn}
                        <button class="player-action-btn ${isCurrentPlayer ? 'active' : ''}" 
                                id="mic-btn-${playerId}" 
                                data-player-id="${playerId}"
                                data-action="mic"
                                title="${translations.micro}">🎤</button>
                    </div>
                </div>
            `;
        });
        
        const emptySlots = Math.min(maxPlayers, 8) - playerEntries.length;
        for (let i = 0; i < emptySlots; i++) {
            html += `
                <div class="empty-slot" style="padding: 15px; display: flex; align-items: center; gap: 15px;">
                    <div class="empty-slot-icon" style="font-size: 1.5rem; margin: 0;">👤</div>
                    <div class="empty-slot-text">${translations.waitingPlayer}</div>
                </div>
            `;
        }
        
        playersGrid.innerHTML = html;
        
        const sectionTitle = document.querySelector('.players-section .section-title span:last-child');
        if (sectionTitle) {
            sectionTitle.textContent = `${translations.players} (${playerEntries.length}/${maxPlayers})`;
        }
    }
    
    document.addEventListener('click', function(e) {
        const playerCard = e.target.closest('.player-card');
        if (playerCard && !e.target.closest('.player-actions')) {
            const playerId = playerCard.dataset.playerId;
            const playerName = playerCard.dataset.playerName;
            if (playerId && playerName) {
                showPlayerStats(parseInt(playerId), playerName);
            }
        }
        
        const actionBtn = e.target.closest('[data-action]');
        if (actionBtn) {
            e.stopPropagation();
            const action = actionBtn.dataset.action;
            const playerId = parseInt(actionBtn.dataset.playerId);
            const playerCard = actionBtn.closest('.player-card');
            const playerName = playerCard?.dataset.playerName || '';
            
            if (action === 'chat') {
                openPlayerChat(playerId, playerName);
            } else if (action === 'mic') {
                toggleMic(playerId);
            }
        }
    });
    
    function updateWaitingMessage(players, minPlayers, allReady) {
        const waitingDiv = document.querySelector('.waiting-message');
        if (!waitingDiv) return;
        
        const playerCount = Object.keys(players || {}).length;
        
        if (playerCount < minPlayers) {
            waitingDiv.innerHTML = `${translations.waitingMessage} (${playerCount}/${minPlayers} ${translations.minimum})<span class="waiting-dots"></span>`;
            waitingDiv.style.display = 'block';
        } else if (!allReady) {
            waitingDiv.innerHTML = `${translations.waitingReady}<span class="waiting-dots"></span>`;
            waitingDiv.style.display = 'block';
        } else {
            waitingDiv.style.display = 'none';
        }
    }
    
    async function refreshLobbyState() {
        try {
            const response = await fetch(`/lobby/${lobbyCode}/state`);
            const data = await response.json();
            
            if (!data.exists) {
                showToast(translations.lobbyClosed);
                setTimeout(() => window.location.href = '/', 2000);
                return;
            }
            
            if (data.lobby?.status === 'starting') {
                const mode = data.lobby?.mode || 'duo';
                const settings = data.lobby?.settings || {};
                submitGameStart(mode, settings);
                return;
            }
            
            updatePlayersUI(data.lobby?.players);
            
            if (isHost) {
                updateWaitingMessage(data.lobby?.players, {{ $minPlayers }}, data.all_ready);
                
                if (data.can_start) {
                    document.getElementById('start-btn')?.removeAttribute('disabled');
                } else {
                    document.getElementById('start-btn')?.setAttribute('disabled', 'disabled');
                }
            }
            
        } catch (error) {
            console.error('Error refreshing lobby state:', error);
        }
    }
    
    pollingInterval = setInterval(refreshLobbyState, 2000);
    
    window.addEventListener('beforeunload', () => {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }
    });
</script>

@if(isset($matchId) && $matchId)
<script type="module">
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
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
const db = getFirestore(app);

const matchId = {{ $matchId }};
const currentUserId = {{ $currentPlayerId }};
const isHost = {{ $isHost ? 'true' : 'false' }};

const matchRef = doc(db, 'duo_matches', String(matchId));

let declineHandled = false;
const defaultGuestName = @json(__('Invité'));
const declinedMessage = @json(__('a refusé votre invitation'));

onSnapshot(matchRef, (docSnap) => {
    if (!docSnap.exists()) return;
    
    const data = docSnap.data();
    
    if (data.status === 'declined' && isHost && !declineHandled) {
        declineHandled = true;
        const declinedByName = data.declinedByName || defaultGuestName;
        
        const toast = document.getElementById('toast');
        toast.textContent = declinedByName + ' ' + declinedMessage;
        toast.classList.add('show');
        toast.style.background = '#E53935';
        
        setTimeout(() => {
            toast.classList.remove('show');
            window.location.href = '/duo/lobby';
        }, 3000);
    }
    
    if (data.player2Joined && isHost) {
        location.reload();
    }
});
</script>
@endif
@endsection
