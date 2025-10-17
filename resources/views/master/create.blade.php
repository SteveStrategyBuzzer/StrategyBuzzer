@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.create-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 1rem;
}

.create-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 1.5rem;
    text-align: center;
    color: #FFD700;
}

.section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.2rem;
    margin-bottom: 1rem;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.8rem;
    color: #FFD700;
    text-align: center;
}

.form-group {
    margin-bottom: 1.2rem;
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.form-input {
    width: 100%;
    padding: 0.8rem;
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    font-size: 1rem;
}

.form-select {
    width: 100%;
    padding: 0.8rem;
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    font-size: 1rem;
    cursor: pointer;
}

.form-select option {
    background: #003DA5;
    color: #fff;
    padding: 0.5rem;
}

.checkbox-group, .radio-group {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.checkbox-label, .radio-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-input, .radio-input {
    width: 20px;
    height: 20px;
}

.buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 2rem;
}

.btn-continue {
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

.btn-continue:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
}

.btn-cancel {
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
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.3);
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
}

.input-with-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.input-number {
    width: 60px;
    padding: 0.5rem;
    border-radius: 6px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
    color: #fff;
    font-size: 1rem;
    text-align: center;
}

/* Paysage : 2 colonnes */
@media (orientation: landscape) and (min-width: 768px) {
    .create-container {
        max-width: 900px;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .section-full {
        grid-column: 1 / -1;
    }
}
</style>

<a href="{{ route('menu') }}" class="header-back">Menu</a>

<div class="create-container">
    <h1 class="create-title">Général</h1>
    
    <form action="{{ route('master.store') }}" method="POST" id="createForm">
        @csrf
        
        <!-- Nom du Quiz (pleine largeur) -->
        <div class="section section-full">
            <div class="section-title">Nom du Quizz</div>
            <input type="text" name="name" class="form-input" placeholder="Ex: Quiz du samedi" required>
        </div>
        
        <div class="form-grid">
        <!-- Langue + Participants -->
        <div class="section">
            <div class="form-group">
                <div class="checkbox-group" style="justify-content: center;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="languages[]" value="FR" class="checkbox-input" checked>
                        <span>Langue FR</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="languages[]" value="EN" class="checkbox-input">
                        <span>EN</span>
                    </label>
                </div>
            </div>
            <div class="form-group" style="margin-top: 1rem;">
                <div class="input-with-label" style="justify-content: center;">
                    <label>Participants (3-40)</label>
                    <input type="number" name="participants_expected" class="input-number" value="10" min="3" max="40" required>
                    <span>/40</span>
                </div>
            </div>
        </div>
        
        <!-- Questions -->
        <div class="section">
            <div class="section-title">Questions</div>
            
            <div class="form-group">
                <label class="form-label" style="text-align: center;">Nombre</label>
                <div class="radio-group" style="justify-content: center;">
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="10" class="radio-input">
                        <span>010</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="20" class="radio-input" checked>
                        <span>020</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="30" class="radio-input">
                        <span>030</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="40" class="radio-input">
                        <span>040</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 1rem;">
                <label class="form-label" style="text-align: center;">Types</label>
                <div class="checkbox-group" style="justify-content: center;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="true_false" class="checkbox-input type-checkbox">
                        <span>Vrai/Faux</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="multiple_choice" class="checkbox-input type-checkbox" checked>
                        <span>QCM</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="image" class="checkbox-input type-checkbox">
                        <span>Image</span>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Mode de Jeu -->
        <div class="section">
            <div class="section-title">Mode de Jeu</div>
            <div class="radio-group" style="flex-direction: column; gap: 0.8rem; align-items: flex-start;">
                <label class="radio-label">
                    <input type="radio" name="mode" value="face_to_face" class="radio-input">
                    <span>Face à Face</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="mode" value="groups" class="radio-input">
                    <span>En Groupe</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="mode" value="one_vs_all" class="radio-input" checked>
                    <span>1 contre Tous</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="mode" value="podium" class="radio-input">
                    <span>Podium</span>
                </label>
            </div>
        </div>
        
        <!-- Domaine -->
        <div class="section">
            <div class="section-title">Domaine</div>
            
            <div class="form-group">
                <div class="radio-group" style="justify-content: center; gap: 2rem;">
                    <label class="radio-label">
                        <input type="radio" name="domain_type" value="theme" class="radio-input domain-radio" checked>
                        <span>Thème</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="domain_type" value="scolaire" class="radio-input domain-radio">
                        <span>Scolaire</span>
                    </label>
                </div>
            </div>
            
            <div id="themeSection" class="form-group" style="margin-top: 1rem;">
                <select name="theme" class="form-select" style="text-align: center; font-weight: 600;">
                    <option value="Géographie">Géographie</option>
                    <option value="Histoire">Histoire</option>
                    <option value="Arts et Culture">Arts et Culture</option>
                    <option value="Sciences et Nature">Sciences et Nature</option>
                    <option value="Sports et Loisirs">Sports et Loisirs</option>
                    <option value="Divertissement">Divertissement</option>
                    <option value="Technologie">Technologie</option>
                    <option value="Société">Société</option>
                    <option value="Général">Général</option>
                </select>
            </div>
            
            <div id="scolaireSection" class="form-group" style="display: none; margin-top: 1rem;">
                <select name="school_country" class="form-select">
                    <option value="France">France</option>
                    <option value="Canada">Canada</option>
                    <option value="Belgique">Belgique</option>
                    <option value="Suisse">Suisse</option>
                </select>
                
                <select name="school_level" class="form-select" style="margin-top: 0.8rem;">
                    <option value="Primaire">Primaire</option>
                    <option value="Collège">Collège</option>
                    <option value="Lycée">Lycée</option>
                    <option value="Cégep">Cégep</option>
                    <option value="Université">Université</option>
                </select>
                
                <select name="school_subject" class="form-select" style="margin-top: 0.8rem;">
                    <option value="Mathématiques">Mathématiques</option>
                    <option value="Français">Français</option>
                    <option value="Histoire-Géographie">Histoire-Géo</option>
                    <option value="Sciences">Sciences</option>
                    <option value="Anglais">Anglais</option>
                </select>
            </div>
        </div>
        </div>
        
        <!-- Boutons de création (pleine largeur) -->
        <div class="section-full" style="margin-top: 2rem;">
            <div class="buttons" style="gap: 1rem;">
                <button type="submit" name="creation_mode" value="automatique" class="btn-continue" style="flex: 1; background: linear-gradient(135deg, #FFD700, #FFA500); font-size: 1.1rem;">
                    Automatique
                </button>
                <button type="submit" name="creation_mode" value="personnalise" class="btn-continue" style="flex: 1; background: linear-gradient(135deg, #00D4FF, #0099CC); font-size: 1.1rem;">
                    Personalisé
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// Domain type logic
const domainRadios = document.querySelectorAll('.domain-radio');
const themeSection = document.getElementById('themeSection');
const scolaireSection = document.getElementById('scolaireSection');

if (domainRadios.length > 0 && themeSection && scolaireSection) {
    domainRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'theme') {
                themeSection.style.display = 'block';
                scolaireSection.style.display = 'none';
            } else if (this.value === 'scolaire') {
                themeSection.style.display = 'none';
                scolaireSection.style.display = 'block';
            }
        });
    });
}
</script>
@endsection
