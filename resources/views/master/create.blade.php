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
    max-width: 1200px;
    margin: 0 auto;
}

.create-title {
    font-size: 2.5rem;
    font-weight: 900;
    margin-bottom: 2rem;
    text-align: center;
    color: #FFD700;
}

.form-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
}

.section-full {
    grid-column: 1 / -1;
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
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
}

/* Responsive */
@media (max-width: 768px) {
    .form-layout {
        grid-template-columns: 1fr;
    }
    .section-full {
        grid-column: 1;
    }
}

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

<a href="{{ route('menu') }}" class="header-back">← Menu</a>

<div class="create-container">
    <h1 class="create-title">📝 Créer un Quiz</h1>
    
    <form action="{{ route('master.store') }}" method="POST" id="createForm">
        @csrf
        
        <div class="form-layout">
            <!-- Colonne 1 -->
            <div>
                <!-- A. Informations générales -->
                <div class="section">
                    <div class="section-title">A. Informations générales</div>
                    
                    <div class="form-group">
                        <label class="form-label">Nom du quiz</label>
                        <input type="text" name="name" class="form-input" placeholder="Ex: Quiz du samedi" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Langue</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="languages[]" value="FR" class="checkbox-input" checked>
                                <span>🇫🇷 FR</span>
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="languages[]" value="EN" class="checkbox-input">
                                <span>🇬🇧 EN</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Participants (3-40)</label>
                        <select name="participants_expected" class="form-select" required>
                            @for ($i = 3; $i <= 40; $i++)
                                <option value="{{ $i }}" {{ $i == 10 ? 'selected' : '' }}>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
                
                <!-- C. Questions -->
                <div class="section" style="margin-top: 1.5rem;">
                    <div class="section-title">C. Questions</div>
                    
                    <div class="form-group">
                        <label class="form-label">Nombre</label>
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
                        <label class="form-label">Types</label>
                        <div class="checkbox-group">
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
                            <label class="checkbox-label">
                                <input type="checkbox" name="question_types[]" value="random" class="checkbox-input" id="randomCheck">
                                <span>Aléa</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Colonne 2 -->
            <div>
                <!-- B. Mode de jeu -->
                <div class="section">
                    <div class="section-title">B. Mode de jeu</div>
                    <div class="radio-group" style="flex-direction: column; gap: 0.8rem;">
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
                
                <!-- D. Domaine -->
                <div class="section" style="margin-top: 1.5rem;">
                    <div class="section-title">D. Domaine</div>
                    
                    <div class="form-group">
                        <div class="radio-group" style="flex-direction: column; gap: 0.8rem;">
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
                        <label class="form-label">Thème (9 choix)</label>
                        <select name="theme" class="form-select">
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
                    
                    <div id="scolaireSection" class="form-group" style="display: none;">
                        <label class="form-label">1. Pays (programme scolaire)</label>
                        <select name="school_country" class="form-select" id="schoolCountry">
                            <option value="France">France</option>
                            <option value="Canada">Canada</option>
                            <option value="Belgique">Belgique</option>
                            <option value="Suisse">Suisse</option>
                        </select>
                        
                        <label class="form-label" style="margin-top: 0.8rem;">2. Niveau scolaire</label>
                        <select name="school_level" class="form-select" id="schoolLevel">
                            <option value="Primaire">Primaire</option>
                            <option value="Collège">Collège (Secondaire)</option>
                            <option value="Lycée">Lycée</option>
                            <option value="Cégep">Cégep</option>
                            <option value="Université">Université</option>
                        </select>
                        
                        <label class="form-label" style="margin-top: 0.8rem;">3. Matière du niveau</label>
                        <select name="school_subject" class="form-select" id="schoolSubject">
                            <option value="Mathématiques">Mathématiques</option>
                            <option value="Français">Français</option>
                            <option value="Histoire-Géographie">Histoire-Géographie</option>
                            <option value="Sciences">Sciences</option>
                            <option value="Anglais">Anglais</option>
                            <option value="Physique">Physique</option>
                            <option value="Chimie">Chimie</option>
                            <option value="Biologie">Biologie</option>
                        </select>
                    </div>
                    
                    <div id="personaliseNote" class="form-group" style="display: none;">
                        <p style="background: rgba(255,215,0,0.2); padding: 0.8rem; border-radius: 8px; font-size: 0.95rem;">
                            ℹ️ Mode Personnalisé : Vous serez redirigé vers la création de questions après validation.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Boutons (pleine largeur) -->
        <div class="buttons" style="margin-top: 2rem;">
            <button type="submit" class="btn-continue">Continuer</button>
            <a href="{{ route('menu') }}" class="btn-cancel">Annuler</a>
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

const personaliseNote = document.getElementById('personaliseNote');

domainRadios.forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'theme') {
            themeSection.style.display = 'block';
            scolaireSection.style.display = 'none';
            personaliseNote.style.display = 'none';
        } else if (this.value === 'scolaire') {
            themeSection.style.display = 'none';
            scolaireSection.style.display = 'block';
            personaliseNote.style.display = 'none';
        } else if (this.value === 'personnalisé') {
            themeSection.style.display = 'none';
            scolaireSection.style.display = 'none';
            personaliseNote.style.display = 'block';
        }
    });
});
</script>
@endsection
