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
    max-width: 800px;
    margin: 0 auto;
}

.create-title {
    font-size: 2.5rem;
    font-weight: 900;
    margin-bottom: 2rem;
    text-align: center;
    color: #FFD700;
}

.section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.section-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #FFD700;
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
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
}

/* Responsive */
@media (max-width: 480px) {
    .create-title {
        font-size: 2rem;
    }
    .checkbox-group, .radio-group {
        flex-direction: column;
        gap: 1rem;
    }
    .buttons {
        flex-direction: column;
    }
    .btn-continue, .btn-cancel {
        width: 100%;
    }
}
</style>

<a href="{{ route('master.index') }}" class="header-back">← Retour</a>

<div class="create-container">
    <h1 class="create-title">🎮 Créer une Partie</h1>
    
    <form action="{{ route('master.store') }}" method="POST" id="createForm">
        @csrf
        
        <!-- A. Informations générales -->
        <div class="section">
            <div class="section-title">A. Informations générales</div>
            
            <div class="form-group">
                <label class="form-label">Nom de la partie</label>
                <input type="text" name="name" class="form-input" placeholder="Ex: Quiz du samedi soir" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Langue</label>
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="languages[]" value="FR" class="checkbox-input" checked>
                        <span>Français</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="languages[]" value="EN" class="checkbox-input">
                        <span>Anglais</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Nombre de participants attendus</label>
                <select name="participants_expected" class="form-select" required>
                    @for ($i = 3; $i <= 40; $i++)
                        <option value="{{ $i }}" {{ $i == 10 ? 'selected' : '' }}>{{ $i }} joueurs</option>
                    @endfor
                </select>
            </div>
        </div>
        
        <!-- B. Mode de jeu -->
        <div class="section">
            <div class="section-title">B. Mode de jeu</div>
            <div class="radio-group">
                <label class="radio-label">
                    <input type="radio" name="mode" value="face_to_face" class="radio-input">
                    <span>Face à Face</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="mode" value="one_vs_all" class="radio-input">
                    <span>1 contre Tous</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="mode" value="podium" class="radio-input" checked>
                    <span>Podium</span>
                </label>
                <label class="radio-label">
                    <input type="radio" name="mode" value="groups" class="radio-input">
                    <span>En Groupes</span>
                </label>
            </div>
        </div>
        
        <!-- C. Questions -->
        <div class="section">
            <div class="section-title">C. Questions</div>
            
            <div class="form-group">
                <label class="form-label">Nombre de questions</label>
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="10" class="radio-input">
                        <span>10</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="20" class="radio-input" checked>
                        <span>20</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="30" class="radio-input">
                        <span>30</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="total_questions" value="40" class="radio-input">
                        <span>40</span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Type de questions</label>
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="true_false" class="checkbox-input type-checkbox">
                        <span>Vrai/Faux</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="multiple_choice" class="checkbox-input type-checkbox" checked>
                        <span>Choix multiple</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="image" class="checkbox-input type-checkbox">
                        <span>Image</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="random" class="checkbox-input" id="randomCheck">
                        <span>Random</span>
                    </label>
                </div>
                <p style="font-size: 0.9rem; opacity: 0.8; margin-top: 0.5rem;">Random coche automatiquement tous les types et désactive les autres.</p>
            </div>
        </div>
        
        <!-- D. Domaine -->
        <div class="section">
            <div class="section-title">D. Domaine</div>
            
            <div class="form-group">
                <div class="radio-group">
                    <label class="radio-label">
                        <input type="radio" name="domain_type" value="theme" class="radio-input domain-radio" checked>
                        <span>Thème</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="domain_type" value="scolaire" class="radio-input domain-radio">
                        <span>Scolaire</span>
                    </label>
                    <label class="radio-label">
                        <input type="radio" name="domain_type" value="personnalisé" class="radio-input domain-radio">
                        <span>Personnalisé</span>
                    </label>
                </div>
            </div>
            
            <div id="themeSection" class="form-group">
                <label class="form-label">Choisir un thème</label>
                <select name="theme" class="form-select">
                    <option value="Géographie">Géographie</option>
                    <option value="Histoire">Histoire</option>
                    <option value="Arts et Culture">Arts et Culture</option>
                    <option value="Sciences et Nature">Sciences et Nature</option>
                    <option value="Sports et Loisirs">Sports et Loisirs</option>
                    <option value="Société">Société</option>
                    <option value="Général">Général</option>
                </select>
            </div>
            
            <div id="scolaireSection" class="form-group" style="display: none;">
                <label class="form-label">Pays</label>
                <select name="school_country" class="form-select">
                    <option value="France">France</option>
                    <option value="Canada">Canada</option>
                    <option value="Belgique">Belgique</option>
                    <option value="Suisse">Suisse</option>
                </select>
                
                <label class="form-label" style="margin-top: 1rem;">Niveau</label>
                <select name="school_level" class="form-select">
                    <option value="Primaire">Primaire</option>
                    <option value="Collège">Collège</option>
                    <option value="Lycée">Lycée</option>
                    <option value="Université">Université</option>
                </select>
                
                <label class="form-label" style="margin-top: 1rem;">Matière</label>
                <select name="school_subject" class="form-select">
                    <option value="Mathématiques">Mathématiques</option>
                    <option value="Français">Français</option>
                    <option value="Histoire-Géo">Histoire-Géographie</option>
                    <option value="Sciences">Sciences</option>
                    <option value="Anglais">Anglais</option>
                </select>
            </div>
        </div>
        
        <!-- Boutons -->
        <div class="buttons">
            <button type="submit" class="btn-continue">Continuer</button>
            <a href="{{ route('master.index') }}" class="btn-cancel">Annuler</a>
        </div>
    </form>
</div>

<script>
// Random checkbox logic
const randomCheck = document.getElementById('randomCheck');
const typeCheckboxes = document.querySelectorAll('.type-checkbox');

randomCheck.addEventListener('change', function() {
    if (this.checked) {
        typeCheckboxes.forEach(cb => {
            cb.checked = true;
            cb.disabled = true;
        });
    } else {
        typeCheckboxes.forEach(cb => {
            cb.disabled = false;
        });
    }
});

// Domain type logic
const domainRadios = document.querySelectorAll('.domain-radio');
const themeSection = document.getElementById('themeSection');
const scolaireSection = document.getElementById('scolaireSection');

domainRadios.forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'theme') {
            themeSection.style.display = 'block';
            scolaireSection.style.display = 'none';
        } else if (this.value === 'scolaire') {
            themeSection.style.display = 'none';
            scolaireSection.style.display = 'block';
        } else if (this.value === 'personnalisé') {
            themeSection.style.display = 'none';
            scolaireSection.style.display = 'none';
            alert('Vous serez redirigé vers la création de quiz personnalisé');
        }
    });
});
</script>
@endsection
