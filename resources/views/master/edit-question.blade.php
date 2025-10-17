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
</style>

<a href="{{ route('master.compose', $game->id) }}" class="header-back">← Retour</a>

<div class="edit-container">
    <h1 class="edit-title">Question {{ $questionNumber }}</h1>
    
    <form action="{{ route('master.question.save', [$game->id, $questionNumber]) }}" method="POST" enctype="multipart/form-data" id="questionForm">
        @csrf
        
        <!-- Zone d'upload d'image -->
        <div class="image-upload-zone" onclick="document.getElementById('imageInput').click()">
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
        
        <!-- Texte de la question (optionnel pour type image) -->
        @if(!in_array('image', $game->question_types))
        <div class="answer-section">
            <label style="display: block; margin-bottom: 0.5rem; opacity: 0.9;">Texte de la question</label>
            <input type="text" name="question_text" class="answer-input" 
                   value="{{ $question->question_text ?? '' }}" 
                   placeholder="Entrez votre question...">
        </div>
        @endif
        
        <!-- Réponses -->
        <div class="answer-section">
            <label style="display: block; margin-bottom: 1rem; font-weight: 600;">Réponses (cochez la bonne réponse)</label>
            
            @for ($i = 0; $i < 4; $i++)
                <div class="answer-item">
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
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ Génération en cours...';
    btn.disabled = true;
    
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
        
        btn.innerHTML = originalText;
        btn.disabled = false;
    })
    .catch(error => {
        console.error('Erreur:', error);
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Erreur lors de la génération');
    });
}
</script>
@endsection
