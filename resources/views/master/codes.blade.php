@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.codes-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 1rem;
}

.codes-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 1.5rem;
    text-align: center;
    color: #FFD700;
}

.code-section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.code-label {
    font-size: 0.9rem;
    opacity: 0.8;
    margin-bottom: 0.5rem;
}

.code-display {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 215, 0, 0.5);
    border-radius: 10px;
    padding: 1rem;
    font-size: 2rem;
    font-weight: 900;
    text-align: center;
    color: #FFD700;
    letter-spacing: 0.2rem;
}

.code-info {
    margin-top: 0.5rem;
    font-size: 0.85rem;
    opacity: 0.7;
    text-align: center;
}

.qr-code {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    margin: 1rem 0;
}

.btn-start {
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
    margin-top: 2rem;
}

.btn-start:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 212, 0, 0.4);
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
}

@media (max-width: 768px) {
    .header-back {
        top: 10px;
        left: 10px;
        padding: 6px 12px;
        font-size: 0.9rem;
    }
}
</style>

<a href="{{ route('menu') }}" class="header-back">Menu</a>

<div class="codes-container">
    <h1 class="codes-title">Codes de la Partie</h1>
    
    <!-- Code d'accès principal -->
    <div class="code-section">
        <div class="code-label">Code d'accès</div>
        <div class="code-display">{{ $game->access_code }}</div>
        <div class="code-info">Les joueurs doivent entrer ce code pour rejoindre</div>
    </div>
    
    <!-- QR Code (optionnel) -->
    <div class="code-section">
        <div class="code-label">QR Code</div>
        <div class="qr-code">
            <div style="width: 200px; height: 200px; margin: 0 auto; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">
                QR Code
            </div>
        </div>
        <div class="code-info">Scanner pour rejoindre rapidement</div>
    </div>
    
    <!-- Informations de la partie -->
    <div class="code-section">
        <div style="opacity: 0.9; font-size: 0.95rem;">
            <div style="margin-bottom: 0.5rem;">📝 <strong>{{ $game->name }}</strong></div>
            <div style="margin-bottom: 0.5rem;">👥 {{ $game->participants_expected }} joueurs max</div>
            <div style="margin-bottom: 0.5rem;">❓ {{ $game->total_questions }} questions</div>
            <div>🌍 {{ implode(', ', $game->languages) }}</div>
        </div>
    </div>
    
    <button class="btn-start" onclick="window.location.href='{{ route('master.lobby', $game->id) }}'">
        Démarrer le Quiz
    </button>
</div>
@endsection
