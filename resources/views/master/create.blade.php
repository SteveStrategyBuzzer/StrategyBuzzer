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

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: linear-gradient(135deg, #003DA5, #0055CC);
    border: 3px solid #FFD700;
    border-radius: 15px;
    padding: 2rem;
    max-width: 500px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 900;
    color: #FFD700;
    margin-bottom: 1rem;
}

.modal-text {
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.modal-btn {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 0.8rem 2rem;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
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
                <label class="form-label" style="text-align: center;">Langue</label>
                <select name="language" class="form-select" required style="text-align: center; font-weight: 600;">
                    <option value="FR">Français</option>
                    <option value="EN">English</option>
                    <option value="ES">Español</option>
                    <option value="DE">Deutsch</option>
                </select>
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
                <label class="form-label" style="text-align: center;">{{ __('Types') }}</label>
                <div class="checkbox-group" style="justify-content: center;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="true_false" class="checkbox-input type-checkbox">
                        <span>{{ __('Vrai/Faux') }}</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="multiple_choice" class="checkbox-input type-checkbox" checked>
                        <span>{{ __('QCM') }}</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="question_types[]" value="image" class="checkbox-input type-checkbox" id="imageCheckbox">
                        <span>{{ __('Image') }}</span>
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
                <select name="school_country" id="schoolCountry" class="form-select" style="text-align: center; font-weight: 600;">
                    <option value="">-- Pays --</option>
                    <option value="Canada">Canada</option>
                    <option value="France">France</option>
                    <option value="USA">États-Unis</option>
                </select>
                
                <select name="school_level" id="schoolLevel" class="form-select" style="margin-top: 0.8rem; text-align: center;">
                    <option value="">-- Niveau --</option>
                </select>
                
                <select name="school_grade" id="schoolGrade" class="form-select" style="margin-top: 0.8rem; text-align: center;">
                    <option value="">-- Année --</option>
                </select>
                
                <select name="school_subject" id="schoolSubject" class="form-select" style="margin-top: 0.8rem; text-align: center;">
                    <option value="">-- Matière --</option>
                </select>
            </div>
        </div>
        </div>
        
        <!-- Boutons de création (pleine largeur) -->
        <div class="section-full" style="margin-top: 2rem;">
            <div class="buttons" style="gap: 1rem;">
                <button type="submit" name="creation_mode" value="automatique" id="automatiqueBtn" class="btn-continue" style="flex: 1; background: linear-gradient(135deg, #FFD700, #FFA500); font-size: 1.1rem;">
                    Automatique
                </button>
                <button type="submit" name="creation_mode" value="personnalise" class="btn-continue" style="flex: 1; background: linear-gradient(135deg, #00D4FF, #0099CC); font-size: 1.1rem;">
                    Personalisé
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Modal d'avertissement -->
<div id="imageWarningModal" class="modal">
    <div class="modal-content">
        <div class="modal-title">⚠️ Attention</div>
        <div class="modal-text">
            En mode Automatique, vous devez uploader les images et remplir les questions manuellement pour chaque question de type Image.
        </div>
        <button class="modal-btn" onclick="closeImageModal()">J'ai compris</button>
    </div>
</div>

<script>
// Systèmes éducatifs par pays
const educationSystems = {
    'Canada': {
        levels: {
            'Primaire': { grades: ['1', '2', '3', '4', '5', '6'] },
            'Secondaire': { grades: ['1', '2', '3', '4', '5'] },
            'Cégep': { grades: ['1', '2'] },
            'Universitaire': { grades: ['1', '2', '3', '4'] }
        },
        subjects: ['Mathématiques', 'Français', 'Anglais', 'Sciences', 'Histoire', 'Géographie', 'Éducation physique', 'Arts']
    },
    'France': {
        levels: {
            'Primaire': { grades: ['CP', 'CE1', 'CE2', 'CM1', 'CM2'] },
            'Collège': { grades: ['6ème', '5ème', '4ème', '3ème'] },
            'Lycée': { grades: ['2nde', '1ère', 'Terminale'] },
            'Université': { grades: ['L1', 'L2', 'L3', 'M1', 'M2'] }
        },
        subjects: ['Mathématiques', 'Français', 'Anglais', 'Histoire-Géographie', 'Sciences', 'Physique-Chimie', 'SVT', 'Philosophie', 'Arts']
    },
    'USA': {
        levels: {
            'Elementary': { grades: ['K', '1', '2', '3', '4', '5'] },
            'Middle School': { grades: ['6', '7', '8'] },
            'High School': { grades: ['9', '10', '11', '12'] },
            'College': { grades: ['Freshman', 'Sophomore', 'Junior', 'Senior'] }
        },
        subjects: ['Mathematics', 'English', 'Science', 'History', 'Geography', 'Physical Education', 'Arts', 'Foreign Language']
    }
};

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

// Educational system cascading selects
const schoolCountry = document.getElementById('schoolCountry');
const schoolLevel = document.getElementById('schoolLevel');
const schoolGrade = document.getElementById('schoolGrade');
const schoolSubject = document.getElementById('schoolSubject');

if (schoolCountry) {
    schoolCountry.addEventListener('change', function() {
        const country = this.value;
        
        // Reset dependent selects
        schoolLevel.innerHTML = '<option value="">-- Niveau --</option>';
        schoolGrade.innerHTML = '<option value="">-- Année --</option>';
        schoolSubject.innerHTML = '<option value="">-- Matière --</option>';
        
        if (country && educationSystems[country]) {
            // Populate levels
            Object.keys(educationSystems[country].levels).forEach(level => {
                const option = document.createElement('option');
                option.value = level;
                option.textContent = level;
                schoolLevel.appendChild(option);
            });
        }
    });
}

if (schoolLevel) {
    schoolLevel.addEventListener('change', function() {
        const country = schoolCountry.value;
        const level = this.value;
        
        // Reset dependent selects
        schoolGrade.innerHTML = '<option value="">-- Année --</option>';
        schoolSubject.innerHTML = '<option value="">-- Matière --</option>';
        
        if (country && level && educationSystems[country] && educationSystems[country].levels[level]) {
            // Populate grades
            educationSystems[country].levels[level].grades.forEach(grade => {
                const option = document.createElement('option');
                option.value = grade;
                option.textContent = grade;
                schoolGrade.appendChild(option);
            });
        }
    });
}

if (schoolGrade) {
    schoolGrade.addEventListener('change', function() {
        const country = schoolCountry.value;
        
        // Reset subjects
        schoolSubject.innerHTML = '<option value="">-- Matière --</option>';
        
        if (country && educationSystems[country]) {
            // Populate subjects
            educationSystems[country].subjects.forEach(subject => {
                const option = document.createElement('option');
                option.value = subject;
                option.textContent = subject;
                schoolSubject.appendChild(option);
            });
        }
    });
}

// Image checkbox warning (only for Automatique mode)
let imageWarningShown = false;
const imageCheckbox = document.getElementById('imageCheckbox');
const imageWarningModal = document.getElementById('imageWarningModal');
const automatiqueBtn = document.getElementById('automatiqueBtn');

if (imageCheckbox && imageWarningModal) {
    imageCheckbox.addEventListener('change', function() {
        if (this.checked && !imageWarningShown) {
            imageWarningModal.style.display = 'flex';
            imageWarningShown = true;
        }
    });
}

function closeImageModal() {
    imageWarningModal.style.display = 'none';
}

// Close modal on outside click
window.onclick = function(event) {
    if (event.target == imageWarningModal) {
        closeImageModal();
    }
}
</script>
@endsection
