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
    
    .player-card.player-offline {
        opacity: 0.5;
        position: relative;
    }
    
    .player-card.player-offline::after {
        content: '{{ __("Reconnexion...") }}';
        position: absolute;
        bottom: 5px;
        right: 10px;
        font-size: 0.7rem;
        color: #ff9800;
        background: rgba(0,0,0,0.5);
        padding: 2px 6px;
        border-radius: 4px;
    }
    
    .player-card.player-online {
        opacity: 1;
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
    
    .player-action-btn.muted-locally {
        background: rgba(255, 193, 7, 0.4);
        color: #FFC107;
    }
    
    .player-action-btn.unavailable {
        background: rgba(100, 100, 100, 0.3);
        color: #888;
        cursor: not-allowed;
        position: relative;
    }
    
    .player-action-btn.unavailable::after {
        content: '🚫';
        position: absolute;
        font-size: 0.6rem;
        bottom: -2px;
        right: -2px;
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
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: #fff;
        text-shadow: 0 0 3px rgba(0,0,0,0.5);
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
    
    .countdown-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease;
    }
    
    .countdown-overlay.show {
        opacity: 1;
        visibility: visible;
    }
    
    .countdown-number {
        font-size: 8rem;
        font-weight: 900;
        color: #fff;
        text-shadow: 0 0 50px rgba(102, 126, 234, 0.8), 0 0 100px rgba(102, 126, 234, 0.5);
        animation: countdownPulse 1s ease-in-out infinite;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .countdown-number.go {
        color: #4CAF50;
        text-shadow: 0 0 50px rgba(76, 175, 80, 0.8), 0 0 100px rgba(76, 175, 80, 0.5);
        animation: goZoom 0.5s ease-out;
    }
    
    .countdown-label {
        font-size: 1.5rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 30px;
        text-transform: uppercase;
        letter-spacing: 4px;
    }
    
    .countdown-precision {
        font-size: 1.2rem;
        color: rgba(255, 255, 255, 0.4);
        margin-top: 20px;
        font-family: monospace;
    }
    
    @keyframes countdownPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    @keyframes goZoom {
        0% { transform: scale(0.5); opacity: 0; }
        50% { transform: scale(1.3); }
        100% { transform: scale(1); opacity: 1; }
    }
</style>

@if(isset($match) && $match && $match->room_id)
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>
<script>
    window.matchRoomId = '{{ $match->room_id }}';
    window.matchLobbyCode = '{{ $match->lobby_code }}';
    window.matchPlayerToken = '{{ $playerToken ?? "" }}';
    window.gameServerUrl = '{{ $gameServerUrl ?? config("services.game_server.url", "ws://localhost:3001") }}';
    window.useSocketIO = true;
</script>
@else
<script>
    window.useSocketIO = false;
</script>
@endif

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
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px;">
            <div style="text-align: center;">
                <label style="display: block; font-size: 0.85rem; color: rgba(255,255,255,0.7); margin-bottom: 5px;">🎯 {{ __('Thème') }}</label>
            </div>
            <div style="text-align: center;">
                <label style="display: block; font-size: 0.85rem; color: rgba(255,255,255,0.7); margin-bottom: 5px;">❓ {{ __('Questions') }}</label>
            </div>
            
            <select id="theme-select" onchange="updateSettings()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: #fff; font-size: 1rem;">
                <option value="Culture générale" {{ ($settings['theme'] ?? '') == 'Culture générale' ? 'selected' : '' }}>{{ __('Général') }}</option>
                <option value="Géographie" {{ ($settings['theme'] ?? '') == 'Géographie' ? 'selected' : '' }}>{{ __('Géographie') }}</option>
                <option value="Histoire" {{ ($settings['theme'] ?? '') == 'Histoire' ? 'selected' : '' }}>{{ __('Histoire') }}</option>
                <option value="Sports" {{ ($settings['theme'] ?? '') == 'Sports' ? 'selected' : '' }}>{{ __('Sports') }}</option>
                <option value="Sciences" {{ ($settings['theme'] ?? '') == 'Sciences' ? 'selected' : '' }}>{{ __('Sciences') }}</option>
                <option value="Cinéma" {{ ($settings['theme'] ?? '') == 'Cinéma' ? 'selected' : '' }}>{{ __('Cinéma') }}</option>
                <option value="Art" {{ ($settings['theme'] ?? '') == 'Art' ? 'selected' : '' }}>{{ __('Art') }}</option>
                <option value="Animaux" {{ ($settings['theme'] ?? '') == 'Animaux' ? 'selected' : '' }}>{{ __('Animaux') }}</option>
                <option value="Cuisine" {{ ($settings['theme'] ?? '') == 'Cuisine' ? 'selected' : '' }}>{{ __('Cuisine') }}</option>
            </select>
            <select id="questions-select" onchange="updateSettings()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: #fff; font-size: 1rem;">
                @foreach([5, 7, 10, 15, 20] as $num)
                    <option value="{{ $num }}" {{ ($settings['nb_questions'] ?? 10) == $num ? 'selected' : '' }}>{{ $num }}</option>
                @endforeach
            </select>
        </div>
        
        <div style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 15px;">
            <span class="info-badge player-count-badge">👥 <span id="player-count-host">{{ count($players) }}</span>/{{ $maxPlayers }}</span>
        </div>
        
        <div id="bet-negotiation-section" style="text-align: center; margin-top: 15px;">
            <div id="bet-proposal-ui">
                <button id="bet-toggle-btn" onclick="toggleBetDropdown()" style="background: rgba(255,193,7,0.2); border: 1px solid rgba(255,193,7,0.4); color: #ffc107; padding: 12px 24px; border-radius: 10px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.2s;">
                    <span id="bet-label">{{ __('Proposer une mise') }}</span>
                </button>
                <div id="bet-dropdown" style="display: none; position: absolute; left: 50%; transform: translateX(-50%); background: rgba(20,20,40,0.98); border: 1px solid rgba(255,193,7,0.3); border-radius: 10px; margin-top: 8px; overflow: hidden; z-index: 100; min-width: 150px; box-shadow: 0 8px 25px rgba(0,0,0,0.5);">
                    <div class="bet-option" data-bet="0" onclick="proposeBet(0)" style="padding: 12px 20px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: background 0.2s;">
                        <span style="color: #fff;">{{ __('Sans mise') }}</span>
                    </div>
                    @foreach([5, 10, 25, 50, 100] as $bet)
                    <div class="bet-option {{ ($userCompetenceCoins ?? 0) < $bet ? 'disabled' : '' }}" data-bet="{{ $bet }}" onclick="{{ ($userCompetenceCoins ?? 0) >= $bet ? 'proposeBet('.$bet.')' : '' }}" style="padding: 12px 20px; cursor: {{ ($userCompetenceCoins ?? 0) >= $bet ? 'pointer' : 'not-allowed' }}; display: flex; align-items: center; gap: 8px; opacity: {{ ($userCompetenceCoins ?? 0) >= $bet ? '1' : '0.4' }}; transition: background 0.2s;">
                        <img src="{{ asset('images/skill_coin.png') }}" alt="" style="width: 18px; height: 18px;">
                        <span style="color: #ffc107; font-weight: bold;">{{ $bet }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            
            <div id="bet-pending-ui" style="display: none;">
                <div style="background: rgba(255,193,7,0.15); border: 1px solid rgba(255,193,7,0.3); border-radius: 10px; padding: 15px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 10px;">
                        <img src="{{ asset('images/skill_coin.png') }}" alt="" style="width: 24px; height: 24px;">
                        <span id="bet-pending-amount" style="color: #ffc107; font-size: 1.3rem; font-weight: bold;">0</span>
                        <span style="color: rgba(255,255,255,0.7);">{{ __('proposé') }}</span>
                    </div>
                    <div id="bet-pending-status" style="color: rgba(255,255,255,0.6); font-size: 0.9rem;">
                        {{ __('En attente de réponse...') }}
                    </div>
                    <button onclick="cancelBet()" style="margin-top: 10px; background: rgba(244,67,54,0.2); border: 1px solid rgba(244,67,54,0.4); color: #f44336; padding: 8px 16px; border-radius: 8px; font-size: 0.9rem; cursor: pointer;">
                        {{ __('Annuler') }}
                    </button>
                </div>
            </div>
            
            <div id="bet-accepted-ui" style="display: none;">
                <div style="background: rgba(76,175,80,0.15); border: 1px solid rgba(76,175,80,0.3); border-radius: 10px; padding: 15px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <span style="color: #4CAF50; font-size: 1.2rem;">✓</span>
                        <img src="{{ asset('images/skill_coin.png') }}" alt="" style="width: 24px; height: 24px;">
                        <span id="bet-accepted-amount" style="color: #ffc107; font-size: 1.3rem; font-weight: bold;">0</span>
                        <span style="color: #4CAF50;">{{ __('accepté') }}</span>
                    </div>
                </div>
            </div>
            
            <div id="host-bet-response-ui" style="display: none; text-align: center; padding: 20px; background: rgba(255,193,7,0.1); border: 1px solid rgba(255,193,7,0.3); border-radius: 10px;">
                <div style="margin-bottom: 15px;">
                    <span id="host-proposer-name" style="color: #fff; font-weight: bold;"></span>
                    <span style="color: rgba(255,255,255,0.7);"> {{ __('propose une mise de') }} </span>
                    <div style="display: inline-flex; align-items: center; gap: 6px; margin-left: 5px;">
                        <img src="{{ asset('images/skill_coin.png') }}" alt="" style="width: 22px; height: 22px;">
                        <span id="host-bet-amount" style="color: #ffc107; font-size: 1.4rem; font-weight: bold;">0</span>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                    <button onclick="acceptBet()" id="host-accept-btn" style="background: linear-gradient(135deg, #4CAF50, #45a049); border: none; color: #fff; padding: 12px 24px; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.2s;">
                        ✓ {{ __('Accepter') }}
                    </button>
                    <button onclick="showRaiseModal()" id="host-raise-btn" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none; color: #fff; padding: 12px 24px; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.2s;">
                        ↑ {{ __('Relancer') }}
                    </button>
                    <button onclick="refuseBet()" id="host-refuse-btn" style="background: rgba(244,67,54,0.2); border: 1px solid rgba(244,67,54,0.4); color: #f44336; padding: 12px 24px; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.2s;">
                        ✗ {{ __('Refuser') }}
                    </button>
                </div>
                
                <div id="host-insufficient-coins" style="display: none; margin-top: 10px; color: #f44336; font-size: 0.9rem;">
                    {{ __('Vous n\'avez pas assez de pièces pour cette mise') }}
                </div>
            </div>
            
            <input type="hidden" id="bet-select" value="{{ $settings['bet_amount'] ?? 0 }}">
        </div>
    </div>
    @else
    <div class="lobby-info">
        <span class="info-badge">🎯 {{ $settings['theme'] ?? 'Culture générale' }}</span>
        <span class="info-badge">❓ {{ $settings['nb_questions'] ?? 10 }} {{ __('questions') }}</span>
        <span class="info-badge player-count-badge">👥 <span id="player-count-guest">{{ count($players) }}</span>/{{ $maxPlayers }}</span>
    </div>
    
    <div id="guest-bet-negotiation" style="margin-bottom: 25px;">
        <div id="guest-no-bet" style="text-align: center; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 10px;">
            <span style="color: rgba(255,255,255,0.6);">🎲 {{ __('Aucune mise proposée') }}</span>
        </div>
        
        <div id="guest-bet-proposal" style="display: none; text-align: center; padding: 20px; background: rgba(255,193,7,0.1); border: 1px solid rgba(255,193,7,0.3); border-radius: 10px;">
            <div style="margin-bottom: 15px;">
                <span id="guest-proposer-name" style="color: #fff; font-weight: bold;"></span>
                <span style="color: rgba(255,255,255,0.7);"> {{ __('propose une mise de') }} </span>
                <div style="display: inline-flex; align-items: center; gap: 6px; margin-left: 5px;">
                    <img src="{{ asset('images/skill_coin.png') }}" alt="" style="width: 22px; height: 22px;">
                    <span id="guest-bet-amount" style="color: #ffc107; font-size: 1.4rem; font-weight: bold;">0</span>
                </div>
            </div>
            
            <div style="display: flex; justify-content: center; gap: 10px; flex-wrap: wrap;">
                <button onclick="acceptBet()" id="guest-accept-btn" style="background: linear-gradient(135deg, #4CAF50, #45a049); border: none; color: #fff; padding: 12px 24px; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.2s;">
                    ✓ {{ __('Accepter') }}
                </button>
                <button onclick="showRaiseModal()" id="guest-raise-btn" style="background: linear-gradient(135deg, #667eea, #764ba2); border: none; color: #fff; padding: 12px 24px; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.2s;">
                    ↑ {{ __('Relancer') }}
                </button>
                <button onclick="refuseBet()" id="guest-refuse-btn" style="background: rgba(244,67,54,0.2); border: 1px solid rgba(244,67,54,0.4); color: #f44336; padding: 12px 24px; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.2s;">
                    ✗ {{ __('Refuser') }}
                </button>
            </div>
            
            <div id="guest-insufficient-coins" style="display: none; margin-top: 10px; color: #f44336; font-size: 0.9rem;">
                {{ __('Vous n\'avez pas assez de pièces pour cette mise') }}
            </div>
        </div>
        
        <div id="guest-bet-accepted" style="display: none; text-align: center; padding: 15px; background: rgba(76,175,80,0.15); border: 1px solid rgba(76,175,80,0.3); border-radius: 10px;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span style="color: #4CAF50; font-size: 1.2rem;">✓</span>
                <img src="{{ asset('images/skill_coin.png') }}" alt="" style="width: 24px; height: 24px;">
                <span id="guest-accepted-amount" style="color: #ffc107; font-size: 1.3rem; font-weight: bold;">0</span>
                <span style="color: #4CAF50;">{{ __('mise acceptée') }}</span>
            </div>
        </div>
        
        <div id="guest-bet-refused" style="display: none; text-align: center; padding: 15px; background: rgba(244,67,54,0.1); border: 1px solid rgba(244,67,54,0.3); border-radius: 10px;">
            <span style="color: #f44336;">{{ __('Mise refusée - Partie sans mise') }}</span>
        </div>
    </div>
    @endif
    
    <div id="raise-modal" class="custom-modal-overlay">
        <div class="custom-modal">
            <div class="custom-modal-title">{{ __('Relancer la mise') }}</div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 10px; color: rgba(255,255,255,0.7);">{{ __('Nouveau montant (supérieur à') }} <span id="raise-min-amount">0</span>):</label>
                <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <img src="{{ asset('images/skill_coin.png') }}" alt="" style="width: 24px; height: 24px;">
                    <input type="number" id="raise-amount-input" min="1" style="width: 100px; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: #ffc107; font-size: 1.2rem; font-weight: bold; text-align: center;">
                </div>
                <div id="raise-error" style="display: none; margin-top: 10px; color: #f44336; font-size: 0.9rem;"></div>
            </div>
            <div class="custom-modal-buttons">
                <button class="custom-modal-btn cancel" onclick="closeRaiseModal()">{{ __('Annuler') }}</button>
                <button class="custom-modal-btn confirm" onclick="submitRaise()">{{ __('Relancer') }}</button>
            </div>
        </div>
    </div>
    
    <div class="players-section">
        <div class="section-title">
            <span>👥</span>
            <span>{{ __('Joueurs') }}</span>
            <button id="lobby-chat-btn" class="player-action-btn" style="margin-left: 10px; font-size: 1.2rem;" title="{{ __('Chat') }}">💬</button>
            @if(in_array($mode, ['duo', 'league_individual', 'league_team']))
            <button id="my-mic-btn" class="player-action-btn" 
                    data-player-id="{{ $currentPlayerId }}"
                    data-action="mic"
                    style="margin-left: 5px; font-size: 1.2rem;" 
                    title="{{ __('Votre micro') }}">🎙️</button>
            @endif
            <button id="lobby-help-btn" class="player-action-btn" style="margin-left: 5px; font-size: 1.2rem;" title="{{ __('Aide') }}" onclick="showHelpModal()">❓</button>
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
                    
                    <div class="player-coins" style="display: flex; align-items: center; gap: 4px; margin-right: 8px;">
                        <img src="{{ asset('images/skill_coin.png') }}" alt="" style="width: 16px; height: 16px;">
                        <span style="color: #ffc107; font-weight: bold; font-size: 0.85rem;">{{ $player['competence_coins'] ?? 0 }}</span>
                    </div>
                    
                    @if($player['is_host'])
                        <div class="player-status status-host">👑</div>
                    @elseif($player['ready'])
                        <div class="player-status status-ready">✓</div>
                    @else
                        <div class="player-status status-waiting">⏳</div>
                    @endif
                    
                    <div class="player-actions">
                        @if(in_array($mode, ['duo', 'league_individual', 'league_team']))
                            @if(!$isCurrentPlayer)
                            <button class="player-action-btn muted" 
                                    id="mic-btn-{{ $playerId }}" 
                                    data-player-id="{{ $playerId }}"
                                    data-action="opponent-mic"
                                    title="{{ __('Cliquez pour couper/rétablir le son') }}">🔇</button>
                            @endif
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
    
    @if($mode === 'league_team')
    <div class="game-mode-section" style="background: rgba(255,255,255,0.05); border-radius: 20px; padding: 25px; margin-bottom: 25px;">
        <div class="section-title">
            <span>🎮</span>
            <span>{{ __('Mode de jeu') }}</span>
        </div>
        
        <div class="game-modes-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
            <div class="game-mode-card {{ ($settings['game_mode'] ?? 'classique') === 'classique' ? 'selected' : '' }}" 
                 data-mode="classique"
                 onclick="@if($isHost) selectGameMode('classique') @endif"
                 style="background: rgba(102, 126, 234, 0.2); border: 2px solid {{ ($settings['game_mode'] ?? 'classique') === 'classique' ? '#667eea' : 'transparent' }}; border-radius: 15px; padding: 15px; cursor: {{ $isHost ? 'pointer' : 'default' }}; transition: all 0.3s ease; text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 8px;">🏆</div>
                <div style="font-weight: 700; font-size: 1rem; margin-bottom: 5px;">{{ __('Classique') }}</div>
                <div style="font-size: 0.75rem; opacity: 0.8; line-height: 1.3;">{{ __('Tous sur la même question. Premier buzz répond. Skills libres.') }}</div>
            </div>
            
            <div class="game-mode-card {{ ($settings['game_mode'] ?? 'classique') === 'bataille' ? 'selected' : '' }}" 
                 data-mode="bataille"
                 onclick="@if($isHost) selectGameMode('bataille') @endif"
                 style="background: rgba(244, 67, 54, 0.2); border: 2px solid {{ ($settings['game_mode'] ?? 'classique') === 'bataille' ? '#f44336' : 'transparent' }}; border-radius: 15px; padding: 15px; cursor: {{ $isHost ? 'pointer' : 'default' }}; transition: all 0.3s ease; text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 8px;">⚔️</div>
                <div style="font-weight: 700; font-size: 1rem; margin-bottom: 5px;">{{ __('Bataille de Niveaux') }}</div>
                <div style="font-size: 0.75rem; opacity: 0.8; line-height: 1.3;">{{ __('5 duels par rang. 1er vs 1er, 2e vs 2e... Micro équipe + chat adversaire.') }}</div>
            </div>
            
            <div class="game-mode-card {{ ($settings['game_mode'] ?? 'classique') === 'relais' ? 'selected' : '' }}" 
                 data-mode="relais"
                 onclick="@if($isHost) selectGameMode('relais') @endif"
                 style="background: rgba(76, 175, 80, 0.2); border: 2px solid {{ ($settings['game_mode'] ?? 'classique') === 'relais' ? '#4caf50' : 'transparent' }}; border-radius: 15px; padding: 15px; cursor: {{ $isHost ? 'pointer' : 'default' }}; transition: all 0.3s ease; text-align: center;">
                <div style="font-size: 2rem; margin-bottom: 8px;">🔄</div>
                <div style="font-weight: 700; font-size: 1rem; margin-bottom: 5px;">{{ __('Queue Leu Leu') }}</div>
                <div style="font-size: 0.75rem; opacity: 0.8; line-height: 1.3;">{{ __('Chacun son tour. Définissez l\'ordre. Skills du joueur actif seulement.') }}</div>
            </div>
        </div>
        
        @if($isHost && ($settings['game_mode'] ?? 'classique') === 'bataille')
        <div id="matcher-section" style="margin-top: 20px; padding: 15px; background: rgba(244, 67, 54, 0.1); border-radius: 12px; border: 1px solid rgba(244, 67, 54, 0.3);">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="font-size: 0.9rem; opacity: 0.9;">
                    <strong>{{ __('Matcher les joueurs par niveau') }}</strong><br>
                    <span style="font-size: 0.8rem; opacity: 0.7;">{{ __('Associe automatiquement les joueurs par rang de niveau') }}</span>
                </div>
                <button class="btn" onclick="matchPlayersByLevel()" style="background: linear-gradient(135deg, #f44336, #d32f2f); padding: 10px 20px; border-radius: 25px; font-weight: bold;">
                    ⚔️ {{ __('Matcher') }}
                </button>
            </div>
            <div id="duel-pairings" style="margin-top: 15px; display: none;"></div>
        </div>
        @endif
        
        @if($isHost && ($settings['game_mode'] ?? 'classique') === 'relais')
        <div id="order-section" style="margin-top: 20px; padding: 15px; background: rgba(76, 175, 80, 0.1); border-radius: 12px; border: 1px solid rgba(76, 175, 80, 0.3);">
            <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 15px;">
                <strong>{{ __('Ordre de passage de votre équipe') }}</strong><br>
                <span style="font-size: 0.8rem; opacity: 0.7;">{{ __('Glissez les joueurs pour définir l\'ordre') }}</span>
            </div>
            <div id="player-order-list" style="display: flex; flex-direction: column; gap: 8px;"></div>
        </div>
        @endif
    </div>
    @endif
    
    <div class="actions-section">
        <div id="sync-status" class="sync-status" style="display: none; margin-bottom: 15px; padding: 10px 15px; border-radius: 8px; text-align: center; font-weight: 600;">
            <span id="sync-status-text"></span>
        </div>
        
        <button class="btn btn-ready {{ ($players[$currentPlayerId]['ready'] ?? false) ? 'is-ready' : '' }}" 
                onclick="toggleReady()"
                id="ready-btn">
            <span id="ready-text">{{ ($players[$currentPlayerId]['ready'] ?? false) ? __('Annuler') : __('Je Suis Prêt!') }}</span> <span id="ready-count">0/{{ $minPlayers }}</span>
        </button>
        
        @if($isHost && $mode !== 'duo')
            <button class="btn btn-start" 
                    onclick="startGame()"
                    id="start-btn"
                    data-backend-can-start="{{ $canStart ? 'true' : 'false' }}"
                    disabled>
                {{ __('Lancer la partie') }}
            </button>
        @endif
        
        <!-- Hidden container for JS compatibility -->
        <div class="waiting-message" id="waiting-message-container" style="display: none;"></div>
        
        <button class="btn btn-leave" onclick="leaveLobby()">
            {{ __('Quitter le salon') }}
        </button>
    </div>
</div>

<div class="toast" id="toast"></div>

<!-- Countdown Overlay for Duo Auto-Start -->
<div class="countdown-overlay" id="countdown-overlay">
    <div class="countdown-label" id="countdown-label"></div>
    <div class="countdown-number" id="countdown-number">3</div>
    <div class="countdown-precision" id="countdown-precision"></div>
</div>

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
            <a id="stats-avatar-link" href="{{ route('avatars') }}" title="{{ __('Changer d\'avatar') }}">
                <img id="stats-avatar" src="" alt="" class="stats-avatar" style="cursor: pointer;">
            </a>
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
            
            <div class="strategic-avatar-section" id="strategic-avatar-section" style="margin: 15px 0; padding: 0; border-radius: 12px; display: none; overflow: hidden;">
                <div style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.4) 0%, rgba(118, 75, 162, 0.5) 50%, rgba(30, 60, 114, 0.6) 100%); padding: 15px; border: 1px solid rgba(255,193,7,0.3);">
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <div style="flex-shrink: 0; background: linear-gradient(135deg, rgba(255,193,7,0.3), rgba(255,152,0,0.2)); padding: 4px; border-radius: 12px; box-shadow: 0 4px 15px rgba(255,193,7,0.2);">
                            <img id="strategic-avatar-img" src="" alt="" style="width: 60px; height: 60px; border-radius: 10px; display: block;">
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div id="strategic-avatar-name" style="font-weight: bold; font-size: 1.1rem; color: #ffc107; text-shadow: 0 1px 3px rgba(0,0,0,0.3); margin-bottom: 8px;"></div>
                            <div id="strategic-avatar-skills" style="display: flex; flex-direction: column; gap: 4px;"></div>
                        </div>
                    </div>
                    <select id="strategic-avatar-select" onchange="changeStrategicAvatar(this.value)" style="width: 100%; margin-top: 12px; padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(255,193,7,0.4); background: rgba(0,0,0,0.3); color: #fff; font-size: 0.9rem; cursor: pointer;">
                        <option value="">{{ __('Changer d\'avatar stratégique...') }}</option>
                    </select>
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

<audio id="messageNotificationSound" preload="auto">
    <source src="{{ asset('sounds/message_notification.mp3') }}" type="audio/mpeg">
</audio>

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
        width: 90vw;
        max-width: 400px;
        height: 70vh;
        max-height: 500px;
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
        gap: 8px;
        padding-top: 10px;
        border-top: 1px solid rgba(255,255,255,0.1);
        flex-shrink: 0;
    }
    #chat-input {
        flex: 1;
        min-width: 0;
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
        padding: 10px 12px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: bold;
        flex-shrink: 0;
        white-space: nowrap;
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
    
    const unlockedStrategicAvatars = @json($unlockedStrategicAvatars ?? []);
    const activeStrategicAvatar = @json($activeStrategicAvatar ?? null);
    
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
        
        const isCurrentPlayer = playerId == currentPlayerId;
        const strategicSection = document.getElementById('strategic-avatar-section');
        const avatarLink = document.getElementById('stats-avatar-link');
        
        const hasStrategicAvatars = Object.keys(unlockedStrategicAvatars).length > 0;
        
        if (isCurrentPlayer && hasStrategicAvatars) {
            strategicSection.style.display = 'block';
            avatarLink.style.pointerEvents = 'auto';
            
            const select = document.getElementById('strategic-avatar-select');
            select.innerHTML = '<option value="">{{ __("Changer d\'avatar stratégique...") }}</option>';
            
            for (const [slug, avatar] of Object.entries(unlockedStrategicAvatars)) {
                const option = document.createElement('option');
                option.value = slug;
                option.textContent = avatar.name;
                if (slug === activeStrategicAvatar) {
                    option.selected = true;
                }
                select.appendChild(option);
            }
            
            const skillsContainer = document.getElementById('strategic-avatar-skills');
            skillsContainer.innerHTML = '';
            
            if (activeStrategicAvatar && unlockedStrategicAvatars[activeStrategicAvatar]) {
                const active = unlockedStrategicAvatars[activeStrategicAvatar];
                document.getElementById('strategic-avatar-img').src = '/' + active.path;
                document.getElementById('strategic-avatar-name').textContent = active.name;
                
                if (active.skills && active.skills.length > 0) {
                    active.skills.forEach(skill => {
                        const skillEl = document.createElement('div');
                        skillEl.style.cssText = 'font-size: 0.85rem; color: rgba(255,255,255,0.85); padding: 3px 8px; background: rgba(255,255,255,0.1); border-radius: 4px; border-left: 2px solid #ffc107;';
                        skillEl.textContent = '✨ ' + skill;
                        skillsContainer.appendChild(skillEl);
                    });
                }
            } else {
                const firstSlug = Object.keys(unlockedStrategicAvatars)[0];
                const firstAvatar = unlockedStrategicAvatars[firstSlug];
                document.getElementById('strategic-avatar-img').src = '/' + firstAvatar.path;
                document.getElementById('strategic-avatar-name').textContent = '{{ __("Sélectionnez un avatar") }}';
                
                const hint = document.createElement('div');
                hint.style.cssText = 'font-size: 0.8rem; color: rgba(255,255,255,0.6); font-style: italic;';
                hint.textContent = '{{ __("Utilisez le menu ci-dessous") }}';
                skillsContainer.appendChild(hint);
            }
        } else {
            strategicSection.style.display = 'none';
            avatarLink.style.pointerEvents = isCurrentPlayer ? 'auto' : 'none';
        }
        
        try {
            const response = await fetch(`/lobby/player-stats/${playerId}`);
            const data = await response.json();
            
            if (data.success) {
                let avatar = data.player.avatar || 'default';
                let avatarSrc;
                
                if (avatar === 'default' || avatar === null) {
                    avatarSrc = '/images/avatars/standard/default.png';
                } else if (avatar.startsWith('http')) {
                    avatarSrc = avatar;
                } else if (avatar.startsWith('/')) {
                    avatarSrc = avatar;
                } else if (avatar.includes('/') || avatar.includes('.png')) {
                    avatarSrc = '/' + avatar.replace(/^\/+/, '');
                } else {
                    avatarSrc = '/images/avatars/standard/' + avatar + '.png';
                }
                
                const statsAvatarImg = document.getElementById('stats-avatar');
                statsAvatarImg.onerror = function() {
                    this.src = '/images/avatars/standard/default.png';
                };
                statsAvatarImg.src = avatarSrc;
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
    
    async function changeStrategicAvatar(slug) {
        if (!slug) return;
        
        try {
            const response = await fetch('/api/strategic-avatar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({ avatar_slug: slug })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('{{ __("Avatar stratégique changé!") }}');
                
                if (unlockedStrategicAvatars[slug]) {
                    const active = unlockedStrategicAvatars[slug];
                    document.getElementById('strategic-avatar-img').src = '/' + active.path;
                    document.getElementById('strategic-avatar-name').textContent = active.name;
                    
                    const skillsContainer = document.getElementById('strategic-avatar-skills');
                    skillsContainer.innerHTML = '';
                    
                    if (active.skills && active.skills.length > 0) {
                        active.skills.forEach(skill => {
                            const skillEl = document.createElement('div');
                            skillEl.style.cssText = 'font-size: 0.85rem; color: rgba(255,255,255,0.85); padding: 3px 8px; background: rgba(255,255,255,0.1); border-radius: 4px; border-left: 2px solid #ffc107;';
                            skillEl.textContent = '✨ ' + skill;
                            skillsContainer.appendChild(skillEl);
                        });
                    }
                }
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error changing strategic avatar:', error);
            showToast('{{ __("Erreur de connexion") }}');
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
                const avatarEl = document.getElementById('chat-avatar');
                if (data.contact && data.contact.avatar_url) {
                    const avatar = data.contact.avatar_url;
                    const avatarSrc = avatar.includes('/') ? `/${avatar}` : `/images/avatars/standard/${avatar}.png`;
                    avatarEl.src = avatarSrc;
                } else {
                    avatarEl.src = '/images/avatars/standard/default.png';
                }
                avatarEl.onerror = function() { this.src = '/images/avatars/standard/default.png'; };
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
        
        const container = document.getElementById('chat-messages');
        const noMessages = container.querySelector('.no-messages');
        if (noMessages) noMessages.remove();
        
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-message mine';
        msgDiv.innerHTML = `${escapeHtml(message)}<span class="time">{{ __("À l'instant") }}</span>`;
        container.appendChild(msgDiv);
        container.scrollTop = container.scrollHeight;
        
        if (window.lobbyChatManager) {
            try {
                await window.lobbyChatManager.sendMessage(message);
            } catch (err) {
                console.warn('[LobbyChat] Firebase send failed, using REST fallback');
            }
        }
        
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
            
            if (!data.success) {
                console.warn('REST chat backup failed:', data.message);
            }
        } catch (error) {
            console.warn('REST chat backup error:', error);
        }
    }
    
    function closeChatModal() {
        document.getElementById('chat-modal').style.display = 'none';
        document.getElementById('chat-input').removeEventListener('keypress', handleChatKeypress);
        currentChatPlayerId = null;
        currentChatPlayerName = null;
    }
    
    let micStates = {};
    let voicePresence = {};
    let voiceEnabled = false;
    const locallyMutedPlayers = new Set();
    const lobbyMode = '{{ $mode }}';
    const voiceEnabledModes = ['duo', 'league_individual', 'league_team'];
    const isVoiceSupported = voiceEnabledModes.includes(lobbyMode);
    
    micStates[currentPlayerId] = false;
    
    function updateMicStatesOnly(playerEntries) {
        const myMicBtn = getMyMicBtn();
        if (myMicBtn) {
            myMicBtn.classList.toggle('active', micStates[currentPlayerId]);
            myMicBtn.classList.toggle('muted', !micStates[currentPlayerId]);
        }
        
        playerEntries.forEach(([playerId, player]) => {
            const isCurrentPlayer = parseInt(playerId) === currentPlayerId;
            if (isCurrentPlayer) return;
            
            const micBtn = document.getElementById('mic-btn-' + playerId);
            if (!micBtn) return;
            
            const presence = voicePresence[playerId] || {};
            const micEnabled = presence.micEnabled ?? false;
            const speaking = presence.speaking ?? false;
            
            micBtn.classList.remove('active', 'muted', 'speaking', 'unavailable');
            if (micEnabled) {
                micBtn.classList.add('active');
                if (speaking) micBtn.classList.add('speaking');
            } else {
                micBtn.classList.add('muted');
            }
        });
    }
    
    function updateVoicePresence(playerId, data) {
        voicePresence[playerId] = data;
        updateOpponentMicUI(playerId);
    }
    
    function updateOpponentMicUI(playerId) {
        const micBtn = document.getElementById('mic-btn-' + playerId);
        if (!micBtn || parseInt(playerId) === currentPlayerId) return;
        
        const presence = voicePresence[playerId] || {};
        const micEnabled = presence.micEnabled ?? false;
        const speaking = presence.speaking ?? false;
        const isLocallyMuted = locallyMutedPlayers.has(String(playerId));
        
        micBtn.classList.remove('active', 'muted', 'speaking', 'muted-locally', 'unavailable');
        
        if (!micEnabled) {
            micBtn.classList.add('muted');
            micBtn.textContent = '🔇';
            micBtn.title = '{{ __("Micro adversaire désactivé") }}';
        } else if (isLocallyMuted) {
            micBtn.classList.add('muted-locally');
            micBtn.textContent = '🔕';
            micBtn.title = '{{ __("Cliquez pour rétablir le son") }}';
        } else {
            micBtn.classList.add('active');
            if (speaking) micBtn.classList.add('speaking');
            micBtn.textContent = '🔊';
            micBtn.title = '{{ __("Cliquez pour couper le son") }}';
        }
    }
    
    function toggleOpponentMute(playerId) {
        console.log('[Mic] toggleOpponentMute called for:', playerId);
        const playerIdStr = String(playerId);
        
        if (locallyMutedPlayers.has(playerIdStr)) {
            locallyMutedPlayers.delete(playerIdStr);
            console.log('[Mic] Unmuted player:', playerId);
        } else {
            locallyMutedPlayers.add(playerIdStr);
            console.log('[Mic] Muted player:', playerId);
        }
        
        updateOpponentMicUI(playerId);
        
        if (window.webrtcManager) {
            window.webrtcManager.setRemoteAudioMuted(playerId, locallyMutedPlayers.has(playerIdStr));
        }
    }
    
    function getMyMicBtn() {
        return document.getElementById('my-mic-btn');
    }
    
    function toggleMic(playerId) {
        console.log('[Mic] toggleMic called for player:', playerId);
        
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
        const btn = getMyMicBtn();
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
        const btn = getMyMicBtn();
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
        const isCurrentPlayer = parseInt(playerId) === currentPlayerId;
        const btn = isCurrentPlayer ? getMyMicBtn() : document.getElementById('mic-btn-' + playerId);
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
        const isCurrentPlayer = parseInt(playerId) === currentPlayerId;
        const btn = isCurrentPlayer ? getMyMicBtn() : document.getElementById('mic-btn-' + playerId);
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
        
        if (typeof firebaseMatchId !== 'undefined' && firebaseMatchId) {
            const matchIdInput = document.createElement('input');
            matchIdInput.type = 'hidden';
            matchIdInput.name = 'match_id';
            matchIdInput.value = firebaseMatchId;
            form.appendChild(matchIdInput);
        }
        
        const niveauInput = document.createElement('input');
        niveauInput.type = 'hidden';
        niveauInput.name = 'niveau';
        niveauInput.value = 1;
        form.appendChild(niveauInput);
        
        if (settings.game_mode) {
            const gameModeInput = document.createElement('input');
            gameModeInput.type = 'hidden';
            gameModeInput.name = 'game_mode';
            gameModeInput.value = settings.game_mode;
            form.appendChild(gameModeInput);
        }
        
        if (settings.player_order) {
            const playerOrderInput = document.createElement('input');
            playerOrderInput.type = 'hidden';
            playerOrderInput.name = 'player_order';
            playerOrderInput.value = JSON.stringify(settings.player_order);
            form.appendChild(playerOrderInput);
        }
        
        if (settings.duel_pairings) {
            const duelPairingsInput = document.createElement('input');
            duelPairingsInput.type = 'hidden';
            duelPairingsInput.name = 'duel_pairings';
            duelPairingsInput.value = JSON.stringify(settings.duel_pairings);
            form.appendChild(duelPairingsInput);
        }
        
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
    
    let selectedGameMode = '{{ $settings['game_mode'] ?? 'classique' }}';
    let playerOrder = [];
    let duelPairings = [];
    
    async function selectGameMode(mode) {
        if (selectedGameMode === mode) return;
        
        try {
            const response = await fetch(`/lobby/${lobbyCode}/game-mode`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({ game_mode: mode })
            });
            
            const data = await response.json();
            
            if (data.success) {
                location.reload();
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error selecting game mode:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    async function matchPlayersByLevel() {
        try {
            const response = await fetch(`/lobby/${lobbyCode}/match-players`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                duelPairings = data.pairings || [];
                displayDuelPairings(duelPairings);
                showToast('{{ __("Joueurs matchés par niveau !") }}');
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error matching players:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    function displayDuelPairings(pairings) {
        const container = document.getElementById('duel-pairings');
        if (!container || !pairings.length) return;
        
        container.style.display = 'block';
        container.innerHTML = `
            <div style="font-weight: 600; margin-bottom: 10px; font-size: 0.9rem;">{{ __('Duels configurés :') }}</div>
            ${pairings.map((duel, idx) => `
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: rgba(255,255,255,0.05); border-radius: 8px; margin-bottom: 6px;">
                    <span style="flex: 1; text-align: center; font-size: 0.85rem;">${escapeHtml(duel.player1.name)} (Nv.${duel.player1.level})</span>
                    <span style="padding: 0 10px; color: #f44336; font-weight: bold;">VS</span>
                    <span style="flex: 1; text-align: center; font-size: 0.85rem;">${escapeHtml(duel.player2.name)} (Nv.${duel.player2.level})</span>
                </div>
            `).join('')}
        `;
    }
    
    function initPlayerOrderList() {
        const container = document.getElementById('player-order-list');
        if (!container) return;
        
        const myTeamPlayers = @json(collect($players)->filter(fn($p) => ($p['team'] ?? null) === ($players[$currentPlayerId]['team'] ?? null))->values()->toArray());
        
        if (!myTeamPlayers.length) return;
        
        playerOrder = myTeamPlayers.map(p => p.id || p.user_id);
        
        container.innerHTML = myTeamPlayers.map((player, idx) => `
            <div class="order-item" draggable="true" data-player-id="${player.id || player.user_id}" 
                 style="display: flex; align-items: center; gap: 10px; padding: 10px 15px; background: rgba(255,255,255,0.1); border-radius: 10px; cursor: grab;">
                <span style="font-weight: bold; color: #4caf50; min-width: 25px;">${idx + 1}.</span>
                <span style="flex: 1;">${escapeHtml(player.name)}</span>
                <span style="cursor: grab; opacity: 0.5;">⠿</span>
            </div>
        `).join('');
        
        initDragAndDrop();
    }
    
    function initDragAndDrop() {
        const container = document.getElementById('player-order-list');
        if (!container) return;
        
        let draggedItem = null;
        
        container.querySelectorAll('.order-item').forEach(item => {
            item.addEventListener('dragstart', function(e) {
                draggedItem = this;
                this.style.opacity = '0.5';
            });
            
            item.addEventListener('dragend', function(e) {
                this.style.opacity = '1';
                draggedItem = null;
                updatePlayerOrder();
            });
            
            item.addEventListener('dragover', function(e) {
                e.preventDefault();
            });
            
            item.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedItem !== this) {
                    const allItems = [...container.querySelectorAll('.order-item')];
                    const draggedIdx = allItems.indexOf(draggedItem);
                    const targetIdx = allItems.indexOf(this);
                    
                    if (draggedIdx < targetIdx) {
                        this.parentNode.insertBefore(draggedItem, this.nextSibling);
                    } else {
                        this.parentNode.insertBefore(draggedItem, this);
                    }
                }
            });
        });
    }
    
    async function updatePlayerOrder() {
        const container = document.getElementById('player-order-list');
        if (!container) return;
        
        const items = container.querySelectorAll('.order-item');
        playerOrder = [...items].map(item => parseInt(item.dataset.playerId));
        
        items.forEach((item, idx) => {
            const numSpan = item.querySelector('span:first-child');
            if (numSpan) numSpan.textContent = `${idx + 1}.`;
        });
        
        try {
            await fetch(`/lobby/${lobbyCode}/player-order`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({ player_order: playerOrder })
            });
        } catch (error) {
            console.error('Error updating player order:', error);
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        if (selectedGameMode === 'relais') {
            initPlayerOrderList();
        }
    });
    
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
                
                if (window.lobbyPresenceManager) {
                    await window.lobbyPresenceManager.updateReady(newReadyState);
                }
                
                if (window.useSocketIO && window.duoSocketConnected && typeof DuoSocketClient !== 'undefined') {
                    DuoSocketClient.setReady(newReadyState);
                    console.log('[Socket.IO] Ready state sent:', newReadyState);
                }
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
            text.textContent = '{{ __("Je Suis Prêt!") }}';
        }
    }
    
    async function updateSettings() {
        if (!isHost) return;
        
        const themeSelect = document.getElementById('theme-select');
        const questionsSelect = document.getElementById('questions-select');
        const betSelect = document.getElementById('bet-select');
        
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
                    nb_questions: parseInt(questionsSelect.value),
                    bet_amount: betSelect ? parseInt(betSelect.value) : 0
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
    
    function toggleBetDropdown() {
        const dropdown = document.getElementById('bet-dropdown');
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
    }
    
    let currentBetNegotiation = null;
    const userCompetenceCoins = {{ $userCompetenceCoins ?? 0 }};
    
    async function proposeBet(amount) {
        const dropdown = document.getElementById('bet-dropdown');
        if (dropdown) dropdown.style.display = 'none';
        
        try {
            const response = await fetch(`/lobby/${lobbyCode}/bet/propose`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({ amount: amount })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('{{ __("Proposition de mise envoyée") }}');
                updateBetNegotiationUI(data.lobby?.bet_negotiation, data.lobby?.settings);
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error proposing bet:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    async function acceptBet() {
        try {
            const response = await fetch(`/lobby/${lobbyCode}/bet/respond`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({ action: 'accept' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('{{ __("Mise acceptée !") }}');
                updateBetNegotiationUI(data.lobby?.bet_negotiation, data.lobby?.settings);
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error accepting bet:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    async function refuseBet() {
        try {
            const response = await fetch(`/lobby/${lobbyCode}/bet/respond`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({ action: 'refuse' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('{{ __("Mise refusée") }}');
                updateBetNegotiationUI(data.lobby?.bet_negotiation, data.lobby?.settings);
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error refusing bet:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    function showRaiseModal() {
        const modal = document.getElementById('raise-modal');
        const minAmountEl = document.getElementById('raise-min-amount');
        const inputEl = document.getElementById('raise-amount-input');
        const errorEl = document.getElementById('raise-error');
        
        if (currentBetNegotiation) {
            const minAmount = currentBetNegotiation.proposed_amount || 0;
            minAmountEl.textContent = minAmount;
            inputEl.min = minAmount + 1;
            inputEl.value = minAmount + 5;
        }
        
        errorEl.style.display = 'none';
        modal.classList.add('show');
    }
    
    function closeRaiseModal() {
        const modal = document.getElementById('raise-modal');
        modal.classList.remove('show');
    }
    
    async function submitRaise() {
        const inputEl = document.getElementById('raise-amount-input');
        const errorEl = document.getElementById('raise-error');
        const amount = parseInt(inputEl.value);
        const minAmount = currentBetNegotiation?.proposed_amount || 0;
        
        if (amount <= minAmount) {
            errorEl.textContent = '{{ __("Le montant doit être supérieur à") }} ' + minAmount;
            errorEl.style.display = 'block';
            return;
        }
        
        if (amount > userCompetenceCoins) {
            errorEl.textContent = '{{ __("Vous n\'avez pas assez de pièces") }}';
            errorEl.style.display = 'block';
            return;
        }
        
        closeRaiseModal();
        
        try {
            const response = await fetch(`/lobby/${lobbyCode}/bet/respond`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({ action: 'raise', amount: amount })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('{{ __("Relance envoyée !") }}');
                updateBetNegotiationUI(data.lobby?.bet_negotiation, data.lobby?.settings);
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error raising bet:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    async function cancelBet() {
        try {
            const response = await fetch(`/lobby/${lobbyCode}/bet/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('{{ __("Proposition annulée") }}');
                updateBetNegotiationUI(null, data.lobby?.settings);
            } else {
                showToast(data.error || '{{ __("Erreur") }}');
            }
        } catch (error) {
            console.error('Error canceling bet:', error);
            showToast('{{ __("Erreur de connexion") }}');
        }
    }
    
    function updateBetNegotiationUI(negotiation, settings) {
        currentBetNegotiation = negotiation;
        
        if (isHost) {
            const proposalUI = document.getElementById('bet-proposal-ui');
            const pendingUI = document.getElementById('bet-pending-ui');
            const acceptedUI = document.getElementById('bet-accepted-ui');
            const responseUI = document.getElementById('host-bet-response-ui');
            
            if (!proposalUI || !pendingUI || !acceptedUI || !responseUI) return;
            
            proposalUI.style.display = 'none';
            pendingUI.style.display = 'none';
            acceptedUI.style.display = 'none';
            responseUI.style.display = 'none';
            
            if (!negotiation) {
                proposalUI.style.display = 'block';
            } else if (negotiation.status === 'proposed') {
                if (negotiation.proposer_id === currentPlayerId) {
                    pendingUI.style.display = 'block';
                    document.getElementById('bet-pending-amount').textContent = negotiation.proposed_amount;
                } else {
                    responseUI.style.display = 'block';
                    document.getElementById('host-proposer-name').textContent = negotiation.proposer_name;
                    document.getElementById('host-bet-amount').textContent = negotiation.proposed_amount;
                    
                    const insufficientEl = document.getElementById('host-insufficient-coins');
                    const acceptBtn = document.getElementById('host-accept-btn');
                    if (userCompetenceCoins < negotiation.proposed_amount) {
                        insufficientEl.style.display = 'block';
                        acceptBtn.disabled = true;
                        acceptBtn.style.opacity = '0.5';
                        acceptBtn.style.cursor = 'not-allowed';
                    } else {
                        insufficientEl.style.display = 'none';
                        acceptBtn.disabled = false;
                        acceptBtn.style.opacity = '1';
                        acceptBtn.style.cursor = 'pointer';
                    }
                }
            } else if (negotiation.status === 'accepted') {
                acceptedUI.style.display = 'block';
                document.getElementById('bet-accepted-amount').textContent = settings?.bet_amount || negotiation.proposed_amount;
            } else if (negotiation.status === 'refused') {
                proposalUI.style.display = 'block';
            }
        } else {
            const noBetUI = document.getElementById('guest-no-bet');
            const proposalUI = document.getElementById('guest-bet-proposal');
            const acceptedUI = document.getElementById('guest-bet-accepted');
            const refusedUI = document.getElementById('guest-bet-refused');
            
            if (!noBetUI || !proposalUI || !acceptedUI || !refusedUI) return;
            
            noBetUI.style.display = 'none';
            proposalUI.style.display = 'none';
            acceptedUI.style.display = 'none';
            refusedUI.style.display = 'none';
            
            if (!negotiation) {
                noBetUI.style.display = 'block';
            } else if (negotiation.status === 'proposed') {
                if (negotiation.proposer_id !== currentPlayerId) {
                    proposalUI.style.display = 'block';
                    document.getElementById('guest-proposer-name').textContent = negotiation.proposer_name;
                    document.getElementById('guest-bet-amount').textContent = negotiation.proposed_amount;
                    
                    const insufficientEl = document.getElementById('guest-insufficient-coins');
                    const acceptBtn = document.getElementById('guest-accept-btn');
                    if (userCompetenceCoins < negotiation.proposed_amount) {
                        insufficientEl.style.display = 'block';
                        acceptBtn.disabled = true;
                        acceptBtn.style.opacity = '0.5';
                        acceptBtn.style.cursor = 'not-allowed';
                    } else {
                        insufficientEl.style.display = 'none';
                        acceptBtn.disabled = false;
                        acceptBtn.style.opacity = '1';
                        acceptBtn.style.cursor = 'pointer';
                    }
                } else {
                    noBetUI.innerHTML = '<span style="color: rgba(255,255,255,0.8);">🎲 {{ __("Votre proposition de") }} ' + negotiation.proposed_amount + ' 🪙 {{ __("en attente...") }}</span>';
                    noBetUI.style.display = 'block';
                }
            } else if (negotiation.status === 'accepted') {
                acceptedUI.style.display = 'block';
                document.getElementById('guest-accepted-amount').textContent = settings?.bet_amount || negotiation.proposed_amount;
            } else if (negotiation.status === 'refused') {
                refusedUI.style.display = 'block';
            }
        }
    }
    
    document.addEventListener('click', function(e) {
        const betBtn = document.getElementById('bet-toggle-btn');
        const dropdown = document.getElementById('bet-dropdown');
        if (betBtn && dropdown && !betBtn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
    
    function showHelpModal() {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.id = 'help-modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 500px;">
                <button class="modal-close" onclick="closeHelpModal()">&times;</button>
                <h2 style="margin-bottom: 20px;">{{ __('Aide - Salon d\'attente') }}</h2>
                <div style="text-align: left; line-height: 1.8;">
                    <p><strong>💬 Chat</strong> - {{ __('Discutez avec les autres joueurs') }}</p>
                    <p><strong>🎤 Micro</strong> - {{ __('Activez votre micro pour parler en temps réel') }}</p>
                    <p><strong>✓ Prêt</strong> - {{ __('Indiquez que vous êtes prêt à jouer') }}</p>
                    <p><strong>🎨 Couleur</strong> - {{ __('Choisissez votre couleur d\'équipe') }}</p>
                    <p><strong>🎲 Mise</strong> - {{ __('Pariez des pièces de Compétence comme enjeu. Le gagnant remporte la mise de tous les joueurs!') }}</p>
                    <hr style="margin: 15px 0; opacity: 0.3;">
                    <p style="opacity: 0.8; font-size: 0.9rem;">{{ __('Le créateur du salon peut démarrer la partie quand tous les joueurs sont prêts.') }}</p>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.style.display = 'flex';
    }
    
    function closeHelpModal() {
        const modal = document.getElementById('help-modal');
        if (modal) modal.remove();
    }
    
    let isStartingGame = false;
    
    async function startGame() {
        if (isStartingGame) {
            console.log('Game start already in progress, ignoring duplicate click');
            return;
        }
        
        isStartingGame = true;
        const startBtn = document.getElementById('start-btn');
        if (startBtn) {
            startBtn.disabled = true;
            startBtn.style.opacity = '0.5';
        }
        
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
                isStartingGame = false;
                if (startBtn) {
                    startBtn.disabled = false;
                    startBtn.style.opacity = '1';
                }
            }
        } catch (error) {
            console.error('Error starting game:', error);
            showToast('{{ __("Erreur de connexion") }}');
            isStartingGame = false;
            if (startBtn) {
                startBtn.disabled = false;
                startBtn.style.opacity = '1';
            }
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
        you: @json(__("vous")),
        waitingPlayer: @json(__("En attente d'un joueur...")),
        chat: @json(__("Chat")),
        micro: @json(__("Micro")),
        yourMic: @json(__("Votre micro")),
        opponentMic: @json(__("Micro de l'adversaire")),
        players: @json(__("Joueurs")),
        lobbyClosed: @json(__("Le salon a été fermé")),
        waitingMessage: @json(__("En attente de joueurs")),
        waitingReady: @json(__("En attente que tous soient prêts")),
        waitingConnection: @json(__("En attente de connexion")),
        waitingOtherPlayer: @json(__("En attente de l'autre joueur...")),
        synchronized: @json(__("Synchronisé")),
        minimum: @json(__("minimum")),
        audioNotAvailable: @json(__("Audio non disponible")),
        waitingFor: @json(__("En attente de:")),
        gameStarting: @json(__("La partie commence dans")),
        go: @json(__("GO!"))
    };
    
    let lastPlayersHash = '';
    
    function updatePlayersUI(players) {
        const playersGrid = document.querySelector('.players-grid');
        if (!playersGrid) return;
        
        const playerEntries = Object.entries(players || {});
        
        const currentHash = JSON.stringify(playerEntries.map(([id, p]) => ({
            id, name: p.name, avatar: p.avatar, ready: p.ready, is_host: p.is_host, color: p.color
        })));
        
        if (currentHash === lastPlayersHash) {
            updateMicStatesOnly(playerEntries);
            return;
        }
        lastPlayersHash = currentHash;
        
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
            
            const otherMicEnabled = voicePresence[playerId]?.micEnabled ?? false;
            const otherSpeaking = voicePresence[playerId]?.speaking ?? false;
            
            let micBtnHtml = '';
            if (isVoiceSupported && !isCurrentPlayer) {
                const isLocallyMuted = locallyMutedPlayers.has(playerId);
                let micClass, micIcon, micTitle;
                
                if (!otherMicEnabled) {
                    micClass = 'muted';
                    micIcon = '🔇';
                    micTitle = translations.opponentMicOff || "Micro adversaire désactivé";
                } else if (isLocallyMuted) {
                    micClass = 'muted-locally';
                    micIcon = '🔕';
                    micTitle = translations.opponentMutedLocally || "Cliquez pour rétablir le son";
                } else {
                    micClass = otherSpeaking ? 'active speaking' : 'active';
                    micIcon = '🔊';
                    micTitle = translations.opponentMicActive || "Cliquez pour couper le son";
                }
                
                micBtnHtml = `<button class="player-action-btn ${micClass}" 
                    id="mic-btn-${playerId}" 
                    data-player-id="${playerId}"
                    data-action="opponent-mic"
                    title="${micTitle}">${micIcon}</button>`;
            }
            
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
                         loading="lazy"
                         onerror="this.src='/images/avatars/standard/default.png'">
                    
                    <div class="player-info">
                        <div class="player-name">
                            ${safeName}
                            ${youLabel}
                        </div>
                        <div class="player-code">${safeCode}</div>
                    </div>
                    
                    <div class="player-coins" style="display: flex; align-items: center; gap: 4px; margin-right: 8px;">
                        <img src="/images/skill_coin.png" alt="" style="width: 16px; height: 16px;">
                        <span style="color: #ffc107; font-weight: bold; font-size: 0.85rem;">${player.competence_coins || 0}</span>
                    </div>
                    
                    ${statusHtml}
                    
                    <div class="player-actions">
                        ${micBtnHtml}
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
        
        const hostCountEl = document.getElementById('player-count-host');
        if (hostCountEl) hostCountEl.textContent = playerEntries.length;
        
        const guestCountEl = document.getElementById('player-count-guest');
        if (guestCountEl) guestCountEl.textContent = playerEntries.length;
    }
    
    document.addEventListener('click', function(e) {
        console.log('[Click] Document click detected, target:', e.target.tagName, e.target.className);
        
        // Handle lobby chat button click first
        if (e.target.id === 'lobby-chat-btn' || e.target.closest('#lobby-chat-btn')) {
            console.log('[Chat] Lobby chat button clicked');
            openLobbyChatWithOpponent();
            return;
        }
        
        // Handle action buttons (mic, chat)
        const actionBtn = e.target.closest('[data-action]');
        if (actionBtn) {
            e.preventDefault();
            const action = actionBtn.dataset.action;
            const playerId = parseInt(actionBtn.dataset.playerId);
            const playerCard = actionBtn.closest('.player-card');
            const playerName = playerCard?.dataset.playerName || '';
            
            console.log('[Click] Action:', action, 'PlayerId:', playerId);
            
            if (action === 'chat') {
                openPlayerChat(playerId, playerName);
            } else if (action === 'mic') {
                toggleMic(playerId);
            } else if (action === 'opponent-mic') {
                toggleOpponentMute(playerId);
            }
            return;
        }
        
        // Handle player card click (show stats) - only if not clicking on actions
        const playerCard = e.target.closest('.player-card');
        if (playerCard && !e.target.closest('.player-actions')) {
            const playerId = playerCard.dataset.playerId;
            const playerName = playerCard.dataset.playerName;
            if (playerId && playerName) {
                showPlayerStats(parseInt(playerId), playerName);
            }
        }
    });
    
    // Open chat with the opponent (other player in lobby)
    function openLobbyChatWithOpponent() {
        const playerCards = document.querySelectorAll('.player-card');
        for (const card of playerCards) {
            const playerId = parseInt(card.dataset.playerId);
            if (playerId !== currentPlayerId) {
                const playerName = card.dataset.playerName || 'Adversaire';
                console.log('[Chat] Opening chat with opponent:', playerId, playerName);
                openPlayerChat(playerId, playerName);
                return;
            }
        }
        showToast(translations.noOpponent || 'Aucun adversaire dans le salon');
    }
    
    function updateWaitingMessage(players, minPlayers, allReady) {
        // Waiting message removed - status is shown via player cards with ready indicators
        const waitingDiv = document.querySelector('.waiting-message');
        if (waitingDiv) waitingDiv.style.display = 'none';
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
            
            updateBetNegotiationUI(data.lobby?.bet_negotiation, data.lobby?.settings);
            
            if (isHost) {
                updateWaitingMessage(data.lobby?.players, {{ $minPlayers }}, data.all_ready);
                
                const startBtn = document.getElementById('start-btn');
                if (startBtn) {
                    // Track backend state for combination with Firebase presence check
                    startBtn.dataset.backendDisabled = data.can_start ? 'false' : 'true';
                    
                    // Only enable if BOTH backend allows AND Firebase confirms connection
                    const firebaseConnected = startBtn.dataset.firebaseConnected === 'true';
                    if (data.can_start && firebaseConnected) {
                        startBtn.removeAttribute('disabled');
                    } else {
                        startBtn.setAttribute('disabled', 'disabled');
                    }
                }
            }
            
        } catch (error) {
            console.error('Error refreshing lobby state:', error);
        }
    }
    
    pollingInterval = setInterval(refreshLobbyState, 10000);
    
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
import { getAuth, signInAnonymously, onAuthStateChanged } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';
import { getFirestore, doc, collection, addDoc, onSnapshot, query, where, deleteDoc, getDocs, getDoc, setDoc, serverTimestamp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js';

const firebaseConfig = {
    apiKey: "AIzaSyAB5-A0NsX9I9eFX76ZBYQQG_bagWp_dHw",
    authDomain: "strategybuzzergame.firebaseapp.com",
    projectId: "strategybuzzergame",
    storageBucket: "strategybuzzergame.appspot.com",
    messagingSenderId: "68047817391",
    appId: "1:68047817391:web:ba6b3bc148ef187bfeae9a"
};

const app = initializeApp(firebaseConfig, 'webrtc-app');
const auth = getAuth(app);
const db = getFirestore(app);

let firebaseReady = false;
let initPromise = null;

function initFirebase() {
    if (initPromise) return initPromise;
    
    initPromise = new Promise((resolve, reject) => {
        let authStateResolved = false;
        let signInResolved = false;
        let authUser = null;
        let signInSuccess = false;
        
        function checkComplete() {
            if (authStateResolved && signInResolved) {
                if (authUser && signInSuccess) {
                    firebaseReady = true;
                    resolve(true);
                } else {
                    resolve(false);
                }
            }
        }
        
        onAuthStateChanged(auth, (user) => {
            authUser = user;
            authStateResolved = true;
            if (user) {
                console.log('[Firebase] User authenticated:', user.uid);
            }
            checkComplete();
        });
        
        signInAnonymously(auth)
            .then(() => {
                console.log('[Firebase] Anonymous auth successful');
                signInSuccess = true;
                signInResolved = true;
                checkComplete();
            })
            .catch((error) => {
                console.error('[Firebase] Auth error:', error);
                signInResolved = true;
                checkComplete();
            });
        
        setTimeout(() => {
            if (!authStateResolved || !signInResolved) {
                console.error('[Firebase] Auth timeout');
                resolve(false);
            }
        }, 10000);
    });
    
    return initPromise;
}

class LobbyChatManager {
    constructor(lobbyCode, currentPlayerId, currentPlayerName) {
        this.lobbyCode = lobbyCode;
        this.currentPlayerId = currentPlayerId;
        this.currentPlayerName = currentPlayerName;
        this.unsubscriber = null;
        this.isListening = false;
    }
    
    getChatPath() {
        return `lobby_chats/${this.lobbyCode}/messages`;
    }
    
    startListening() {
        if (this.isListening) return;
        
        const messagesRef = collection(db, this.getChatPath());
        const q = query(messagesRef);
        
        this.unsubscriber = onSnapshot(q, (snapshot) => {
            snapshot.docChanges().forEach((change) => {
                if (change.type === 'added') {
                    const data = change.doc.data();
                    if (data.senderId !== this.currentPlayerId) {
                        this.displayIncomingMessage(data);
                    }
                }
            });
        });
        
        this.isListening = true;
        console.log('[LobbyChat] Started listening for messages');
    }
    
    displayIncomingMessage(data) {
        const chatModal = document.getElementById('chat-modal');
        if (!chatModal || chatModal.style.display === 'none') {
            return;
        }
        
        const container = document.getElementById('chat-messages');
        if (!container) return;
        
        const noMessages = container.querySelector('.no-messages');
        if (noMessages) noMessages.remove();
        
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-message theirs';
        msgDiv.innerHTML = `<strong>${this.escapeHtml(data.senderName)}</strong><br>${this.escapeHtml(data.message)}<span class="time">{{ __("À l'instant") }}</span>`;
        container.appendChild(msgDiv);
        container.scrollTop = container.scrollHeight;
    }
    
    async sendMessage(message) {
        try {
            const messagesRef = collection(db, this.getChatPath());
            await addDoc(messagesRef, {
                senderId: this.currentPlayerId,
                senderName: this.currentPlayerName,
                message: message,
                timestamp: serverTimestamp()
            });
            console.log('[LobbyChat] Message sent via Firebase');
            return true;
        } catch (error) {
            console.error('[LobbyChat] Error sending message:', error);
            return false;
        }
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    stopListening() {
        if (this.unsubscriber) {
            this.unsubscriber();
            this.unsubscriber = null;
        }
        this.isListening = false;
        console.log('[LobbyChat] Stopped listening');
    }
}

class LobbyPresenceManager {
    constructor(lobbyCode, currentPlayerId, currentPlayerData, isHost) {
        this.lobbyCode = lobbyCode;
        this.currentPlayerId = currentPlayerId;
        this.currentPlayerData = currentPlayerData;
        this.isHost = isHost;
        this.heartbeatInterval = null;
        this.cleanupInterval = null;
        this.unsubscriber = null;
        this.presenceData = {};
        this.onPlayersChange = null;
        this.HEARTBEAT_INTERVAL = 15000; // 15 seconds
        this.OFFLINE_THRESHOLD = 60000; // 60 seconds - more tolerant of brief disconnections
    }
    
    getPresencePath() {
        return `lobbies/${this.lobbyCode}/presence`;
    }
    
    async joinLobby() {
        try {
            const presenceRef = doc(db, this.getPresencePath(), String(this.currentPlayerId));
            const readyState = this.currentPlayerData.ready === true ? true : false;
            console.log('[Presence] Joining lobby with ready state:', readyState, 'for player:', this.currentPlayerId, 'isHost:', this.isHost);
            await setDoc(presenceRef, {
                odPlayerId: this.currentPlayerId,
                name: this.currentPlayerData.name,
                player_code: this.currentPlayerData.player_code || '',
                avatar: this.currentPlayerData.avatar || null,
                color: this.currentPlayerData.color || 'blue',
                team: this.currentPlayerData.team || null,
                ready: readyState,
                is_host: this.isHost,
                online: true,
                lastSeen: serverTimestamp(),
                joinedAt: serverTimestamp()
            });
            console.log('[Presence] Joined lobby:', this.lobbyCode, 'with ready:', readyState);
            
            this.startHeartbeat();
            this.startListening();
            this.startCleanupCheck();
            
            return true;
        } catch (error) {
            console.error('[Presence] Error joining lobby:', error);
            return false;
        }
    }
    
    startHeartbeat() {
        if (this.heartbeatInterval) return;
        
        this.heartbeatPaused = false;
        this.heartbeatInterval = setInterval(async () => {
            if (this.heartbeatPaused) return; // Skip if paused
            try {
                const presenceRef = doc(db, this.getPresencePath(), String(this.currentPlayerId));
                await setDoc(presenceRef, {
                    lastSeen: serverTimestamp(),
                    online: true
                }, { merge: true });
            } catch (error) {
                console.error('[Presence] Heartbeat error:', error);
            }
        }, this.HEARTBEAT_INTERVAL);
        
        console.log('[Presence] Heartbeat started');
    }
    
    pauseHeartbeat() {
        this.heartbeatPaused = true;
        console.log('[Presence] Heartbeat paused');
    }
    
    resumeHeartbeat() {
        this.heartbeatPaused = false;
        // Send immediate heartbeat to show we're back online
        const presenceRef = doc(db, this.getPresencePath(), String(this.currentPlayerId));
        setDoc(presenceRef, {
            lastSeen: serverTimestamp(),
            online: true
        }, { merge: true }).then(() => {
            console.log('[Presence] Heartbeat resumed - immediate update sent');
        }).catch(err => {
            console.error('[Presence] Resume heartbeat error:', err);
        });
    }
    
    startListening() {
        if (this.unsubscriber) return;
        
        const presenceRef = collection(db, this.getPresencePath());
        
        this.unsubscriber = onSnapshot(presenceRef, (snapshot) => {
            const now = Date.now();
            const players = {};
            
            snapshot.forEach((docSnap) => {
                const data = docSnap.data();
                const playerId = data.odPlayerId || parseInt(docSnap.id);
                const lastSeen = data.lastSeen?.toMillis ? data.lastSeen.toMillis() : now;
                const isOnline = data.online && (now - lastSeen < this.OFFLINE_THRESHOLD);
                
                if (isOnline) {
                    players[playerId] = {
                        id: playerId,
                        name: data.name,
                        player_code: data.player_code,
                        avatar: data.avatar,
                        color: data.color,
                        team: data.team,
                        ready: data.ready,
                        is_host: data.is_host,
                        online: true
                    };
                }
                
                this.presenceData[playerId] = { ...data, lastSeen, isOnline };
            });
            
            console.log('[Presence] Players online:', Object.keys(players).length);
            
            if (this.onPlayersChange) {
                this.onPlayersChange(players);
            }
        }, (error) => {
            console.error('[Presence] Listener error:', error);
        });
        
        console.log('[Presence] Started listening');
    }
    
    startCleanupCheck() {
        if (this.cleanupInterval) return;
        
        // Track missed heartbeat counts before removal
        this.offlineCounts = {};
        const REMOVAL_THRESHOLD = 3; // Player must be offline 3 checks before removal
        
        this.cleanupInterval = setInterval(async () => {
            if (!this.isHost) return;
            
            const now = Date.now();
            for (const [playerId, data] of Object.entries(this.presenceData)) {
                if (parseInt(playerId) === this.currentPlayerId) continue;
                
                const lastSeen = data.lastSeen || 0;
                if (now - lastSeen > this.OFFLINE_THRESHOLD && data.online) {
                    // Increment offline count
                    this.offlineCounts[playerId] = (this.offlineCounts[playerId] || 0) + 1;
                    console.log(`[Presence] Player ${playerId} offline check ${this.offlineCounts[playerId]}/${REMOVAL_THRESHOLD}`);
                    
                    // Only remove after multiple consecutive offline checks
                    if (this.offlineCounts[playerId] >= REMOVAL_THRESHOLD) {
                        try {
                            const presenceRef = doc(db, this.getPresencePath(), String(playerId));
                            await setDoc(presenceRef, { online: false }, { merge: true });
                            console.log('[Presence] Marked player offline:', playerId);
                            
                            await fetch(`/lobby/${this.lobbyCode}/remove-player`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                },
                                body: JSON.stringify({ player_id: parseInt(playerId) })
                            });
                            console.log('[Presence] Removed offline player from backend:', playerId);
                            delete this.offlineCounts[playerId];
                        } catch (error) {
                            console.error('[Presence] Error marking offline:', error);
                        }
                    }
                } else {
                    // Reset offline count if player is online
                    delete this.offlineCounts[playerId];
                }
            }
        }, 20000); // Check every 20 seconds
        
        console.log('[Presence] Cleanup check started');
    }
    
    async updateReady(ready) {
        try {
            const presenceRef = doc(db, this.getPresencePath(), String(this.currentPlayerId));
            console.log('[Presence] Updating ready state to:', ready, 'for player:', this.currentPlayerId);
            await setDoc(presenceRef, { ready: ready === true, lastSeen: serverTimestamp() }, { merge: true });
            console.log('[Presence] Ready updated successfully:', ready);
            return true;
        } catch (error) {
            console.error('[Presence] Error updating ready:', error);
            return false;
        }
    }
    
    async updateColor(color) {
        try {
            const presenceRef = doc(db, this.getPresencePath(), String(this.currentPlayerId));
            await setDoc(presenceRef, { color, lastSeen: serverTimestamp() }, { merge: true });
            console.log('[Presence] Color updated:', color);
            return true;
        } catch (error) {
            console.error('[Presence] Error updating color:', error);
            return false;
        }
    }
    
    async leaveLobby() {
        try {
            if (this.heartbeatInterval) {
                clearInterval(this.heartbeatInterval);
                this.heartbeatInterval = null;
            }
            if (this.cleanupInterval) {
                clearInterval(this.cleanupInterval);
                this.cleanupInterval = null;
            }
            if (this.unsubscriber) {
                this.unsubscriber();
                this.unsubscriber = null;
            }
            
            const presenceRef = doc(db, this.getPresencePath(), String(this.currentPlayerId));
            await deleteDoc(presenceRef);
            console.log('[Presence] Left lobby');
        } catch (error) {
            console.error('[Presence] Error leaving lobby:', error);
        }
    }
    
    cleanup() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
        if (this.cleanupInterval) {
            clearInterval(this.cleanupInterval);
            this.cleanupInterval = null;
        }
        if (this.unsubscriber) {
            this.unsubscriber();
            this.unsubscriber = null;
        }
        
        try {
            const presenceRef = doc(db, this.getPresencePath(), String(this.currentPlayerId));
            deleteDoc(presenceRef);
        } catch (error) {
            console.error('[Presence] Cleanup error:', error);
        }
        
        console.log('[Presence] Cleaned up');
    }
}

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
        this.presenceListenerActive = false;
        this.signalingListenerActive = false;
        
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
    
    async initialize() {
        console.log('[WebRTC] Initializing - creating listening presence for player:', this.currentPlayerId);
        try {
            await this.createListeningPresence();
            this.listenForSignaling();
            this.listenForPresence();
            console.log('[WebRTC] Initialized successfully - listening for other players');
            return true;
        } catch (error) {
            console.error('[WebRTC] Initialization error:', error);
            return false;
        }
    }
    
    async createListeningPresence() {
        try {
            const presencePath = this.getPresencePath();
            console.log('[WebRTC] Creating listening presence at:', presencePath);
            const presenceRef = doc(db, presencePath, String(this.currentPlayerId));
            await setDoc(presenceRef, {
                odPlayerId: this.currentPlayerId,
                muted: true,
                speaking: false,
                listening: true,
                teamId: this.teamId,
                createdAt: serverTimestamp(),
                updatedAt: serverTimestamp()
            }, { merge: true });
            console.log('[WebRTC] Listening presence created successfully');
        } catch (error) {
            console.error('[WebRTC] Error creating listening presence:', error);
            throw error;
        }
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
            console.log('[WebRTC] Presence updated to mic enabled');
            
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
        if (this.presenceListenerActive) {
            console.log('[WebRTC] Presence listener already active, skipping');
            return;
        }
        this.presenceListenerActive = true;
        const presencePath = this.getPresencePath();
        console.log('[WebRTC] listenForPresence - path:', presencePath);
        const presenceRef = collection(db, presencePath);
        
        const unsubscribe = onSnapshot(presenceRef, (snapshot) => {
            console.log('[WebRTC] Presence snapshot received, changes:', snapshot.docChanges().length, 'total docs:', snapshot.size);
            snapshot.docChanges().forEach((change) => {
                const data = change.doc.data();
                const odPlayerId = data.odPlayerId || parseInt(change.doc.id);
                
                console.log('[WebRTC] Presence change:', change.type, 'for player:', odPlayerId, 'currentPlayer:', this.currentPlayerId, 'muted:', data.muted, 'speaking:', data.speaking);
                
                if (parseInt(odPlayerId) === parseInt(this.currentPlayerId)) {
                    console.log('[WebRTC] Skipping own presence update');
                    return;
                }
                
                if (change.type === 'added' || change.type === 'modified') {
                    const micEnabled = !data.muted;
                    const speaking = data.speaking && micEnabled;
                    const isListening = data.listening === true;
                    
                    console.log('[WebRTC] Remote player', odPlayerId, 'micEnabled:', micEnabled, 'speaking:', speaking, 'listening:', isListening, 'hasConnection:', !!this.peerConnections[odPlayerId], 'hasLocalStream:', !!this.localStream);
                    
                    if (typeof updateVoicePresence === 'function') {
                        updateVoicePresence(odPlayerId, { micEnabled, speaking });
                    }
                    if (typeof updateSpeakingIndicator === 'function') {
                        updateSpeakingIndicator(odPlayerId, speaking);
                    }
                    if (typeof updateRemoteMicState === 'function') {
                        updateRemoteMicState(odPlayerId, micEnabled);
                    }
                    
                    const shouldConnect = (micEnabled && this.localStream) || (isListening && micEnabled);
                    if (!this.peerConnections[odPlayerId] && shouldConnect) {
                        console.log('[WebRTC] Creating peer connection with remote player:', odPlayerId, 'reason: micEnabled=', micEnabled, 'listening=', isListening);
                        this.createPeerConnection(odPlayerId, this.currentPlayerId < parseInt(odPlayerId));
                    }
                } else if (change.type === 'removed') {
                    this.closePeerConnection(odPlayerId);
                    if (typeof updateVoicePresence === 'function') {
                        updateVoicePresence(odPlayerId, { micEnabled: false, speaking: false });
                    }
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
        if (this.signalingListenerActive) {
            console.log('[WebRTC] Signaling listener already active, skipping');
            return;
        }
        this.signalingListenerActive = true;
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
    
    setRemoteAudioMuted(playerId, muted) {
        console.log('[WebRTC] setRemoteAudioMuted called for:', playerId, 'muted:', muted);
        const audioKey = `audio-${playerId}`;
        const audioElements = document.querySelectorAll(`audio[data-peer-id="${playerId}"]`);
        
        audioElements.forEach(audio => {
            audio.muted = muted;
            console.log('[WebRTC] Audio element muted:', muted);
        });
        
        if (this.remoteStreams && this.remoteStreams[playerId]) {
            const tracks = this.remoteStreams[playerId].getAudioTracks();
            tracks.forEach(track => {
                track.enabled = !muted;
                console.log('[WebRTC] Remote track enabled:', !muted);
            });
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
const currentPlayerName = @json($players[$currentPlayerId]['name'] ?? 'Joueur');
const isHostFirebase = {{ $isHost ? 'true' : 'false' }};
const currentPlayerData = @json($players[$currentPlayerId] ?? ['name' => 'Joueur', 'ready' => false, 'color' => 'blue']);
const minPlayersFirebase = {{ $minPlayers }};
const firebaseMatchId = {{ $matchId ?? 'null' }};

window.countdownInitiated = false;
window.countdownUnsubscriber = null;
window.countdownAnimationFrame = null;
window.serverTimeOffset = 0; // Server time - Client time (ms)
window.offsetMeasured = false;

// Measure clock offset between client and server using /api/now endpoint
async function measureServerOffset() {
    const samples = [];
    const numSamples = 3;
    
    for (let i = 0; i < numSamples; i++) {
        try {
            const t0 = Date.now();
            const response = await fetch('/api/now');
            const t3 = Date.now();
            const data = await response.json();
            
            // NTP-style calculation: offset = serverTime - ((t0 + t3) / 2)
            const rtt = t3 - t0;
            const clientMidpoint = (t0 + t3) / 2;
            const offset = data.serverTime - clientMidpoint;
            
            samples.push({ offset, rtt });
        } catch (e) {
            console.warn('[ClockSync] Sample failed:', e);
        }
    }
    
    if (samples.length === 0) {
        console.warn('[ClockSync] No valid samples, using offset 0');
        return 0;
    }
    
    // Use the sample with minimum RTT (most accurate)
    samples.sort((a, b) => a.rtt - b.rtt);
    const bestSample = samples[0];
    
    console.log('[ClockSync] Offset measured:', bestSample.offset, 'ms (RTT:', bestSample.rtt, 'ms)');
    return bestSample.offset;
}

// Get synchronized server time
function getServerTime() {
    return Date.now() + window.serverTimeOffset;
}

async function startDuoCountdown(presencePlayers) {
    if (!db) {
        console.error('[Countdown] Firebase not initialized');
        return;
    }
    
    // Measure clock offset before starting countdown (if not already done)
    if (!window.offsetMeasured) {
        window.serverTimeOffset = await measureServerOffset();
        window.offsetMeasured = true;
    }
    
    const countdownDocRef = doc(db, 'lobbies', lobbyCode, 'countdown', 'current');
    
    try {
        const existingDoc = await getDoc(countdownDocRef);
        
        if (!existingDoc.exists()) {
            console.log('[Countdown] Creating countdown document...');
            await setDoc(countdownDocRef, {
                startAt: serverTimestamp(),
                durationMs: 3000,
                initiatedBy: currentPlayerId
            });
            console.log('[Countdown] Countdown document created');
        } else {
            console.log('[Countdown] Countdown already exists, waiting for it...');
        }
        
        listenToCountdown(countdownDocRef);
        
    } catch (error) {
        console.error('[Countdown] Error creating countdown:', error);
        window.countdownInitiated = false;
    }
}

function listenToCountdown(countdownDocRef) {
    if (window.countdownUnsubscriber) return;
    
    window.countdownUnsubscriber = onSnapshot(countdownDocRef, async (docSnap) => {
        if (!docSnap.exists()) return;
        
        const data = docSnap.data();
        if (!data.startAt) return;
        
        // Both clients receive the same serverTimestamp from Firebase
        // This is the authoritative time reference
        const serverStartTime = data.startAt.toMillis ? data.startAt.toMillis() : Date.now();
        const durationMs = data.durationMs || 3000;
        
        // Calculate end time based on server start time
        const serverEndTime = serverStartTime + durationMs;
        
        // Calculate how much time has already passed since countdown started
        // Use synchronized server time for accurate calculation
        const syncedNow = getServerTime();
        const elapsedSinceStart = syncedNow - serverStartTime;
        const remainingAtStart = durationMs - elapsedSinceStart;
        
        console.log('[Countdown] Starting visual countdown', { 
            serverStartTime,
            syncedNow,
            elapsedSinceStart,
            remainingAtStart,
            durationMs,
            serverTimeOffset: window.serverTimeOffset
        });
        
        // If countdown already finished (late joiner), clean up and start game immediately
        if (remainingAtStart <= 0) {
            console.log('[Countdown] Already finished, starting game immediately');
            
            // Clean up countdown document
            try {
                await deleteDoc(countdownDocRef);
                console.log('[Countdown] Stale countdown document deleted');
            } catch (err) {
                console.error('[Countdown] Failed to delete stale countdown:', err);
            }
            
            if (window.countdownUnsubscriber) {
                window.countdownUnsubscriber();
                window.countdownUnsubscriber = null;
            }
            window.countdownInitiated = false;
            
            submitGameStart(mode, @json($settings ?? []));
            return;
        }
        
        showCountdownOverlay();
        // Pass server times so all clients use the same reference point
        runCountdownAnimation(serverEndTime, durationMs, countdownDocRef, serverStartTime);
        
    }, (error) => {
        console.error('[Countdown] Listener error:', error);
    });
}

function showCountdownOverlay() {
    const overlay = document.getElementById('countdown-overlay');
    const label = document.getElementById('countdown-label');
    if (overlay) {
        overlay.classList.add('show');
    }
    if (label) {
        label.textContent = translations.gameStarting;
    }
}

function hideCountdownOverlay() {
    const overlay = document.getElementById('countdown-overlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
}

// Normalize match ID for Firebase path - must match backend DuoFirestoreService::normalizeMatchId()
function normalizeMatchIdJs(matchId) {
    if (typeof matchId === 'number' && matchId > 0) {
        return matchId;
    }
    const matchIdStr = String(matchId);
    const numericId = parseInt(matchIdStr.replace(/[^0-9]/g, ''), 10) || 0;
    if (numericId === 0) {
        let crc = 0xFFFFFFFF;
        for (let i = 0; i < matchIdStr.length; i++) {
            crc ^= matchIdStr.charCodeAt(i);
            for (let j = 0; j < 8; j++) {
                crc = (crc >>> 1) ^ (crc & 1 ? 0xEDB88320 : 0);
            }
        }
        return ((crc ^ 0xFFFFFFFF) >>> 0) & 0x7FFFFFFF;
    }
    return numericId;
}

function runCountdownAnimation(serverEndTime, totalDuration, countdownDocRef, serverStartTime) {
    const numberEl = document.getElementById('countdown-number');
    const precisionEl = document.getElementById('countdown-precision');
    const label = document.getElementById('countdown-label');
    let gameNavigated = false;
    
    // Prefetch the game page during countdown to reduce load time
    // This ensures the page is cached when we navigate after countdown
    const prefetchLink = document.createElement('link');
    prefetchLink.rel = 'prefetch';
    prefetchLink.href = `/game/${mode}/question`;
    prefetchLink.as = 'document';
    document.head.appendChild(prefetchLink);
    console.log('[Countdown] Prefetching game page:', prefetchLink.href);
    
    function updateCountdown() {
        // Calculate remaining time using synchronized server time
        // getServerTime() = Date.now() + serverTimeOffset (measured via /api/now)
        // This ensures both clients compute the same remaining time
        const syncedNow = getServerTime();
        const remaining = serverEndTime - syncedNow;
        
        if (remaining <= 0) {
            if (!gameNavigated) {
                gameNavigated = true;
                
                if (numberEl) {
                    numberEl.textContent = translations.go;
                    numberEl.classList.add('go');
                }
                if (precisionEl) {
                    precisionEl.textContent = '';
                }
                
                setTimeout(async () => {
                    console.log('[Countdown] Countdown finished! Starting game...');
                    
                    // Calculate introEndTimestamp = countdown end time + 9 seconds intro
                    // Use serverEndTime (synchronized) so all clients get the same value
                    const introDurationMs = 9000;
                    const introEndTimestamp = serverEndTime + introDurationMs;
                    
                    // Publish introEndTimestamp to Firebase for synchronized intro
                    // Host publishes, all clients read the same value
                    if (isHostFirebase && db) {
                        try {
                            const normalizedId = normalizeMatchIdJs(lobbyCode);
                            const gameDocRef = doc(db, 'games', `duo-match-${normalizedId}`);
                            await setDoc(gameDocRef, {
                                introEndTimestamp: introEndTimestamp,
                                gameStartTimeMs: serverEndTime,
                                introDurationMs: introDurationMs
                            }, { merge: true });
                            console.log('[Countdown] Published intro sync timestamps:', {
                                introEndTimestamp,
                                gameStartTimeMs: serverEndTime,
                                introDurationMs
                            });
                        } catch (err) {
                            console.error('[Countdown] Failed to publish intro timestamps:', err);
                        }
                    }
                    
                    // Clean up countdown document to allow future countdowns
                    if (countdownDocRef) {
                        try {
                            await deleteDoc(countdownDocRef);
                            console.log('[Countdown] Countdown document deleted');
                        } catch (err) {
                            console.error('[Countdown] Failed to delete countdown doc:', err);
                        }
                    }
                    
                    if (window.countdownUnsubscriber) {
                        window.countdownUnsubscriber();
                        window.countdownUnsubscriber = null;
                    }
                    if (pollingInterval) clearInterval(pollingInterval);
                    if (window.lobbyPresenceManager) window.lobbyPresenceManager.cleanup();
                    if (window.webrtcManager) window.webrtcManager.cleanup();
                    
                    window.countdownInitiated = false; // Reset for future lobbies
                    
                    const settings = @json($settings ?? []);
                    submitGameStart(mode, settings);
                }, 500);
            }
            return;
        }
        
        const seconds = Math.ceil(remaining / 1000);
        
        if (numberEl) {
            numberEl.textContent = seconds.toString();
            numberEl.classList.remove('go');
        }
        if (precisionEl) {
            // Show centiseconds precision (10ms = 0.01s)
            precisionEl.textContent = `${(remaining / 1000).toFixed(2)}s`;
        }
        
        window.countdownAnimationFrame = requestAnimationFrame(updateCountdown);
    }
    
    window.countdownAnimationFrame = requestAnimationFrame(updateCountdown);
}

initFirebase().then(async (authenticated) => {
    if (!authenticated) {
        console.error('[Firebase] Authentication failed - real-time features disabled');
        return;
    }
    
    // Measure clock offset immediately when entering lobby (not during countdown)
    // This ensures synchronization is ready before the countdown starts
    console.log('[ClockSync] Starting early offset measurement...');
    window.serverTimeOffset = await measureServerOffset();
    window.offsetMeasured = true;
    console.log('[ClockSync] Early sync complete, offset:', window.serverTimeOffset, 'ms');
    
    window.lobbyPresenceManager = new LobbyPresenceManager(lobbyCode, currentPlayerId, currentPlayerData, isHostFirebase);
    
    window.lobbyPresenceManager.onPlayersChange = (presencePlayers) => {
        // Don't replace the full player list from Firebase presence
        // The authoritative player list comes from Laravel polling
        // Only update online status indicators for existing players
        
        // Update online/ready status indicators for each player card
        document.querySelectorAll('.player-card').forEach(card => {
            const playerId = parseInt(card.dataset.playerId);
            const presenceData = presencePlayers[playerId];
            
            if (presenceData) {
                // Player is online in Firebase
                card.classList.remove('player-offline');
                card.classList.add('player-online');
                
                // Update ready status if changed
                if (presenceData.ready) {
                    card.classList.add('is-ready');
                } else {
                    card.classList.remove('is-ready');
                }
            } else {
                // Player not in presence data - may be temporarily disconnected
                // Don't remove them from UI, just show offline indicator
                card.classList.add('player-offline');
                card.classList.remove('player-online');
            }
        });
        
        const playerCount = Object.keys(presencePlayers).length;
        const readyCount = Object.values(presencePlayers).filter(p => p.ready).length;
        const allReady = readyCount === playerCount && playerCount >= minPlayersFirebase;
        const allConnected = playerCount >= minPlayersFirebase;
        
        // Update ready count display
        const readyCountEl = document.getElementById('ready-count');
        if (readyCountEl) {
            const displayDenominator = Math.max(playerCount, minPlayersFirebase);
            readyCountEl.textContent = `${readyCount}/${displayDenominator}`;
        }
        
        // Find players who are connected but not ready
        const notReadyPlayers = Object.values(presencePlayers).filter(p => !p.ready);
        
        // Sync status indicator - only show when synchronized (green checkmark)
        const syncStatus = document.getElementById('sync-status');
        const syncStatusText = document.getElementById('sync-status-text');
        if (syncStatus && syncStatusText) {
            if (allReady && allConnected) {
                syncStatus.style.display = 'block';
                syncStatus.style.background = 'rgba(76, 175, 80, 0.2)';
                syncStatus.style.border = '1px solid rgba(76, 175, 80, 0.5)';
                syncStatus.style.color = '#4CAF50';
                syncStatusText.textContent = '✓ ' + translations.synchronized;
            } else {
                // Hide status messages - player cards show ready state via icons
                syncStatus.style.display = 'none';
            }
        }
        
        // Update start button - Firebase provides visual feedback, backend polling is authoritative
        const startBtn = document.getElementById('start-btn');
        if (startBtn) {
            startBtn.dataset.firebaseConnected = allConnected ? 'true' : 'false';
            startBtn.dataset.allReady = allReady ? 'true' : 'false';
            
            // Backend polling updates backendDisabled state (see polling handler line ~3133)
            // Firebase only upgrades to enabled, never downgrades
            const backendDisabled = startBtn.dataset.backendDisabled === 'true';
            const backendCanStart = startBtn.dataset.backendCanStart === 'true';
            
            if (backendDisabled) {
                // Backend explicitly revoked permission - always disable
                startBtn.disabled = true;
            } else if (allReady && allConnected) {
                // Firebase confirms all ready - enable button
                startBtn.disabled = false;
            } else if (backendCanStart) {
                // Backend approved but Firebase not yet synced - keep enabled
                startBtn.disabled = false;
            } else {
                // Neither approved - keep disabled
                startBtn.disabled = true;
            }
        }
        
        // Waiting message removed - status is shown via player cards with ready indicators
        
        // For Duo mode: Auto-start countdown when all ready
        if (mode === 'duo' && allReady && allConnected && !window.countdownInitiated) {
            window.countdownInitiated = true;
            console.log('[Countdown] All players ready! Starting countdown...');
            startDuoCountdown(presencePlayers);
        }
        
        window.dispatchEvent(new CustomEvent('lobbyPlayersUpdated', { detail: { players: presencePlayers, allReady, allConnected } }));
    };
    
    await window.lobbyPresenceManager.joinLobby();
    console.log('[Presence] Manager initialized for lobby:', lobbyCode);
    
    window.webrtcManager = new WebRTCManager(lobbyCode, currentPlayerId, mode, teamId);
    await window.webrtcManager.initialize();
    console.log('[WebRTC] Manager assigned to window.webrtcManager:', !!window.webrtcManager);

    window.lobbyChatManager = new LobbyChatManager(lobbyCode, currentPlayerId, currentPlayerName);
    window.lobbyChatManager.startListening();
    console.log('[LobbyChat] Manager initialized for lobby:', lobbyCode);

    window.dispatchEvent(new CustomEvent('webrtcReady'));
    console.log('[WebRTC] Manager initialized for lobby:', lobbyCode, '- Player:', currentPlayerId);
    
    // Listener Firebase pour le signal de démarrage synchronisé
    function normalizeMatchIdJs(matchId) {
        if (typeof matchId === 'number' && matchId > 0) {
            return matchId;
        }
        const matchIdStr = String(matchId);
        const numericId = parseInt(matchIdStr.replace(/[^0-9]/g, ''), 10) || 0;
        if (numericId === 0) {
            // Utiliser CRC32 pour les codes purement alphabétiques
            let crc = 0xFFFFFFFF;
            for (let i = 0; i < matchIdStr.length; i++) {
                crc ^= matchIdStr.charCodeAt(i);
                for (let j = 0; j < 8; j++) {
                    crc = (crc >>> 1) ^ (crc & 1 ? 0xEDB88320 : 0);
                }
            }
            return ((crc ^ 0xFFFFFFFF) >>> 0) & 0x7FFFFFFF;
        }
        return numericId;
    }
    
    // IMPORTANT: Toujours utiliser lobbyCode pour le chemin Firebase
    // Le backend (DuoFirestoreService) publie avec lobbyCode, pas matchId
    // Cela assure la cohérence entre lobby et gameplay
    const normalizedId = normalizeMatchIdJs(lobbyCode);
    const gameDocRef = doc(db, 'games', `duo-match-${normalizedId}`);
    let gameStartHandled = false;
    
    console.log('[Firebase] Listening for game start signal on:', `games/duo-match-${normalizedId}`, '(lobbyCode:', lobbyCode, ')');
    
    onSnapshot(gameDocRef, (docSnap) => {
        if (!docSnap.exists() || gameStartHandled) return;
        
        const data = docSnap.data();
        
        if (data.gameStarted === true) {
            gameStartHandled = true;
            console.log('[Firebase] Game start signal received! Navigating to game...');
            
            // Arrêter le polling et les managers
            if (pollingInterval) clearInterval(pollingInterval);
            if (window.lobbyPresenceManager) window.lobbyPresenceManager.cleanup();
            if (window.webrtcManager) window.webrtcManager.cleanup();
            
            // Naviguer vers la page de jeu avec les paramètres
            const settings = @json($settings ?? []);
            submitGameStart(mode, settings);
        }
    }, (error) => {
        console.error('[Firebase] Game start listener error:', error);
    });
});

if (window.useSocketIO && window.matchRoomId && typeof DuoSocketClient !== 'undefined') {
    (async function initSocketIO() {
        console.log('[Socket.IO] Initializing connection to Game Server...');
        window.duoSocketConnected = false;
        
        DuoSocketClient.onConnect = () => {
            console.log('[Socket.IO] Connected to Game Server');
            window.duoSocketConnected = true;
            
            DuoSocketClient.joinRoom(window.matchRoomId, window.matchLobbyCode, {
                playerId: currentPlayerId,
                playerName: currentPlayerData.name || '',
                avatarId: currentPlayerData.avatar || null,
                token: window.matchPlayerToken
            });
        };
        
        DuoSocketClient.onDisconnect = (reason) => {
            console.log('[Socket.IO] Disconnected:', reason);
            window.duoSocketConnected = false;
        };
        
        DuoSocketClient.onError = (error) => {
            console.error('[Socket.IO] Error:', error);
        };
        
        DuoSocketClient.onPlayerJoined = (event) => {
            console.log('[Socket.IO] Player joined:', event);
            const card = document.querySelector(`.player-card[data-player-id="${event.playerId}"]`);
            if (card) {
                card.classList.remove('player-offline');
                card.classList.add('player-online');
            }
        };
        
        DuoSocketClient.onPlayerLeft = (event) => {
            console.log('[Socket.IO] Player left:', event);
            const card = document.querySelector(`.player-card[data-player-id="${event.playerId}"]`);
            if (card) {
                card.classList.add('player-offline');
                card.classList.remove('player-online');
            }
        };
        
        DuoSocketClient.onPlayerReady = (data) => {
            console.log('[Socket.IO] Player ready state changed:', data);
            const card = document.querySelector(`.player-card[data-player-id="${data.playerId}"]`);
            if (card) {
                if (data.isReady) {
                    card.classList.add('is-ready');
                } else {
                    card.classList.remove('is-ready');
                }
            }
        };
        
        DuoSocketClient.onLobbyState = (state) => {
            console.log('[Socket.IO] Lobby state received:', state);
            
            if (state && state.players) {
                const playerCount = Object.keys(state.players).length;
                const readyCount = Object.values(state.players).filter(p => p.isReady).length;
                const allReady = readyCount === playerCount && playerCount >= minPlayersFirebase;
                
                const readyCountEl = document.getElementById('ready-count');
                if (readyCountEl) {
                    const displayDenominator = Math.max(playerCount, minPlayersFirebase);
                    readyCountEl.textContent = `${readyCount}/${displayDenominator}`;
                }
                
                Object.entries(state.players).forEach(([playerId, playerData]) => {
                    const card = document.querySelector(`.player-card[data-player-id="${playerId}"]`);
                    if (card) {
                        card.classList.remove('player-offline');
                        card.classList.add('player-online');
                        if (playerData.isReady) {
                            card.classList.add('is-ready');
                        } else {
                            card.classList.remove('is-ready');
                        }
                    }
                });
                
                if (mode === 'duo' && allReady && !window.countdownInitiated) {
                    window.countdownInitiated = true;
                    console.log('[Socket.IO] All players ready! Starting countdown...');
                    startDuoCountdown(state.players);
                }
            }
        };
        
        DuoSocketClient.onPhaseChanged = (data) => {
            console.log('[Socket.IO] Phase changed:', data);
            if (data.phase === 'playing' || data.phase === 'question') {
                console.log('[Socket.IO] Game started! Navigating to game...');
                
                if (pollingInterval) clearInterval(pollingInterval);
                if (window.lobbyPresenceManager) window.lobbyPresenceManager.cleanup();
                if (window.webrtcManager) window.webrtcManager.cleanup();
                
                const settings = @json($settings ?? []);
                submitGameStart(mode, settings);
            }
        };
        
        try {
            await DuoSocketClient.connect(window.gameServerUrl, window.matchPlayerToken);
            console.log('[Socket.IO] Connection established');
        } catch (error) {
            console.error('[Socket.IO] Failed to connect:', error);
            console.log('[Socket.IO] Falling back to Firebase-only mode');
            window.duoSocketConnected = false;
        }
    })();
}

window.addEventListener('beforeunload', () => {
    if (window.lobbyPresenceManager) {
        window.lobbyPresenceManager.cleanup();
    }
    if (window.webrtcManager) {
        window.webrtcManager.cleanup();
    }
    if (window.lobbyChatManager) {
        window.lobbyChatManager.stopListening();
    }
    if (window.duoSocketConnected && typeof DuoSocketClient !== 'undefined') {
        DuoSocketClient.disconnect();
    }
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        // Don't cleanup on visibility change - just pause heartbeats
        // Full cleanup only on beforeunload/pagehide (actual page close)
        if (window.lobbyPresenceManager && window.lobbyPresenceManager.pauseHeartbeat) {
            window.lobbyPresenceManager.pauseHeartbeat();
        }
        console.log('[Visibility] Page hidden - heartbeat paused (not cleaned up)');
    } else if (document.visibilityState === 'visible') {
        // Resume heartbeat when page becomes visible again
        if (window.lobbyPresenceManager && window.lobbyPresenceManager.resumeHeartbeat) {
            window.lobbyPresenceManager.resumeHeartbeat();
        }
        console.log('[Visibility] Page visible - heartbeat resumed');
    }
});

window.addEventListener('pagehide', () => {
    if (window.lobbyPresenceManager) {
        window.lobbyPresenceManager.cleanup();
    }
    if (window.webrtcManager) {
        window.webrtcManager.cleanup();
    }
    if (window.lobbyChatManager) {
        window.lobbyChatManager.stopListening();
    }
    if (window.duoSocketConnected && typeof DuoSocketClient !== 'undefined') {
        DuoSocketClient.disconnect();
    }
});
</script>
@endif

@if(isset($matchId) && $matchId)
<script type="module">
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { getAuth, signInAnonymously, onAuthStateChanged } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';
import { getFirestore, doc, onSnapshot } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore.js';

const firebaseConfig = {
    apiKey: "AIzaSyAB5-A0NsX9I9eFX76ZBYQQG_bagWp_dHw",
    authDomain: "strategybuzzergame.firebaseapp.com",
    projectId: "strategybuzzergame",
    storageBucket: "strategybuzzergame.appspot.com",
    messagingSenderId: "68047817391",
    appId: "1:68047817391:web:ba6b3bc148ef187bfeae9a"
};

const app = initializeApp(firebaseConfig, 'match-watcher');
const auth = getAuth(app);
const db = getFirestore(app);

const matchId = {{ $matchId }};
const currentUserId = {{ $currentPlayerId }};
const isHost = {{ $isHost ? 'true' : 'false' }};
const defaultGuestName = @json(__('Invité'));
const declinedMessage = @json(__('a refusé votre invitation'));

function startMatchListener() {
    const matchRef = doc(db, 'duo_matches', String(matchId));
    let declineHandled = false;

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
    }, (error) => {
        console.error('[Firebase] Match listener error:', error);
    });
}

onAuthStateChanged(auth, (user) => {
    if (user) {
        console.log('[Firebase] Match watcher authenticated');
        startMatchListener();
    }
});

signInAnonymously(auth).catch(e => console.error('[Firebase] Auth error:', e));
</script>
@endif
@endsection
