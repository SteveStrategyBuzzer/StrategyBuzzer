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
    max-width: 100%;
    margin: 0 auto;
    padding: 1rem;
}

.compose-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 1.5rem;
    text-align: center;
    color: #FFD700;
}

.question-bubble {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    position: relative;
    padding-right: 120px;
}

.bubble-number {
    font-size: 1.2rem;
    font-weight: 700;
    color: #FFD700;
    margin-bottom: 0.8rem;
}

.bubble-content {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 0.8rem;
    margin-bottom: 0.5rem;
}

.question-text {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.question-image {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 1rem;
}

.question-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-placeholder {
    font-size: 2.5rem;
    flex-shrink: 0;
}

.image-label {
    font-size: 1.1rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
}

@media (max-width: 768px) {
    .question-image {
        padding: 1.2rem;
        gap: 0.8rem;
    }
    
    .image-placeholder {
        font-size: 2rem;
    }
    
    .image-label {
        font-size: 1rem;
    }
}

.answer-item {
    padding: 0.4rem 0;
    opacity: 0.9;
    font-size: 0.95rem;
}

.btn-create {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 0.8rem 2rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 1.1rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.btn-create:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
}

@media (max-width: 768px) {
    .btn-create {
        padding: 0.7rem 1.8rem;
        font-size: 1rem;
    }
}

.btn-validate {
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
    margin: 2rem auto;
}

.btn-validate:hover {
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

<div class="compose-container">
    <h1 class="compose-title">{{ ucfirst($game->creation_mode) }}</h1>
    
    @if($game->creation_mode === 'automatique')
        <!-- Mode Automatique : Questions pré-générées -->
        @for ($i = 1; $i <= $game->total_questions; $i++)
            <div class="question-bubble">
                <div class="bubble-number">{{ $i }}</div>
                <a href="{{ route('master.question.edit', [$game->id, $i]) }}" class="btn-create" style="text-decoration: none; display: inline-block;">Créer</a>
                
                <div class="bubble-content">
                    @if(in_array('image', $game->question_types))
                        <div class="question-image">
                            <div class="image-placeholder">🖼️</div>
                            <div class="image-label">Question image</div>
                        </div>
                        <div class="answer-item">1. Tour Eiffel</div>
                        <div class="answer-item">2. Big Ben</div>
                        <div class="answer-item">3. Statue de la Liberté</div>
                        <div class="answer-item">4. Colisée</div>
                    @elseif(in_array('multiple_choice', $game->question_types))
                        <div class="question-text">Quelle est la capitale de la France ?</div>
                        <div class="answer-item">1. Paris</div>
                        <div class="answer-item">2. Lyon</div>
                        <div class="answer-item">3. Marseille</div>
                        <div class="answer-item">4. Bordeaux</div>
                    @elseif(in_array('true_false', $game->question_types))
                        <div class="question-text">La Terre tourne autour du Soleil</div>
                        <div class="answer-item">Vrai</div>
                        <div class="answer-item">Faux</div>
                    @else
                        <div class="question-text">Question générée automatiquement</div>
                    @endif
                </div>
            </div>
        @endfor
        
        <button class="btn-validate" onclick="window.location.href='{{ route('master.codes', $game->id) }}'">
            Valider
        </button>
        
    @else
        <!-- Mode Personnalisé : Bulles vides -->
        @for ($i = 1; $i <= $game->total_questions; $i++)
            <div class="question-bubble">
                <div class="bubble-number">{{ $i }}</div>
                <a href="{{ route('master.question.edit', [$game->id, $i]) }}" class="btn-create" style="text-decoration: none; display: inline-block;">Créer</a>
                
                <div class="bubble-content">
                    @if(in_array('image', $game->question_types))
                        <div class="question-image" style="opacity: 0.4; background: rgba(255, 255, 255, 0.05);">
                            <div class="image-placeholder">🖼️</div>
                            <div class="image-label">Question image</div>
                        </div>
                        <div class="answer-item" style="opacity: 0.4;">1. Réponse</div>
                        <div class="answer-item" style="opacity: 0.4;">2. Réponse</div>
                        <div class="answer-item" style="opacity: 0.4;">3. Réponse</div>
                        <div class="answer-item" style="opacity: 0.4;">4. Réponse</div>
                    @else
                        <div class="question-text" style="opacity: 0.4;">Question</div>
                        @if(in_array('multiple_choice', $game->question_types))
                            <div class="answer-item" style="opacity: 0.4;">1. Réponse</div>
                            <div class="answer-item" style="opacity: 0.4;">2. Réponse</div>
                            <div class="answer-item" style="opacity: 0.4;">3. Réponse</div>
                            <div class="answer-item" style="opacity: 0.4;">4. Réponse</div>
                        @elseif(in_array('true_false', $game->question_types))
                            <div class="answer-item" style="opacity: 0.4;">Vrai</div>
                            <div class="answer-item" style="opacity: 0.4;">Faux</div>
                        @else
                            <div class="answer-item" style="opacity: 0.4;">Réponse</div>
                        @endif
                    @endif
                </div>
            </div>
        @endfor
        
        <button class="btn-validate" onclick="window.location.href='{{ route('master.codes', $game->id) }}'">
            Valider
        </button>
    @endif
</div>
@endsection
