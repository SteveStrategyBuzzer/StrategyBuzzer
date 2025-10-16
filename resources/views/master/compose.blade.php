@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.compose-container {
    max-width: 1200px;
    margin: 0 auto;
}

.compose-title {
    font-size: 2.5rem;
    font-weight: 900;
    margin-bottom: 2rem;
    text-align: center;
    color: #FFD700;
}

.tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid rgba(255, 255, 255, 0.2);
}

.tab {
    padding: 1rem 2rem;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border-bottom: 3px solid transparent;
}

.tab.active {
    color: #FFD700;
    border-bottom-color: #FFD700;
}

.tab:hover {
    color: #fff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 1.5rem;
}

.question-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.question-item {
    background: rgba(255, 255, 255, 0.05);
    padding: 1rem;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.search-box {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.search-input {
    flex: 1;
    padding: 1rem;
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    font-size: 1rem;
}

.btn-generate {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 1rem 2rem;
    border-radius: 8px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
}

.btn-primary {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 1rem 3rem;
    border-radius: 10px;
    font-size: 1.2rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    padding: 1rem 3rem;
    border-radius: 10px;
    font-size: 1.2rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.3);
}

.buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

.header-back {
    position: absolute;
    top: 20px;
    left: 20px;
    background: white;
    color: #003DA5;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
}

.game-info {
    background: rgba(255, 215, 0, 0.1);
    border: 1px solid rgba(255, 215, 0, 0.3);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .tabs {
        flex-direction: column;
    }
    
    .tab {
        border-bottom: none;
        border-left: 3px solid transparent;
    }
    
    .tab.active {
        border-bottom: none;
        border-left-color: #FFD700;
    }
}
</style>

<a href="{{ route('menu') }}" class="header-back">← Menu</a>

<div class="compose-container">
    <h1 class="compose-title">📝 Composer le Quiz</h1>
    
    <div class="game-info">
        <strong>Partie :</strong> {{ $game->name }} | 
        <strong>Mode :</strong> {{ ucfirst(str_replace('_', ' ', $game->mode)) }} | 
        <strong>Questions :</strong> {{ $game->total_questions }} | 
        <strong>Participants :</strong> {{ $game->participants_expected }}
    </div>
    
    <div class="tabs">
        <button class="tab active" data-tab="automatique">🤖 Automatique</button>
        <button class="tab" data-tab="personnalise">✏️ Personnalisé</button>
        <button class="tab" data-tab="recherche">🔍 Recherche</button>
    </div>
    
    <!-- Tab 1: Automatique -->
    <div id="automatique" class="tab-content active">
        <div class="section">
            <h3 style="color: #FFD700; margin-bottom: 1rem;">Génération Automatique par IA</h3>
            <p style="opacity: 0.9; margin-bottom: 1.5rem;">
                L'IA va générer {{ $game->total_questions }} questions basées sur vos paramètres :
            </p>
            <ul style="opacity: 0.9; margin-left: 2rem; margin-bottom: 1.5rem;">
                <li>Domaine : {{ $game->theme ?? $game->school_subject ?? 'Personnalisé' }}</li>
                <li>Types : {{ implode(', ', $game->question_types) }}</li>
                <li>Langue : {{ implode(', ', $game->languages) }}</li>
            </ul>
            
            <div class="buttons">
                <form action="#" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn-primary">
                        ✨ Générer les questions automatiquement
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Tab 2: Personnalisé -->
    <div id="personnalise" class="tab-content">
        <div class="section">
            <h3 style="color: #FFD700; margin-bottom: 1rem;">Créer vos Questions Manuellement</h3>
            <p style="opacity: 0.9; margin-bottom: 1.5rem;">
                Créez {{ $game->total_questions }} questions personnalisées une par une.
            </p>
            
            <div class="question-list">
                @for ($i = 1; $i <= min(5, $game->total_questions); $i++)
                    <div class="question-item">
                        <span>Question {{ $i }} : <em style="opacity: 0.7;">Non créée</em></span>
                        <button class="btn-generate">+ Créer</button>
                    </div>
                @endfor
                
                @if ($game->total_questions > 5)
                    <p style="opacity: 0.7; text-align: center;">... et {{ $game->total_questions - 5 }} autres questions</p>
                @endif
            </div>
            
            <div class="buttons" style="margin-top: 2rem;">
                <button class="btn-primary">Commencer la création</button>
            </div>
        </div>
    </div>
    
    <!-- Tab 3: Recherche -->
    <div id="recherche" class="tab-content">
        <div class="section">
            <h3 style="color: #FFD700; margin-bottom: 1rem;">Création par Recherche</h3>
            <p style="opacity: 0.9; margin-bottom: 1.5rem;">
                Tapez un mot-clé (ex: "spéléologue") et l'IA créera un quiz avec vos critères.
            </p>
            
            <div class="search-box">
                <input 
                    type="text" 
                    class="search-input" 
                    placeholder="Ex: spéléologue, astronomie, histoire de France..."
                    id="searchKeyword"
                >
                <button class="btn-generate" id="btnSearchGenerate">🔍 Générer</button>
            </div>
            
            <div id="searchPreview" style="display: none;">
                <h4 style="color: #FFD700; margin-bottom: 1rem;">Aperçu de la recherche</h4>
                <p style="opacity: 0.9;">
                    Le quiz sera généré avec :
                </p>
                <ul style="opacity: 0.9; margin-left: 2rem; margin-top: 1rem;">
                    <li>Mot-clé : <strong id="keywordDisplay"></strong></li>
                    <li>{{ $game->total_questions }} questions</li>
                    <li>Types : {{ implode(', ', $game->question_types) }}</li>
                    <li>Langue : {{ implode(', ', $game->languages) }}</li>
                </ul>
                
                <div class="buttons" style="margin-top: 1.5rem;">
                    <button class="btn-primary">✨ Confirmer et générer</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="buttons" style="margin-top: 2rem;">
        <a href="{{ route('menu') }}" class="btn-secondary">Annuler</a>
    </div>
</div>

<script>
// Tab switching
const tabs = document.querySelectorAll('.tab');
const tabContents = document.querySelectorAll('.tab-content');

tabs.forEach(tab => {
    tab.addEventListener('click', () => {
        const targetTab = tab.dataset.tab;
        
        tabs.forEach(t => t.classList.remove('active'));
        tabContents.forEach(tc => tc.classList.remove('active'));
        
        tab.classList.add('active');
        document.getElementById(targetTab).classList.add('active');
    });
});

// Search functionality
const searchInput = document.getElementById('searchKeyword');
const btnSearchGenerate = document.getElementById('btnSearchGenerate');
const searchPreview = document.getElementById('searchPreview');
const keywordDisplay = document.getElementById('keywordDisplay');

btnSearchGenerate.addEventListener('click', () => {
    const keyword = searchInput.value.trim();
    if (keyword) {
        keywordDisplay.textContent = keyword;
        searchPreview.style.display = 'block';
    }
});
</script>
@endsection
