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

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 1rem;
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

.history-item {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.8rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.history-left {
    flex: 1;
}

.history-name {
    font-weight: 600;
    margin-bottom: 0.3rem;
}

.history-details {
    font-size: 0.85rem;
    opacity: 0.7;
}

.history-code {
    background: rgba(255, 215, 0, 0.2);
    color: #FFD700;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 700;
    font-size: 1.1rem;
}

.status-badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

.status-active {
    background: rgba(0, 212, 0, 0.3);
    color: #00D400;
}

.status-finished {
    background: rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.6);
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
    margin-top: 1rem;
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

<a href="{{ route('master.create') }}" class="header-back">Retour</a>

<div class="codes-container">
    <h1 class="codes-title">Vos Codes</h1>
    
    <!-- Partie actuelle -->
    <div class="section-title">Partie en cours</div>
    <div class="code-section">
        <div class="code-label">Code d'accès</div>
        <div class="code-display">{{ $game->access_code }}</div>
        <div class="code-info">Partagez ce code aux joueurs</div>
        
        <div style="opacity: 0.9; font-size: 0.95rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
            <div style="margin-bottom: 0.5rem;">📝 <strong>{{ $game->name }}</strong></div>
            <div style="margin-bottom: 0.5rem;">👥 {{ $game->participants_expected }} joueurs max</div>
            <div style="margin-bottom: 0.5rem;">❓ {{ $game->total_questions }} questions</div>
            <div>🌍 {{ implode(', ', $game->languages) }}</div>
        </div>
        
        <button class="btn-start" onclick="window.location.href='{{ route('master.lobby', $game->id) }}'">
            Démarrer le Quiz
        </button>
    </div>
    
    <!-- Historique -->
    <div class="section-title" style="margin-top: 2rem;">Historique</div>
    <div class="code-section">
        @php
            $history = \App\Models\MasterGame::where('host_user_id', Auth::id())
                ->where('id', '!=', $game->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        @endphp
        
        @if($history->count() > 0)
            @foreach($history as $oldGame)
                <div class="history-item">
                    <div class="history-left">
                        <div class="history-name">
                            {{ $oldGame->name }}
                            <span class="status-badge {{ $oldGame->status === 'finished' ? 'status-finished' : 'status-active' }}">
                                {{ $oldGame->status === 'finished' ? 'Terminé' : 'Actif' }}
                            </span>
                        </div>
                        <div class="history-details">
                            {{ $oldGame->created_at->format('d/m/Y H:i') }} • {{ $oldGame->total_questions }} questions
                        </div>
                    </div>
                    <div class="history-code">{{ $oldGame->access_code }}</div>
                </div>
            @endforeach
        @else
            <div style="text-align: center; opacity: 0.6; padding: 2rem 0;">
                Aucune partie précédente
            </div>
        @endif
    </div>
</div>
@endsection
