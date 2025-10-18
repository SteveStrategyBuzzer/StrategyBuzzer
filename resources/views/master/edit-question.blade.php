@extends('layouts.app')

@section('content')
<style>
body {
    background-color: #003DA5;
    color: #fff;
    min-height: 100vh;
    padding: 20px;
}

.edit-container {
    max-width: 100%;
    margin: 0 auto;
    padding: 1rem;
}

.edit-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 1.5rem;
    text-align: center;
    color: #FFD700;
}

.image-upload-zone {
    width: 100%;
    height: 350px;
    background: rgba(255, 255, 255, 0.1);
    border: 3px dashed rgba(255, 215, 0, 0.5);
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.image-upload-zone:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 215, 0, 0.8);
}

.image-upload-zone img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.upload-placeholder {
    text-align: center;
    color: rgba(255, 255, 255, 0.6);
}

.upload-placeholder .icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.answer-section {
    margin-bottom: 2rem;
}

.answer-input {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 8px;
    padding: 1rem;
    color: #fff;
    font-size: 1rem;
    width: 100%;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.answer-input:focus {
    outline: none;
    border-color: #FFD700;
    background: rgba(255, 255, 255, 0.15);
}

.answer-item {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.8rem;
}

.answer-radio {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.btn-regenerate {
    background: linear-gradient(135deg, #9D4EDD, #7B2CBF);
    color: white;
    padding: 1rem 2rem;
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    margin-bottom: 1rem;
}

.btn-regenerate:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(157, 78, 221, 0.4);
}

.btn-save {
    background: linear-gradient(135deg, #00D400, #00A000);
    color: white;
    padding: 1rem 2rem;
    border-radius: 10px;
    font-size: 1.1rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
}

.btn-save:hover {
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
    
    .image-upload-zone {
        height: 300px;
    }
}

.file-input {
    display: none;
}

.mode-toggle {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    justify-content: center;
}

.mode-btn {
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.7);
    padding: 0.8rem 2rem;
    border-radius: 10px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mode-btn.active {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    border-color: #FFD700;
}

.mode-btn:hover:not(.active) {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.4);
}

@media (max-width: 768px) {
    .mode-btn {
        padding: 0.6rem 1.5rem;
        font-size: 0.9rem;
    }
}
</style>

<a href="{{ route('master.compose', $game->id) }}" class="header-back">← Retour</a>

<div class="edit-container">
    <h1 class="edit-title">Question {{ $questionNumber }}</h1>
    
    @if($game->creation_mode === 'personnalise')
    <!-- Toggle Mode Texte/Image (uniquement en mode personnalisé) -->
    <div class="mode-toggle">
        <button type="button" class="mode-btn active" id="modeTextBtn" onclick="switchMode('text')">
            📝 Texte
        </button>
        <button type="button" class="mode-btn" id="modeImageBtn" onclick="switchMode('image')">
            🖼️ Image
        </button>
    </div>
    @endif
    
    <form action="{{ route('master.question.save', [$game->id, $questionNumber]) }}" method="POST" enctype="multipart/form-data" id="questionForm">
        @csrf
        
        <!-- Champ caché pour stocker le mode actuel -->
        <input type="hidden" name="question_mode" id="questionMode" value="text">
        
        <!-- Zone d'upload d'image -->
        <div id="imageSection" class="image-upload-zone" onclick="document.getElementById('imageInput').click()" style="display: none;">
            @if($question && $question->question_image)
                <img src="{{ asset('storage/' . $question->question_image) }}" alt="Question image" id="previewImage">
            @else
                <div class="upload-placeholder" id="uploadPlaceholder">
                    <div class="icon">📷</div>
                    <div>Tapez pour ajouter une image</div>
                    <div style="font-size: 0.85rem; opacity: 0.7; margin-top: 0.5rem;">Mobile ou ordinateur</div>
                </div>
            @endif
        </div>
        <input type="file" id="imageInput" name="question_image" class="file-input" accept="image/*" onchange="previewImageFile(this)">
        
        <!-- Texte de la question -->
        <div id="textSection" class="answer-section">
            <label style="display: block; margin-bottom: 0.5rem; opacity: 0.9;">Texte de la question</label>
            <input type="text" name="question_text" class="answer-input" 
                   value="{{ $question->question_text ?? '' }}" 
                   placeholder="Entrez votre question...">
            
            @if($game->creation_mode === 'personnalise')
            <!-- Sous-toggle pour Vrai/Faux ou Choix Multiples -->
            <div style="display: flex; gap: 0.5rem; margin-top: 0.8rem; font-size: 0.9rem;">
                <button type="button" class="mode-btn" id="trueFalseBtn" onclick="setTextQuestionType('true_false')" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                    Vrai/Faux
                </button>
                <button type="button" class="mode-btn active" id="multipleChoiceBtn" onclick="setTextQuestionType('multiple_choice')" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                    Choix Multiples
                </button>
            </div>
            @endif
        </div>
        
        <!-- Réponses -->
        <div class="answer-section">
            <label style="display: block; margin-bottom: 1rem; font-weight: 600;">Réponses (cochez la bonne réponse)</label>
            
            @for ($i = 0; $i < 4; $i++)
                <div class="answer-item" id="answer-{{ $i }}">
                    <input type="radio" name="correct_answer" value="{{ $i }}" class="answer-radio" 
                           {{ ($question && $question->correct_answer == $i) ? 'checked' : ($i == 0 ? 'checked' : '') }} required>
                    <input type="text" name="answers[]" class="answer-input" 
                           value="{{ $question->answers[$i] ?? '' }}" 
                           placeholder="Réponse {{ $i + 1 }}" required>
                </div>
            @endfor
        </div>
        
        <!-- Boutons -->
        <button type="button" class="btn-regenerate" onclick="regenerateQuestion()">
            ✨ Régénérer avec IA
        </button>
        
        <button type="submit" class="btn-save">
            💾 Sauvegarder
        </button>
    </form>
</div>

<script>
// Basculer entre mode Texte et mode Image (uniquement en mode personnalisé)
function switchMode(mode) {
    const textSection = document.getElementById('textSection');
    const imageSection = document.getElementById('imageSection');
    const modeTextBtn = document.getElementById('modeTextBtn');
    const modeImageBtn = document.getElementById('modeImageBtn');
    const questionModeInput = document.getElementById('questionMode');
    
    if (mode === 'text') {
        // Mode Texte
        textSection.style.display = 'block';
        imageSection.style.display = 'none';
        modeTextBtn.classList.add('active');
        modeImageBtn.classList.remove('active');
        questionModeInput.value = 'text';
    } else {
        // Mode Image
        textSection.style.display = 'none';
        imageSection.style.display = 'flex';
        modeTextBtn.classList.remove('active');
        modeImageBtn.classList.add('active');
        questionModeInput.value = 'image';
    }
}

// Basculer entre Vrai/Faux et Choix Multiples pour les questions texte
function setTextQuestionType(type, prefillDefaults = true) {
    const answer2 = document.getElementById('answer-2');
    const answer3 = document.getElementById('answer-3');
    const trueFalseBtn = document.getElementById('trueFalseBtn');
    const multipleChoiceBtn = document.getElementById('multipleChoiceBtn');
    
    const answerInputs = document.querySelectorAll('input[name="answers[]"]');
    
    if (type === 'true_false') {
        // Mode Vrai/Faux : afficher seulement 2 réponses
        answer2.style.display = 'none';
        answer3.style.display = 'none';
        
        // Préremplir avec Vrai et Faux SEULEMENT pour les nouvelles questions
        if (prefillDefaults && answerInputs[0] && !answerInputs[0].value) {
            answerInputs[0].value = 'Vrai';
        }
        if (prefillDefaults && answerInputs[1] && !answerInputs[1].value) {
            answerInputs[1].value = 'Faux';
        }
        
        // Désactiver le required pour les 2 derniers champs
        if (answerInputs[2]) answerInputs[2].removeAttribute('required');
        if (answerInputs[3]) answerInputs[3].removeAttribute('required');
        
        // Mettre à jour les boutons
        if (trueFalseBtn) trueFalseBtn.classList.add('active');
        if (multipleChoiceBtn) multipleChoiceBtn.classList.remove('active');
    } else {
        // Mode Choix Multiples : afficher 4 réponses
        answer2.style.display = 'flex';
        answer3.style.display = 'flex';
        
        // Réactiver le required
        if (answerInputs[2]) answerInputs[2].setAttribute('required', 'required');
        if (answerInputs[3]) answerInputs[3].setAttribute('required', 'required');
        
        // Mettre à jour les boutons
        if (trueFalseBtn) trueFalseBtn.classList.remove('active');
        if (multipleChoiceBtn) multipleChoiceBtn.classList.add('active');
    }
}

// Prévisualisation de l'image
function previewImageFile(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const uploadZone = document.querySelector('.image-upload-zone');
            uploadZone.innerHTML = '<img src="' + e.target.result + '" alt="Preview" id="previewImage">';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Régénérer la question avec IA
function regenerateQuestion() {
    const gameId = {{ $game->id }};
    const questionNumber = {{ $questionNumber }};
    
    // Afficher un loader
    const btn = event ? event.target : null;
    let originalText = '';
    if (btn) {
        originalText = btn.innerHTML;
        btn.innerHTML = '⏳ Génération en cours...';
        btn.disabled = true;
    } else {
        // Afficher un indicateur de chargement global
        document.body.style.cursor = 'wait';
    }
    
    fetch(`/master/${gameId}/question/${questionNumber}/regenerate`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Remplir les champs avec les données générées
        const answerInputs = document.querySelectorAll('input[name="answers[]"]');
        data.answers.forEach((answer, index) => {
            if (answerInputs[index]) {
                answerInputs[index].value = answer;
            }
        });
        
        // Sélectionner la bonne réponse
        const correctRadio = document.querySelector(`input[name="correct_answer"][value="${data.correct_answer}"]`);
        if (correctRadio) {
            correctRadio.checked = true;
        }
        
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        } else {
            document.body.style.cursor = 'default';
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        } else {
            document.body.style.cursor = 'default';
        }
        alert('Erreur lors de la génération de la question');
    });
}

// Initialisation au chargement de la page
window.addEventListener('DOMContentLoaded', function() {
    @if($question)
        // Question existante : initialiser selon les données
        const hasImage = {{ $question->question_image ? 'true' : 'false' }};
        const answersCount = {{ count($question->answers ?? []) }};
        
        if (hasImage) {
            // Question image existante
            document.getElementById('textSection').style.display = 'none';
            document.getElementById('imageSection').style.display = 'flex';
            document.getElementById('questionMode').value = 'image';
            @if($game->creation_mode === 'personnalise')
            if (document.getElementById('modeImageBtn')) {
                document.getElementById('modeTextBtn').classList.remove('active');
                document.getElementById('modeImageBtn').classList.add('active');
            }
            @endif
        } else {
            // Question texte existante
            document.getElementById('textSection').style.display = 'block';
            document.getElementById('imageSection').style.display = 'none';
            document.getElementById('questionMode').value = 'text';
            
            // Détecter Vrai/Faux vs Choix Multiples (pour TOUS les modes)
            if (answersCount === 2) {
                // C'est une question Vrai/Faux existante - ne pas préremplir
                setTextQuestionType('true_false', false);
            } else {
                // C'est un choix multiple existant
                setTextQuestionType('multiple_choice', false);
            }
            
            // Mettre à jour les boutons toggle uniquement en mode personnalisé
            @if($game->creation_mode === 'personnalise')
            if (document.getElementById('modeTextBtn')) {
                document.getElementById('modeTextBtn').classList.add('active');
                document.getElementById('modeImageBtn').classList.remove('active');
            }
            @endif
        }
    @else
        // Nouvelle question
        @if($game->creation_mode === 'automatique')
            // Mode automatique : déterminer le type spécifique pour cette question
            const questionNumber = {{ $questionNumber }};
            const questionTypes = @json($game->question_types);
            
            // Calculer le type pour ce numéro de question
            let questionType;
            if (questionTypes.length === 1) {
                questionType = questionTypes[0];
            } else {
                // Distribution équilibrée des types
                const totalQuestions = {{ $game->total_questions }};
                const typeIndex = (questionNumber - 1) % questionTypes.length;
                questionType = questionTypes[typeIndex];
            }
            
            if (questionType === 'image') {
                // Afficher mode image
                document.getElementById('textSection').style.display = 'none';
                document.getElementById('imageSection').style.display = 'flex';
                document.getElementById('questionMode').value = 'image';
            } else if (questionType === 'true_false') {
                // Afficher mode texte avec 2 réponses
                document.getElementById('textSection').style.display = 'block';
                document.getElementById('imageSection').style.display = 'none';
                document.getElementById('questionMode').value = 'text';
                // Cacher les réponses 3 et 4 et enlever required
                const answer2 = document.getElementById('answer-2');
                const answer3 = document.getElementById('answer-3');
                answer2.style.display = 'none';
                answer3.style.display = 'none';
                const answerInputs = document.querySelectorAll('input[name="answers[]"]');
                if (answerInputs[2]) answerInputs[2].removeAttribute('required');
                if (answerInputs[3]) answerInputs[3].removeAttribute('required');
            } else {
                // Afficher mode texte avec 4 réponses (choix multiples)
                document.getElementById('textSection').style.display = 'block';
                document.getElementById('imageSection').style.display = 'none';
                document.getElementById('questionMode').value = 'text';
            }
            
            // Générer automatiquement
            setTimeout(function() {
                regenerateQuestion();
            }, 500);
        @else
            // Mode personnalisé : démarrer en mode texte choix multiples par défaut
            document.getElementById('textSection').style.display = 'block';
            document.getElementById('imageSection').style.display = 'none';
            document.getElementById('questionMode').value = 'text';
            setTextQuestionType('multiple_choice');
        @endif
    @endif
});
</script>
@endsection
