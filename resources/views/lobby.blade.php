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
    
    .lobby-code {
        display: inline-block;
        background: rgba(255, 255, 255, 0.1);
        border: 2px dashed rgba(255, 255, 255, 0.3);
        border-radius: 12px;
        padding: 15px 30px;
        font-size: 2.5rem;
        font-weight: 700;
        letter-spacing: 8px;
        font-family: 'Courier New', monospace;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .lobby-code:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: scale(1.02);
    }
    
    .lobby-code-hint {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
        margin-top: 10px;
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
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .player-card {
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
        position: absolute;
        top: 10px;
        right: 10px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }
    
    .player-name {
        font-weight: 600;
        font-size: 1rem;
        text-align: center;
    }
    
    .player-code {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.6);
        font-family: monospace;
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
        .lobby-code {
            font-size: 1.8rem;
            letter-spacing: 5px;
            padding: 12px 20px;
        }
        
        .players-grid {
            grid-template-columns: 1fr 1fr;
        }
        
        .player-card {
            padding: 12px;
        }
        
        .player-avatar {
            width: 50px;
            height: 50px;
        }
    }
</style>

<div class="lobby-container">
    <div class="lobby-header">
        <h1 class="lobby-title">{{ __('Salon d\'attente') }} - {{ $modeLabel }}</h1>
        
        <div class="lobby-code" onclick="copyLobbyCode()" title="{{ __('Cliquer pour copier') }}">
            {{ $lobbyCode }}
        </div>
        <div class="lobby-code-hint">{{ __('Partagez ce code avec vos amis') }}</div>
    </div>
    
    <div class="lobby-info">
        <span class="info-badge">🎯 {{ $settings['theme'] ?? 'Culture générale' }}</span>
        <span class="info-badge">❓ {{ $settings['nb_questions'] ?? 10 }} {{ __('questions') }}</span>
        <span class="info-badge">👥 {{ count($players) }}/{{ $maxPlayers }}</span>
    </div>
    
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
                @endphp
                <div class="player-card {{ $player['ready'] ? 'is-ready' : '' }} {{ $player['is_host'] ? 'is-host' : '' }}" 
                     style="color: {{ $playerColor['hex'] }};"
                     data-player-id="{{ $playerId }}">
                    
                    <div class="player-color-indicator" style="background: {{ $playerColor['hex'] }};"></div>
                    
                    <img src="{{ asset('images/avatars/standard/' . ($player['avatar'] ?? 'default') . '.png') }}" 
                         alt="{{ $player['name'] }}" 
                         class="player-avatar"
                         onerror="this.src='{{ asset('images/avatars/standard/default.png') }}'">
                    
                    <div class="player-name">
                        {{ $player['name'] }}
                        @if($isCurrentPlayer)
                            <span style="font-size: 0.8rem; opacity: 0.7;">({{ __('vous') }})</span>
                        @endif
                    </div>
                    
                    <div class="player-code">{{ $player['player_code'] ?? 'SB-????' }}</div>
                    
                    @if($player['is_host'])
                        <div class="player-status status-host">👑 {{ __('Hôte') }}</div>
                    @elseif($player['ready'])
                        <div class="player-status status-ready">✓ {{ __('Prêt') }}</div>
                    @else
                        <div class="player-status status-waiting">⏳ {{ __('En attente') }}</div>
                    @endif
                </div>
            @endforeach
            
            @for($i = count($players); $i < min($maxPlayers, 8); $i++)
                <div class="empty-slot">
                    <div class="empty-slot-icon">👤</div>
                    <div class="empty-slot-text">{{ __('En attente...') }}</div>
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
    
    function copyLobbyCode() {
        navigator.clipboard.writeText(lobbyCode).then(() => {
            showToast('{{ __("Code copié !") }}');
        }).catch(() => {
            showToast('{{ __("Erreur lors de la copie") }}');
        });
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
                window.location.href = `/game/${mode}/start?lobby=${lobbyCode}`;
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
    
    async function refreshLobbyState() {
        try {
            const response = await fetch(`/lobby/${lobbyCode}/state`);
            const data = await response.json();
            
            if (!data.exists) {
                showToast('{{ __("Le salon a été fermé") }}');
                setTimeout(() => window.location.href = '/', 2000);
                return;
            }
            
            if (data.lobby?.status === 'starting') {
                const mode = data.lobby?.mode || 'duo';
                window.location.href = `/game/${mode}/start?lobby=${lobbyCode}`;
                return;
            }
            
            if (isHost && data.can_start) {
                document.getElementById('start-btn')?.removeAttribute('disabled');
            } else if (isHost) {
                document.getElementById('start-btn')?.setAttribute('disabled', 'disabled');
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
@endsection
