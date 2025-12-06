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
        overflow: hidden;
    }
    
    .player-name {
        font-weight: 600;
        font-size: 1rem;
        text-align: left;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 5px;
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
    
    .player-action-btn.speaking {
        animation: speakingPulse 0.8s ease-in-out infinite;
        box-shadow: 0 0 15px rgba(76, 175, 80, 0.6);
    }
    
    @keyframes speakingPulse {
        0%, 100% { transform: scale(1); box-shadow: 0 0 15px rgba(76, 175, 80, 0.4); }
        50% { transform: scale(1.1); box-shadow: 0 0 25px rgba(76, 175, 80, 0.8); }
    }
    
    .player-card.speaking {
        border-color: #4CAF50 !important;
        box-shadow: 0 0 20px rgba(76, 175, 80, 0.3);
    }
    
    .voice-indicator {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #4CAF50;
        animation: voicePulse 1s ease-in-out infinite;
    }
    
    @keyframes voicePulse {
        0%, 100% { opacity: 0.5; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.2); }
    }
    
    .mic-connecting {
        animation: micConnecting 1.5s ease-in-out infinite;
    }
    
    @keyframes micConnecting {
        0%, 100% { opacity: 0.5; }
        50% { opacity: 1; }
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
            padding: 10px 12px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .player-avatar {
            width: 45px;
            height: 45px;
            flex-shrink: 0;
        }
        
        .player-info {
            flex: 1;
            min-width: 60px;
            max-width: calc(100% - 180px);
        }
        
        .player-name {
            font-size: 0.9rem;
        }
        
        .player-code {
            font-size: 0.7rem;
        }
        
        .player-status {
            width: 28px;
            height: 28px;
            font-size: 0.9rem;
        }
        
        .player-action-btn {
            width: 32px;
            height: 32px;
            font-size: 0.9rem;
        }
        
        .player-actions {
            gap: 4px;
        }
        
        .player-color-indicator {
            display: none;
        }
    }
    
    .custom-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .custom-modal-overlay.show {
        opacity: 1;
        visibility: visible;
    }
    
    .custom-modal {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        border-radius: 20px;
        padding: 30px;
        max-width: 400px;
        width: 90%;
        text-align: center;
        transform: scale(0.8);
        transition: transform 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }
    
    .custom-modal-overlay.show .custom-modal {
        transform: scale(1);
    }
    
    .custom-modal-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 20px;
        color: #fff;
    }
    
    .custom-modal-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 25px;
    }
    
    .custom-modal-btn {
        padding: 12px 30px;
        border-radius: 10px;
        border: none;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .custom-modal-btn.confirm {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: #fff;
    }
    
    .custom-modal-btn.confirm:hover {
        transform: scale(1.05);
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    
    .custom-modal-btn.cancel {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .custom-modal-btn.cancel:hover {
        background: rgba(255, 255, 255, 0.2);
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
                        @if(in_array($mode, ['duo', 'league_individual', 'league_team']))
                        <button class="player-action-btn" 
                                id="mic-btn-{{ $playerId }}" 
                                onclick="toggleMic({{ $playerId }})" 
                                title="{{ __('Micro') }}">🎙️</button>
                        @else
                        <button class="player-action-btn" 
                                style="opacity: 0.3; cursor: not-allowed;"
                                title="{{ __('Audio non disponible') }}" disabled>🎙️</button>
                        @endif
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

<!-- Modal de confirmation personnalisée -->
<div class="custom-modal-overlay" id="confirmModal">
    <div class="custom-modal">
        <div class="custom-modal-title" id="confirmModalMessage"></div>
        <div class="custom-modal-buttons">
            <button class="custom-modal-btn cancel" id="confirmModalCancel">{{ __('Annuler') }}</button>
            <button class="custom-modal-btn confirm" id="confirmModalConfirm">{{ __('OK') }}</button>
        </div>
    </div>
</div>

<!-- Modal Stats Joueur -->
<div id="stats-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content stats-modal-content">
        <button class="modal-close" onclick="closeStatsModal()">&times;</button>
        <div class="stats-header">
            <img id="stats-avatar" src="" alt="" class="stats-avatar">
            <div class="stats-player-info">
                <h3 id="stats-player-name"></h3>
                <span id="stats-player-code" class="player-code"></span>
            </div>
        </div>
        <div class="stats-body">
            <div class="stats-grid">
                <div class="stat-box">
                    <span class="stat-label">{{ __('Niveau') }}</span>
                    <span id="stats-level" class="stat-value">-</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">{{ __('Division') }}</span>
                    <span id="stats-division" class="stat-value">-</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">{{ __('Victoires') }}</span>
                    <span id="stats-wins" class="stat-value">-</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">{{ __('Défaites') }}</span>
                    <span id="stats-losses" class="stat-value">-</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">{{ __('Taux victoire') }}</span>
                    <span id="stats-winrate" class="stat-value">-</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">{{ __('Précision') }}</span>
                    <span id="stats-efficiency" class="stat-value">-</span>
                </div>
            </div>
            <div class="radar-container">
                <canvas id="stats-radar" width="200" height="200"></canvas>
            </div>
            <div class="history-section">
                <h4>{{ __('Historique contre ce joueur') }}</h4>
                <div class="history-grid">
                    <div class="history-item">
                        <span class="history-label">{{ __('Matchs ensemble') }}</span>
                        <span id="history-matches" class="history-value">-</span>
                    </div>
                    <div class="history-item">
                        <span class="history-label">{{ __('Vos victoires') }}</span>
                        <span id="history-wins" class="history-value">-</span>
                    </div>
                    <div class="history-item">
                        <span class="history-label">{{ __('Vos défaites') }}</span>
                        <span id="history-losses" class="history-value">-</span>
                    </div>
                    <div class="history-item">
                        <span class="history-label">{{ __('Dernière partie') }}</span>
                        <span id="history-last" class="history-value">-</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="stats-actions">
            <button class="btn btn-chat" onclick="openPlayerChatFromStats()">💬 {{ __('Discuter') }}</button>
        </div>
    </div>
</div>

<!-- Modal Chat -->
<div id="chat-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content chat-modal-content">
        <button class="modal-close" onclick="closeChatModal()">&times;</button>
        <div class="chat-header">
            <img id="chat-avatar" src="" alt="" class="chat-avatar">
            <div class="chat-player-info">
                <h3 id="chat-player-name"></h3>
                <span id="chat-player-code" class="player-code"></span>
            </div>
        </div>
        <div class="chat-messages" id="chat-messages">
            <div class="chat-loading">{{ __('Chargement...') }}</div>
        </div>
        <div class="chat-input-area">
            <input type="text" id="chat-input" placeholder="{{ __('Votre message...') }}" maxlength="500">
            <button class="btn btn-send" onclick="sendChatMessage()">{{ __('Envoyer') }}</button>
        </div>
    </div>
</div>

<style>
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    .modal-content {
        background: linear-gradient(145deg, #1a1a2e, #16213e);
        border-radius: 15px;
        padding: 25px;
        max-width: 90vw;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        border: 2px solid #4fc3f7;
        box-shadow: 0 0 30px rgba(79, 195, 247, 0.3);
    }
    .modal-close {
        position: absolute;
        top: 10px;
        right: 15px;
        background: none;
        border: none;
        color: #fff;
        font-size: 2rem;
        cursor: pointer;
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    .modal-close:hover {
        opacity: 1;
    }
    .stats-modal-content {
        width: 400px;
    }
    .stats-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .stats-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        border: 3px solid #4fc3f7;
    }
    .stats-player-info h3 {
        margin: 0;
        color: #fff;
        font-size: 1.3rem;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    .stat-box {
        background: rgba(0,0,0,0.3);
        padding: 10px;
        border-radius: 8px;
        text-align: center;
    }
    .stat-label {
        display: block;
        color: #aaa;
        font-size: 0.75rem;
        margin-bottom: 5px;
    }
    .stat-value {
        display: block;
        color: #4fc3f7;
        font-size: 1.1rem;
        font-weight: bold;
    }
    .radar-container {
        display: flex;
        justify-content: center;
        margin: 20px 0;
        background: rgba(0,0,0,0.2);
        border-radius: 10px;
        padding: 15px;
    }
    .history-section h4 {
        color: #fff;
        margin: 15px 0 10px;
        font-size: 1rem;
    }
    .history-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .history-item {
        background: rgba(0,0,0,0.2);
        padding: 8px;
        border-radius: 6px;
    }
    .history-label {
        display: block;
        color: #888;
        font-size: 0.7rem;
    }
    .history-value {
        display: block;
        color: #fff;
        font-size: 0.9rem;
    }
    .stats-actions {
        margin-top: 20px;
        display: flex;
        justify-content: center;
    }
    .btn-chat {
        background: linear-gradient(135deg, #4fc3f7, #0288d1);
        color: #fff;
        border: none;
        padding: 10px 25px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
    }
    .chat-modal-content {
        width: 400px;
        height: 500px;
        display: flex;
        flex-direction: column;
    }
    .chat-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .chat-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid #4fc3f7;
    }
    .chat-player-info h3 {
        margin: 0;
        color: #fff;
        font-size: 1rem;
    }
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 10px 0;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .chat-loading {
        color: #888;
        text-align: center;
        padding: 20px;
    }
    .chat-message {
        max-width: 80%;
        padding: 8px 12px;
        border-radius: 12px;
        font-size: 0.9rem;
    }
    .chat-message.mine {
        align-self: flex-end;
        background: #4fc3f7;
        color: #000;
    }
    .chat-message.theirs {
        align-self: flex-start;
        background: rgba(255,255,255,0.1);
        color: #fff;
    }
    .chat-message .time {
        display: block;
        font-size: 0.65rem;
        opacity: 0.7;
        margin-top: 3px;
    }
    .chat-input-area {
        display: flex;
        gap: 10px;
        padding-top: 10px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    #chat-input {
        flex: 1;
        background: rgba(0,0,0,0.3);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 8px;
        padding: 10px;
        color: #fff;
        font-size: 0.9rem;
    }
    .btn-send {
        background: #4fc3f7;
        color: #000;
        border: none;
        padding: 10px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
    }
    .no-messages {
        color: #666;
        text-align: center;
        padding: 30px;
        font-style: italic;
    }
</style>

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
    
    let currentStatsPlayerId = null;
    let currentChatPlayerId = null;
    let currentChatPlayerName = null;
    
    async function showPlayerStats(playerId, playerName) {
        currentStatsPlayerId = playerId;
        document.getElementById('stats-modal').style.display = 'flex';
        
        document.getElementById('stats-player-name').textContent = playerName;
        document.getElementById('stats-level').textContent = '-';
        document.getElementById('stats-division').textContent = '-';
        document.getElementById('stats-wins').textContent = '-';
        document.getElementById('stats-losses').textContent = '-';
        document.getElementById('stats-winrate').textContent = '-';
        document.getElementById('stats-efficiency').textContent = '-';
        
        try {
            const response = await fetch(`/lobby/player-stats/${playerId}`);
            const data = await response.json();
            
            if (data.success) {
                const avatar = data.player.avatar || 'default';
                const avatarSrc = avatar.includes('/') || avatar.includes('.png') 
                    ? `/${avatar.replace(/\.png$/, '')}.png`
                    : `/images/avatars/standard/${avatar}.png`;
                document.getElementById('stats-avatar').src = avatarSrc;
                document.getElementById('stats-player-code').textContent = data.player.player_code;
                
                document.getElementById('stats-level').textContent = data.stats.level;
                document.getElementById('stats-division').textContent = data.stats.division;
                document.getElementById('stats-wins').textContent = data.stats.wins;
                document.getElementById('stats-losses').textContent = data.stats.losses;
                document.getElementById('stats-winrate').textContent = data.stats.win_rate + '%';
                document.getElementById('stats-efficiency').textContent = data.stats.efficiency + '%';
                
                document.getElementById('history-matches').textContent = data.history.matches_together;
                document.getElementById('history-wins').textContent = data.history.wins_against;
                document.getElementById('history-losses').textContent = data.history.losses_against;
                document.getElementById('history-last').textContent = data.history.last_played;
                
                drawRadarChart(data.radar_data);
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error loading stats:', error);
            showToast('{{ __("Erreur de chargement") }}');
        }
    }
    
    function drawRadarChart(radarData) {
        const canvas = document.getElementById('stats-radar');
        const ctx = canvas.getContext('2d');
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = 80;
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        const labels = Object.keys(radarData);
        const values = Object.values(radarData);
        const numPoints = labels.length;
        const angleStep = (2 * Math.PI) / numPoints;
        
        for (let level = 1; level <= 5; level++) {
            ctx.beginPath();
            ctx.strokeStyle = 'rgba(255,255,255,0.1)';
            for (let i = 0; i <= numPoints; i++) {
                const angle = (i % numPoints) * angleStep - Math.PI / 2;
                const r = (level / 5) * radius;
                const x = centerX + r * Math.cos(angle);
                const y = centerY + r * Math.sin(angle);
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            }
            ctx.closePath();
            ctx.stroke();
        }
        
        ctx.beginPath();
        ctx.fillStyle = 'rgba(79, 195, 247, 0.3)';
        ctx.strokeStyle = '#4fc3f7';
        ctx.lineWidth = 2;
        for (let i = 0; i <= numPoints; i++) {
            const angle = (i % numPoints) * angleStep - Math.PI / 2;
            const value = values[i % numPoints] || 0;
            const r = (value / 100) * radius;
            const x = centerX + r * Math.cos(angle);
            const y = centerY + r * Math.sin(angle);
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        }
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
        
        ctx.fillStyle = '#fff';
        ctx.font = '10px Arial';
        ctx.textAlign = 'center';
        for (let i = 0; i < numPoints; i++) {
            const angle = i * angleStep - Math.PI / 2;
            const x = centerX + (radius + 15) * Math.cos(angle);
            const y = centerY + (radius + 15) * Math.sin(angle);
            ctx.fillText(labels[i], x, y + 3);
        }
    }
    
    function closeStatsModal() {
        document.getElementById('stats-modal').style.display = 'none';
        currentStatsPlayerId = null;
    }
    
    function openPlayerChatFromStats() {
        if (currentStatsPlayerId) {
            const playerName = document.getElementById('stats-player-name').textContent;
            closeStatsModal();
            openPlayerChat(currentStatsPlayerId, playerName);
        }
    }
    
    async function openPlayerChat(playerId, playerName) {
        console.log('[Chat] openPlayerChat called for player:', playerId, playerName);
        if (playerId === currentPlayerId) {
            showToast('{{ __("Vous ne pouvez pas vous envoyer de message") }}');
            return;
        }
        
        currentChatPlayerId = playerId;
        currentChatPlayerName = playerName;
        
        console.log('[Chat] Opening chat modal');
        document.getElementById('chat-modal').style.display = 'flex';
        document.getElementById('chat-player-name').textContent = playerName;
        document.getElementById('chat-messages').innerHTML = '<div class="chat-loading">{{ __("Chargement...") }}</div>';
        document.getElementById('chat-input').value = '';
        
        try {
            const response = await fetch(`/chat/conversation/${playerId}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.contact && data.contact.avatar_url) {
                    const avatar = data.contact.avatar_url;
                    const avatarSrc = avatar.includes('/') ? `/${avatar}` : `/images/avatars/standard/${avatar}.png`;
                    document.getElementById('chat-avatar').src = avatarSrc;
                }
                document.getElementById('chat-player-code').textContent = data.contact?.player_code || '';
                
                displayChatMessages(data.messages || []);
            } else {
                document.getElementById('chat-messages').innerHTML = '<div class="no-messages">{{ __("Erreur de chargement") }}</div>';
            }
        } catch (error) {
            console.error('Error loading chat:', error);
            document.getElementById('chat-messages').innerHTML = '<div class="no-messages">{{ __("Erreur de connexion") }}</div>';
        }
        
        document.getElementById('chat-input').addEventListener('keypress', handleChatKeypress);
    }
    
    function handleChatKeypress(e) {
        if (e.key === 'Enter') {
            sendChatMessage();
        }
    }
    
    function displayChatMessages(messages) {
        const container = document.getElementById('chat-messages');
        
        if (!messages || messages.length === 0) {
            container.innerHTML = '<div class="no-messages">{{ __("Aucun message. Dites bonjour !") }}</div>';
            return;
        }
        
        let html = '';
        messages.forEach(msg => {
            const isMine = msg.is_mine;
            html += `
                <div class="chat-message ${isMine ? 'mine' : 'theirs'}">
                    ${escapeHtml(msg.message)}
                    <span class="time">${msg.time_ago || ''}</span>
                </div>
            `;
        });
        
        container.innerHTML = html;
        container.scrollTop = container.scrollHeight;
    }
    
    async function sendChatMessage() {
        const input = document.getElementById('chat-input');
        const message = input.value.trim();
        
        if (!message || !currentChatPlayerId) return;
        
        input.value = '';
        
        try {
            const response = await fetch('/chat/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    receiver_id: currentChatPlayerId,
                    message: message
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const container = document.getElementById('chat-messages');
                const noMessages = container.querySelector('.no-messages');
                if (noMessages) noMessages.remove();
                
                const msgDiv = document.createElement('div');
                msgDiv.className = 'chat-message mine';
                msgDiv.innerHTML = `${escapeHtml(message)}<span class="time">{{ __("À l'instant") }}</span>`;
                container.appendChild(msgDiv);
                container.scrollTop = container.scrollHeight;
            } else {
                showToast(data.message || '{{ __("Erreur d\'envoi") }}');
            }
        } catch (error) {
            console.error('Error sending message:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    function closeChatModal() {
        document.getElementById('chat-modal').style.display = 'none';
        document.getElementById('chat-input').removeEventListener('keypress', handleChatKeypress);
        currentChatPlayerId = null;
        currentChatPlayerName = null;
    }
    
    let micStates = {};
    let voiceEnabled = false;
    const lobbyMode = '{{ $mode }}';
    const voiceEnabledModes = ['duo', 'league_individual', 'league_team'];
    const isVoiceSupported = voiceEnabledModes.includes(lobbyMode);
    
    micStates[currentPlayerId] = false;
    
    function toggleMic(playerId) {
        console.log('[Mic] toggleMic called for player:', playerId);
        const btn = document.getElementById('mic-btn-' + playerId);
        if (!btn) {
            console.log('[Mic] Button not found');
            return;
        }
        
        console.log('[Mic] isVoiceSupported:', isVoiceSupported, 'voiceEnabled:', voiceEnabled);
        
        if (!isVoiceSupported) {
            showToast('{{ __("Audio non disponible pour ce mode") }}');
            return;
        }
        
        if (playerId === currentPlayerId) {
            if (!voiceEnabled) {
                initVoiceChat();
            } else {
                toggleLocalMic();
            }
        } else {
            toggleRemoteAudio(playerId);
        }
    }
    
    async function initVoiceChat() {
        console.log('[Mic] initVoiceChat called');
        const btn = document.getElementById('mic-btn-' + currentPlayerId);
        if (!btn) {
            console.log('[Mic] Button not found for current player');
            return;
        }
        
        console.log('[Mic] window.webrtcManager exists:', !!window.webrtcManager);
        
        if (!window.webrtcManager) {
            console.log('[Mic] WebRTC Manager not ready, waiting...');
            showToast('{{ __("Chargement en cours, réessayez...") }}');
            return;
        }
        
        btn.classList.add('mic-connecting');
        console.log('[Mic] Requesting microphone permission...');
        
        try {
            const hasPermission = await requestMicPermission();
            console.log('[Mic] Permission result:', hasPermission);
            if (!hasPermission) {
                btn.classList.remove('mic-connecting');
                showToast('{{ __("Permission micro refusée") }}');
                return;
            }
            
            console.log('[Mic] Starting voice chat via WebRTC Manager...');
            await window.webrtcManager.startVoiceChat();
            console.log('[Mic] Voice chat started successfully');
            
            voiceEnabled = true;
            micStates[currentPlayerId] = true;
            btn.classList.remove('mic-connecting');
            btn.classList.add('active');
            btn.classList.remove('muted');
            showToast('{{ __("Micro activé") }}');
            
        } catch (error) {
            console.error('[Mic] Voice init error:', error);
            voiceEnabled = false;
            micStates[currentPlayerId] = false;
            btn.classList.remove('mic-connecting');
            btn.classList.remove('active');
            btn.classList.add('muted');
            showToast('{{ __("Erreur d\'initialisation audio: ") }}' + error.message);
        }
    }
    
    async function requestMicPermission() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            stream.getTracks().forEach(track => track.stop());
            return true;
        } catch (error) {
            console.error('Mic permission denied:', error);
            return false;
        }
    }
    
    function toggleLocalMic() {
        const btn = document.getElementById('mic-btn-' + currentPlayerId);
        if (!btn || !window.webrtcManager) return;
        
        micStates[currentPlayerId] = !micStates[currentPlayerId];
        
        if (micStates[currentPlayerId]) {
            window.webrtcManager.unmute();
            btn.classList.add('active');
            btn.classList.remove('muted');
            showToast('{{ __("Micro activé") }}');
        } else {
            window.webrtcManager.mute();
            btn.classList.remove('active');
            btn.classList.add('muted');
            showToast('{{ __("Micro désactivé") }}');
        }
    }
    
    function toggleRemoteAudio(playerId) {
        const btn = document.getElementById('mic-btn-' + playerId);
        if (!btn) return;
        
        micStates[playerId] = !micStates[playerId];
        
        if (window.webrtcManager) {
            window.webrtcManager.setRemoteAudioEnabled(playerId, !micStates[playerId]);
        }
        
        if (micStates[playerId]) {
            btn.classList.add('muted');
            showToast('{{ __("Son désactivé") }}');
        } else {
            btn.classList.remove('muted');
            showToast('{{ __("Son activé") }}');
        }
    }
    
    function updateSpeakingIndicator(playerId, isSpeaking) {
        const btn = document.getElementById('mic-btn-' + playerId);
        const card = document.querySelector(`.player-card[data-player-id="${playerId}"]`);
        
        if (btn) {
            if (isSpeaking) {
                btn.classList.add('speaking');
            } else {
                btn.classList.remove('speaking');
            }
        }
        
        if (card) {
            if (isSpeaking) {
                card.classList.add('speaking');
            } else {
                card.classList.remove('speaking');
            }
        }
    }
    
    function updateRemoteMicState(playerId, isActive) {
        const btn = document.getElementById('mic-btn-' + playerId);
        if (btn) {
            if (isActive) {
                btn.classList.add('remote-active');
                btn.style.background = 'rgba(76, 175, 80, 0.3)';
            } else {
                btn.classList.remove('remote-active');
                btn.style.background = '';
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
    
    function showConfirmModal(message) {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirmModal');
            const messageEl = document.getElementById('confirmModalMessage');
            const confirmBtn = document.getElementById('confirmModalConfirm');
            const cancelBtn = document.getElementById('confirmModalCancel');
            
            messageEl.textContent = message;
            modal.classList.add('show');
            
            const cleanup = () => {
                modal.classList.remove('show');
                confirmBtn.removeEventListener('click', onConfirm);
                cancelBtn.removeEventListener('click', onCancel);
            };
            
            const onConfirm = () => {
                cleanup();
                resolve(true);
            };
            
            const onCancel = () => {
                cleanup();
                resolve(false);
            };
            
            confirmBtn.addEventListener('click', onConfirm);
            cancelBtn.addEventListener('click', onCancel);
        });
    }
    
    async function leaveLobby() {
        const confirmed = await showConfirmModal('{{ __("Voulez-vous vraiment quitter le salon ?") }}');
        if (!confirmed) {
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
            
            window.location.href = '/duo';
        } catch (error) {
            console.error('Error leaving lobby:', error);
            window.location.href = '/duo';
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
        minimum: '{{ __("minimum") }}',
        audioNotAvailable: '{{ __("Audio non disponible") }}'
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
                        ${isVoiceSupported ? `<button class="player-action-btn" 
                                id="mic-btn-${playerId}" 
                                data-player-id="${playerId}"
                                data-action="mic"
                                title="${translations.micro}">🎙️</button>` : `<button class="player-action-btn" 
                                style="opacity: 0.3; cursor: not-allowed;"
                                title="${translations.audioNotAvailable}" disabled>🎙️</button>`}
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
                setTimeout(() => window.location.href = '/duo', 2000);
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
    
    document.getElementById('stats-modal').addEventListener('click', function(e) {
        if (e.target === this) closeStatsModal();
    });
    
    document.getElementById('chat-modal').addEventListener('click', function(e) {
        if (e.target === this) closeChatModal();
    });
</script>

@if(in_array($mode, ['duo', 'league_individual', 'league_team']))
<script type="module">
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { getFirestore, doc, collection, addDoc, onSnapshot, query, where, deleteDoc, getDocs, setDoc, serverTimestamp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js';

const firebaseConfig = {
    apiKey: "AIzaSyAB5-A0NsX9I9eFX76ZBYQQG_bqWp_dHw",
    authDomain: "strategybuzzergame.firebaseapp.com",
    projectId: "strategybuzzergame",
    storageBucket: "strategybuzzergame.appspot.com",
    messagingSenderId: "68047817391",
    appId: "1:68047817391:web:ba6b3bc148ef187bfeae9a"
};

const app = initializeApp(firebaseConfig, 'webrtc-app');
const db = getFirestore(app);

class WebRTCManager {
    constructor(lobbyCode, currentPlayerId, mode, teamId = null) {
        this.lobbyCode = lobbyCode;
        this.currentPlayerId = currentPlayerId;
        this.mode = mode;
        this.teamId = teamId;
        this.peerConnections = {};
        this.localStream = null;
        this.remoteAudioElements = {};
        this.audioContext = null;
        this.analyser = null;
        this.isMuted = false;
        this.unsubscribers = [];
        
        this.iceServers = [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' },
            { urls: 'stun:stun3.l.google.com:19302' },
            { urls: 'stun:stun4.l.google.com:19302' },
            { urls: 'stun:stun.relay.metered.ca:80' },
            { 
                urls: 'turn:global.relay.metered.ca:80',
                username: 'free',
                credential: 'free'
            },
            { 
                urls: 'turn:global.relay.metered.ca:443',
                username: 'free',
                credential: 'free'
            }
        ];
        
        this.sessionId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    }
    
    getSignalingPath() {
        if (this.mode === 'league_team' && this.teamId) {
            return `lobbies/${this.lobbyCode}/teams/${this.teamId}/webrtc`;
        }
        return `lobbies/${this.lobbyCode}/webrtc`;
    }
    
    getPresencePath() {
        if (this.mode === 'league_team' && this.teamId) {
            return `lobbies/${this.lobbyCode}/teams/${this.teamId}/voice_presence`;
        }
        return `lobbies/${this.lobbyCode}/voice_presence`;
    }
    
    async startVoiceChat() {
        console.log('[WebRTC] startVoiceChat called');
        try {
            console.log('[WebRTC] Requesting media stream...');
            this.localStream = await navigator.mediaDevices.getUserMedia({ 
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                } 
            });
            console.log('[WebRTC] Media stream obtained:', this.localStream.id);
            
            this.setupVoiceActivityDetection();
            console.log('[WebRTC] Voice activity detection setup complete');
            
            await this.updatePresence(true, false);
            console.log('[WebRTC] Presence updated');
            
            this.listenForSignaling();
            console.log('[WebRTC] Signaling listener started');
            
            this.listenForPresence();
            console.log('[WebRTC] Presence listener started');
            
            await this.addTracksToExistingConnections();
            console.log('[WebRTC] Tracks added to existing connections');
            
            console.log('[WebRTC] Voice chat started successfully');
        } catch (error) {
            console.error('[WebRTC] Failed to start voice chat:', error);
            throw error;
        }
    }
    
    async addTracksToExistingConnections() {
        if (!this.localStream) return;
        
        for (const [peerId, pc] of Object.entries(this.peerConnections)) {
            if (pc.connectionState === 'closed') continue;
            
            const senders = pc.getSenders();
            const audioSender = senders.find(s => s.track?.kind === 'audio' || !s.track);
            const localAudioTrack = this.localStream.getAudioTracks()[0];
            
            if (!localAudioTrack) continue;
            
            let needsRenegotiation = false;
            
            if (audioSender && !audioSender.track) {
                console.log(`Replacing empty audio sender for ${peerId}`);
                await audioSender.replaceTrack(localAudioTrack);
                needsRenegotiation = true;
            } else if (!audioSender) {
                console.log(`Adding audio track to connection with ${peerId}`);
                pc.addTrack(localAudioTrack, this.localStream);
                needsRenegotiation = true;
            }
            
            if (needsRenegotiation && this.currentPlayerId < parseInt(peerId)) {
                try {
                    console.log(`Initiating renegotiation with ${peerId}`);
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    await this.sendSignal(peerId, 'offer', offer.sdp);
                } catch (error) {
                    console.error(`Error renegotiating with ${peerId}:`, error);
                }
            }
        }
    }
    
    setupVoiceActivityDetection() {
        if (!this.localStream) return;
        
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const source = this.audioContext.createMediaStreamSource(this.localStream);
        this.analyser = this.audioContext.createAnalyser();
        this.analyser.fftSize = 512;
        this.analyser.smoothingTimeConstant = 0.4;
        source.connect(this.analyser);
        
        const dataArray = new Uint8Array(this.analyser.frequencyBinCount);
        let speakingState = false;
        let silenceTimeout = null;
        
        const checkLevel = () => {
            if (!this.analyser || this.isMuted) {
                if (speakingState) {
                    speakingState = false;
                    this.onSpeakingChange(false);
                }
                requestAnimationFrame(checkLevel);
                return;
            }
            
            this.analyser.getByteFrequencyData(dataArray);
            const average = dataArray.reduce((a, b) => a + b, 0) / dataArray.length;
            const isSpeaking = average > 15;
            
            if (isSpeaking && !speakingState) {
                if (silenceTimeout) {
                    clearTimeout(silenceTimeout);
                    silenceTimeout = null;
                }
                speakingState = true;
                this.onSpeakingChange(true);
            } else if (!isSpeaking && speakingState && !silenceTimeout) {
                silenceTimeout = setTimeout(() => {
                    speakingState = false;
                    this.onSpeakingChange(false);
                    silenceTimeout = null;
                }, 300);
            }
            
            requestAnimationFrame(checkLevel);
        };
        
        checkLevel();
    }
    
    onSpeakingChange(isSpeaking) {
        if (typeof updateSpeakingIndicator === 'function') {
            updateSpeakingIndicator(this.currentPlayerId, isSpeaking);
        }
        this.updatePresence(!this.isMuted, isSpeaking);
    }
    
    async updatePresence(micEnabled, speaking) {
        try {
            const presencePath = this.getPresencePath();
            console.log('[WebRTC] updatePresence - path:', presencePath, 'micEnabled:', micEnabled, 'speaking:', speaking);
            const presenceRef = doc(db, presencePath, String(this.currentPlayerId));
            await setDoc(presenceRef, {
                odPlayerId: this.currentPlayerId,
                muted: !micEnabled,
                speaking: speaking,
                teamId: this.teamId,
                updatedAt: serverTimestamp()
            }, { merge: true });
            console.log('[WebRTC] Presence updated successfully');
        } catch (error) {
            console.error('[WebRTC] Error updating presence:', error);
        }
    }
    
    listenForPresence() {
        const presencePath = this.getPresencePath();
        console.log('[WebRTC] listenForPresence - path:', presencePath);
        const presenceRef = collection(db, presencePath);
        
        const unsubscribe = onSnapshot(presenceRef, (snapshot) => {
            console.log('[WebRTC] Presence snapshot received, changes:', snapshot.docChanges().length);
            snapshot.docChanges().forEach((change) => {
                const data = change.doc.data();
                const odPlayerId = data.odPlayerId || parseInt(change.doc.id);
                
                if (odPlayerId === this.currentPlayerId) return;
                
                if (change.type === 'added' || change.type === 'modified') {
                    if (typeof updateSpeakingIndicator === 'function') {
                        updateSpeakingIndicator(odPlayerId, data.speaking && !data.muted);
                    }
                    if (typeof updateRemoteMicState === 'function') {
                        updateRemoteMicState(odPlayerId, !data.muted);
                    }
                    
                    if (!this.peerConnections[odPlayerId] && !data.muted) {
                        this.createPeerConnection(odPlayerId, true);
                    }
                } else if (change.type === 'removed') {
                    this.closePeerConnection(odPlayerId);
                    if (typeof updateSpeakingIndicator === 'function') {
                        updateSpeakingIndicator(odPlayerId, false);
                    }
                    if (typeof updateRemoteMicState === 'function') {
                        updateRemoteMicState(odPlayerId, false);
                    }
                }
            });
        });
        
        this.unsubscribers.push(unsubscribe);
    }
    
    listenForSignaling() {
        const signalingPath = this.getSignalingPath();
        console.log('[WebRTC] listenForSignaling - path:', signalingPath);
        const signalingRef = collection(db, signalingPath);
        const q = query(signalingRef, where('to', '==', this.currentPlayerId));
        const startTime = Date.now();
        
        const unsubscribe = onSnapshot(q, (snapshot) => {
            console.log('[WebRTC] Signaling snapshot received, changes:', snapshot.docChanges().length);
            snapshot.docChanges().forEach(async (change) => {
                if (change.type !== 'added') return;
                
                const data = change.doc.data();
                const fromId = data.from;
                
                const docTime = data.createdAt?.toMillis ? data.createdAt.toMillis() : 0;
                if (docTime && docTime < startTime - 5000) {
                    await deleteDoc(change.doc.ref);
                    return;
                }
                
                try {
                    if (data.type === 'offer') {
                        await this.handleOffer(fromId, data.sdp);
                    } else if (data.type === 'answer') {
                        await this.handleAnswer(fromId, data.sdp);
                    } else if (data.type === 'candidate') {
                        await this.handleCandidate(fromId, data.candidate);
                    }
                } finally {
                    await deleteDoc(change.doc.ref);
                }
            });
        });
        
        this.unsubscribers.push(unsubscribe);
    }
    
    async createPeerConnection(peerId, initiator = false) {
        if (this.peerConnections[peerId]) {
            const existingPc = this.peerConnections[peerId];
            if (existingPc.connectionState === 'connected' || 
                existingPc.connectionState === 'connecting' ||
                existingPc.connectionState === 'new') {
                console.log(`Reusing existing connection with ${peerId}, state: ${existingPc.connectionState}`);
                return existingPc;
            }
            if (existingPc.connectionState === 'closed' || existingPc.connectionState === 'failed') {
                console.log(`Removing stale connection with ${peerId}, state: ${existingPc.connectionState}`);
                delete this.peerConnections[peerId];
            } else {
                return existingPc;
            }
        }
        
        console.log(`Creating peer connection with ${peerId}, initiator: ${initiator}, hasLocalStream: ${!!this.localStream}`);
        
        const pc = new RTCPeerConnection({ iceServers: this.iceServers });
        this.peerConnections[peerId] = pc;
        
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                console.log(`Adding local track to connection with ${peerId}:`, track.kind);
                pc.addTrack(track, this.localStream);
            });
        }
        
        pc.ontrack = (event) => {
            console.log(`Received remote track from ${peerId}:`, event.track.kind);
            if (event.streams && event.streams[0]) {
                this.handleRemoteTrack(peerId, event.streams[0]);
            }
        };
        
        pc.onicecandidate = async (event) => {
            if (event.candidate) {
                console.log(`Sending ICE candidate to ${peerId}`);
                await this.sendSignal(peerId, 'candidate', null, event.candidate.toJSON());
            }
        };
        
        pc.onicegatheringstatechange = () => {
            console.log(`ICE gathering state with ${peerId}: ${pc.iceGatheringState}`);
        };
        
        pc.oniceconnectionstatechange = () => {
            console.log(`ICE connection state with ${peerId}: ${pc.iceConnectionState}`);
        };
        
        let disconnectTimeout = null;
        pc.onconnectionstatechange = () => {
            console.log(`Connection state with ${peerId}: ${pc.connectionState}`);
            if (pc.connectionState === 'failed') {
                console.log(`Connection failed with ${peerId}`);
                if (disconnectTimeout) clearTimeout(disconnectTimeout);
                this.closePeerConnection(peerId);
            } else if (pc.connectionState === 'disconnected') {
                console.log(`Connection disconnected with ${peerId}, will attempt recovery in 5s...`);
                if (disconnectTimeout) clearTimeout(disconnectTimeout);
                disconnectTimeout = setTimeout(() => {
                    if (pc.connectionState === 'disconnected' && this.localStream && !this.isMuted) {
                        console.log(`Attempting to recover connection with ${peerId}`);
                        this.closePeerConnection(peerId);
                        this.createPeerConnection(peerId, this.currentPlayerId < parseInt(peerId));
                    }
                }, 5000);
            } else if (pc.connectionState === 'connected') {
                console.log(`Successfully connected to ${peerId}!`);
                if (disconnectTimeout) {
                    clearTimeout(disconnectTimeout);
                    disconnectTimeout = null;
                }
            }
        };
        
        if (initiator) {
            try {
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                console.log(`Sending offer to ${peerId}`);
                await this.sendSignal(peerId, 'offer', offer.sdp);
            } catch (error) {
                console.error('Error creating offer:', error);
            }
        }
        
        return pc;
    }
    
    async handleOffer(fromId, sdp) {
        console.log(`Received offer from ${fromId}`);
        const pc = await this.createPeerConnection(fromId, false);
        
        try {
            await pc.setRemoteDescription(new RTCSessionDescription({ type: 'offer', sdp }));
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await this.sendSignal(fromId, 'answer', answer.sdp);
        } catch (error) {
            console.error('Error handling offer:', error);
        }
    }
    
    async handleAnswer(fromId, sdp) {
        console.log(`Received answer from ${fromId}`);
        const pc = this.peerConnections[fromId];
        if (!pc) return;
        
        try {
            await pc.setRemoteDescription(new RTCSessionDescription({ type: 'answer', sdp }));
        } catch (error) {
            console.error('Error handling answer:', error);
        }
    }
    
    async handleCandidate(fromId, candidateData) {
        const pc = this.peerConnections[fromId];
        if (!pc) return;
        
        try {
            await pc.addIceCandidate(new RTCIceCandidate(candidateData));
        } catch (error) {
            console.error('Error adding ICE candidate:', error);
        }
    }
    
    async sendSignal(toId, type, sdp = null, candidate = null) {
        try {
            const signalingRef = collection(db, this.getSignalingPath());
            await addDoc(signalingRef, {
                from: this.currentPlayerId,
                to: toId,
                type: type,
                sdp: sdp,
                candidate: candidate,
                sessionId: this.sessionId,
                createdAt: serverTimestamp()
            });
        } catch (error) {
            console.error('Error sending signal:', error);
        }
    }
    
    handleRemoteTrack(peerId, stream) {
        console.log(`Handling remote stream from ${peerId}, tracks:`, stream.getTracks().map(t => t.kind));
        
        let audio = this.remoteAudioElements[peerId];
        
        if (!audio) {
            audio = document.createElement('audio');
            audio.id = `remote-audio-${peerId}`;
            audio.autoplay = true;
            audio.playsInline = true;
            audio.style.display = 'none';
            document.body.appendChild(audio);
            this.remoteAudioElements[peerId] = audio;
        }
        
        audio.srcObject = stream;
        
        audio.play().then(() => {
            console.log(`Audio playback started for ${peerId}`);
        }).catch(error => {
            console.warn(`Audio playback failed for ${peerId}, will retry on user interaction:`, error);
            const resumeAudio = () => {
                audio.play().catch(e => console.error('Retry play failed:', e));
                document.removeEventListener('click', resumeAudio);
            };
            document.addEventListener('click', resumeAudio, { once: true });
        });
    }
    
    mute() {
        this.isMuted = true;
        if (this.localStream) {
            this.localStream.getAudioTracks().forEach(track => {
                track.enabled = false;
            });
        }
        this.updatePresence(false, false);
    }
    
    unmute() {
        this.isMuted = false;
        if (this.localStream) {
            this.localStream.getAudioTracks().forEach(track => {
                track.enabled = true;
            });
        }
        this.updatePresence(true, false);
    }
    
    setRemoteAudioEnabled(peerId, enabled) {
        const audio = this.remoteAudioElements[peerId];
        if (audio) {
            audio.muted = !enabled;
        }
    }
    
    closePeerConnection(peerId) {
        const pc = this.peerConnections[peerId];
        if (pc) {
            pc.close();
            delete this.peerConnections[peerId];
        }
        
        const audio = this.remoteAudioElements[peerId];
        if (audio) {
            audio.srcObject = null;
            audio.remove();
            delete this.remoteAudioElements[peerId];
        }
    }
    
    async cleanup() {
        this.unsubscribers.forEach(unsub => unsub());
        this.unsubscribers = [];
        
        Object.keys(this.peerConnections).forEach(peerId => {
            this.closePeerConnection(peerId);
        });
        
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }
        
        if (this.audioContext) {
            this.audioContext.close();
            this.audioContext = null;
        }
        
        try {
            const presenceRef = doc(db, this.getPresencePath(), String(this.currentPlayerId));
            await deleteDoc(presenceRef);
            
            const signalingRef = collection(db, this.getSignalingPath());
            const fromQuery = query(signalingRef, where('from', '==', this.currentPlayerId));
            const toQuery = query(signalingRef, where('to', '==', this.currentPlayerId));
            
            const [fromDocs, toDocs] = await Promise.all([getDocs(fromQuery), getDocs(toQuery)]);
            
            const deletePromises = [];
            fromDocs.forEach(doc => deletePromises.push(deleteDoc(doc.ref)));
            toDocs.forEach(doc => deletePromises.push(deleteDoc(doc.ref)));
            await Promise.all(deletePromises);
        } catch (error) {
            console.error('Error cleaning up signaling:', error);
        }
        
        console.log('Voice chat cleaned up');
    }
}

const lobbyCode = '{{ $lobbyCode }}';
const currentPlayerId = {{ $currentPlayerId }};
const mode = '{{ $mode }}';
const teamId = null;

window.webrtcManager = new WebRTCManager(lobbyCode, currentPlayerId, mode, teamId);
console.log('[WebRTC] Manager assigned to window.webrtcManager:', !!window.webrtcManager);

window.addEventListener('beforeunload', () => {
    if (window.webrtcManager) {
        window.webrtcManager.cleanup();
    }
});

window.dispatchEvent(new CustomEvent('webrtcReady'));
console.log('[WebRTC] Manager initialized for lobby:', lobbyCode, '- Player:', currentPlayerId);
</script>
@endif

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
