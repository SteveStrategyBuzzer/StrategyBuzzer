@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.structure-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 1rem;
}

.structure-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    text-align: center;
    color: #FFD700;
}

.structure-subtitle {
    font-size: 1rem;
    text-align: center;
    opacity: 0.8;
    margin-bottom: 2rem;
}

.structure-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .structure-grid {
        grid-template-columns: 1fr;
    }
}

.structure-card {
    background: rgba(255, 255, 255, 0.1);
    border: 3px solid transparent;
    border-radius: 16px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.structure-card:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-4px);
}

.structure-card.selected {
    border-color: #FFD700;
    background: rgba(255, 215, 0, 0.15);
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
}

.structure-card input[type="radio"] {
    display: none;
}

.card-icon {
    font-size: 3rem;
    margin-bottom: 0.8rem;
    text-align: center;
}

.card-title {
    font-size: 1.2rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 0.5rem;
    color: #FFD700;
}

.card-description {
    font-size: 0.9rem;
    text-align: center;
    opacity: 0.85;
    margin-bottom: 0.8rem;
    line-height: 1.4;
}

.card-players {
    font-size: 0.85rem;
    text-align: center;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.5rem;
    border-radius: 8px;
    font-weight: 600;
}

.team-options {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: none;
}

.team-options.visible {
    display: block;
}

.team-options h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #FFD700;
    margin-bottom: 1rem;
    text-align: center;
}

.slider-group {
    margin-bottom: 1.5rem;
}

.slider-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.slider-value {
    background: #FFD700;
    color: #003DA5;
    padding: 0.2rem 0.8rem;
    border-radius: 20px;
    font-weight: 700;
}

.slider-input {
    width: 100%;
    height: 8px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.2);
    outline: none;
    -webkit-appearance: none;
}

.slider-input::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #FFD700;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.slider-input::-moz-range-thumb {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #FFD700;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.btn-continue {
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
    margin: 0 auto;
    width: 100%;
    max-width: 400px;
}

.btn-continue:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 212, 0, 0.4);
}

.btn-continue:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
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

@media (max-width: 768px) {
    .header-back {
        top: 10px;
        left: 10px;
        padding: 6px 12px;
        font-size: 0.9rem;
    }
    
    .structure-title {
        font-size: 1.5rem;
        margin-top: 2rem;
    }
    
    .card-icon {
        font-size: 2.5rem;
    }
    
    .card-title {
        font-size: 1.1rem;
    }
}

.check-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #FFD700;
    color: #003DA5;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    font-weight: 900;
    font-size: 1rem;
}

.structure-card.selected .check-badge {
    display: flex;
}
</style>

<a href="{{ route('master.compose', $game->id) }}" class="header-back">← {{ __('Retour') }}</a>

<div class="structure-container">
    <h1 class="structure-title">🎮 {{ __('Structure du jeu') }}</h1>
    <p class="structure-subtitle">{{ __('Choisissez comment les joueurs vont s\'affronter') }}</p>
    
    <form action="{{ route('master.structure.save', $game->id) }}" method="POST" id="structureForm">
        @csrf
        
        <div class="structure-grid">
            <label class="structure-card" data-structure="free_for_all">
                <input type="radio" name="game_structure" value="free_for_all" required>
                <span class="check-badge">✓</span>
                <div class="card-icon">🏃</div>
                <div class="card-title">{{ __('Chacun pour soi') }}</div>
                <div class="card-description">{{ __('Compétition individuelle. Chaque joueur joue pour lui-même et cumule ses propres points.') }}</div>
                <div class="card-players">👥 {{ __('Jusqu\'à 40 joueurs') }}</div>
            </label>
            
            <label class="structure-card" data-structure="team_open_skills">
                <input type="radio" name="game_structure" value="team_open_skills" required>
                <span class="check-badge">✓</span>
                <div class="card-icon">⚔️</div>
                <div class="card-title">{{ __('Face à Face Multiple') }}</div>
                <div class="card-description">{{ __('2 équipes s\'affrontent. Tous les joueurs peuvent répondre et utiliser leurs compétences.') }}</div>
                <div class="card-players">👥 {{ __('2 équipes × 20 joueurs max') }}</div>
            </label>
            
            <label class="structure-card" data-structure="team_buzzer_only">
                <input type="radio" name="game_structure" value="team_buzzer_only" required>
                <span class="check-badge">✓</span>
                <div class="card-icon">🔔</div>
                <div class="card-title">{{ __('Face à Face Simple') }}</div>
                <div class="card-description">{{ __('2 équipes. Seul le joueur au buzzer peut répondre et utiliser ses compétences.') }}</div>
                <div class="card-players">👥 {{ __('2 équipes × 20 joueurs max') }}</div>
            </label>
            
            <label class="structure-card" data-structure="multi_team">
                <input type="radio" name="game_structure" value="multi_team" required>
                <span class="check-badge">✓</span>
                <div class="card-icon">🏆</div>
                <div class="card-title">{{ __('Multi-Équipes') }}</div>
                <div class="card-description">{{ __('3 à 8 petites équipes jouent le même quiz simultanément.') }}</div>
                <div class="card-players">👥 {{ __('3-8 équipes') }}</div>
            </label>
        </div>
        
        <div class="team-options" id="teamOptions">
            <h3>⚙️ {{ __('Options d\'équipe') }}</h3>
            
            <div class="slider-group" id="teamCountGroup" style="display: none;">
                <div class="slider-label">
                    <span>{{ __('Nombre d\'équipes') }}</span>
                    <span class="slider-value" id="teamCountValue">4</span>
                </div>
                <input type="range" name="team_count" id="teamCountSlider" class="slider-input" min="3" max="8" value="4">
            </div>
            
            <div class="slider-group">
                <div class="slider-label">
                    <span>{{ __('Joueurs max par équipe') }}</span>
                    <span class="slider-value" id="teamSizeValue">10</span>
                </div>
                <input type="range" name="team_size_cap" id="teamSizeSlider" class="slider-input" min="5" max="20" value="10">
            </div>
        </div>
        
        <button type="submit" class="btn-continue" id="continueBtn" disabled>
            {{ __('Continuer vers le Lobby') }} →
        </button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.structure-card');
    const teamOptions = document.getElementById('teamOptions');
    const teamCountGroup = document.getElementById('teamCountGroup');
    const continueBtn = document.getElementById('continueBtn');
    const teamCountSlider = document.getElementById('teamCountSlider');
    const teamSizeSlider = document.getElementById('teamSizeSlider');
    const teamCountValue = document.getElementById('teamCountValue');
    const teamSizeValue = document.getElementById('teamSizeValue');
    
    cards.forEach(card => {
        card.addEventListener('click', function() {
            cards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input').checked = true;
            
            const structure = this.dataset.structure;
            
            if (structure === 'team_open_skills' || structure === 'team_buzzer_only' || structure === 'multi_team') {
                teamOptions.classList.add('visible');
                
                if (structure === 'multi_team') {
                    teamCountGroup.style.display = 'block';
                } else {
                    teamCountGroup.style.display = 'none';
                }
            } else {
                teamOptions.classList.remove('visible');
            }
            
            continueBtn.disabled = false;
        });
    });
    
    teamCountSlider.addEventListener('input', function() {
        teamCountValue.textContent = this.value;
    });
    
    teamSizeSlider.addEventListener('input', function() {
        teamSizeValue.textContent = this.value;
    });
});
</script>
@endsection
