@extends('layouts.app')

@section('content')
<div class="league-welcome-container">
    <div class="welcome-card">
        <div class="welcome-header">
            <h1>🏆 BIENVENUE EN LIGUE INDIVIDUEL</h1>
            <p class="subtitle">Votre carrière 1v1 permanente commence ici</p>
        </div>

        <div class="welcome-content">
            <div class="info-section">
                <div class="icon">📊</div>
                <h3>Système de Carrière</h3>
                <p>Progressez à travers les divisions en gagnant des points contre des adversaires de votre niveau</p>
            </div>

            <div class="info-section">
                <div class="icon">⚔️</div>
                <h3>Matchmaking Équitable</h3>
                <p>Affrontez des joueurs de votre division dans des matchs best-of-3</p>
            </div>

            <div class="info-section">
                <div class="icon">🎯</div>
                <h3>Points de Division</h3>
                <p>+1 vs plus faible, +2 vs égal, +5 vs plus fort, -2 si défaite</p>
            </div>
        </div>

        <div class="starting-stats">
            <h3>Vos Statistiques de Départ</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-label">Niveau de Départ</span>
                    <span class="stat-value">{{ $stats->level }}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Division</span>
                    <span class="stat-value">{{ ucfirst($division->division ?? 'Bronze') }}</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Points</span>
                    <span class="stat-value">{{ $division->points ?? 0 }}</span>
                </div>
            </div>
            <p class="note">Votre niveau Duo a été transféré comme niveau de départ</p>
        </div>

        <button onclick="window.location.href='{{ route('league.individual.lobby') }}'" class="btn-start">
            COMMENCER MA CARRIÈRE →
        </button>
    </div>
</div>

<style>
.league-welcome-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
}

.welcome-card {
    background: white;
    border-radius: 20px;
    padding: 40px;
    max-width: 800px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.welcome-header {
    text-align: center;
    margin-bottom: 40px;
}

.welcome-header h1 {
    font-size: 2.5em;
    color: #1a1a1a;
    margin-bottom: 10px;
}

.welcome-header .subtitle {
    font-size: 1.2em;
    color: #666;
}

.welcome-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.info-section {
    text-align: center;
}

.info-section .icon {
    font-size: 3em;
    margin-bottom: 15px;
}

.info-section h3 {
    font-size: 1.3em;
    color: #1a1a1a;
    margin-bottom: 10px;
}

.info-section p {
    color: #666;
    line-height: 1.6;
}

.starting-stats {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.starting-stats h3 {
    text-align: center;
    font-size: 1.5em;
    margin-bottom: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 15px;
}

.stat-item {
    text-align: center;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.stat-label {
    font-size: 0.9em;
    opacity: 0.9;
}

.stat-value {
    font-size: 2em;
    font-weight: bold;
}

.starting-stats .note {
    text-align: center;
    opacity: 0.9;
    font-size: 0.95em;
    margin-top: 10px;
}

.btn-start {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.3em;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-start:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
}

@media (max-width: 768px) {
    .welcome-card {
        padding: 25px;
    }

    .welcome-header h1 {
        font-size: 1.8em;
    }

    .welcome-content {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
console.log('League Individual Welcome page loaded');
</script>
@endsection
