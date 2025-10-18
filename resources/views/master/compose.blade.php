@extends('layouts.app')

@section('content')

@php
// Générer des exemples de questions et réponses selon le thème
function getThemeExamples($theme, $questionNumber, $questionType) {
    $themeLower = strtolower($theme ?? 'culture générale');
    
    $examples = [
        'sport' => [
            'questions' => [
                'Qui a remporté le Ballon d\'or en 2023 ?',
                'Combien de joueurs composent une équipe de football ?',
                'Quelle est la durée d\'un match de basketball NBA ?',
                'Dans quel pays se sont déroulés les JO 2024 ?',
            ],
            'answers' => [
                ['Lionel Messi', 'Cristiano Ronaldo', 'Kylian Mbappé', 'Erling Haaland'],
                ['Rafael Nadal', 'Roger Federer', 'Novak Djokovic', 'Andy Murray'],
                ['Tour de France', 'Giro d\'Italia', 'Vuelta', 'Paris-Roubaix'],
                ['NBA', 'NFL', 'MLB', 'NHL'],
            ],
        ],
        'géographie' => [
            'questions' => [
                'Quelle est la capitale de la France ?',
                'Quel est le plus long fleuve du monde ?',
                'Combien de continents existe-t-il ?',
                'Quel est le plus grand océan ?',
            ],
            'answers' => [
                ['Paris', 'Lyon', 'Marseille', 'Bordeaux'],
                ['Nil', 'Amazone', 'Yangtsé', 'Mississippi'],
                ['Everest', 'K2', 'Kilimandjaro', 'Mont Blanc'],
                ['Atlantique', 'Pacifique', 'Indien', 'Arctique'],
            ],
        ],
        'histoire' => [
            'questions' => [
                'En quelle année a eu lieu la Révolution française ?',
                'Qui était le premier empereur romain ?',
                'Quelle guerre a duré de 1914 à 1918 ?',
                'Qui a découvert l\'Amérique en 1492 ?',
            ],
            'answers' => [
                ['1789', '1792', '1804', '1815'],
                ['Napoléon', 'Louis XIV', 'Charlemagne', 'César'],
                ['Versailles', 'Louvre', 'Notre-Dame', 'Arc de Triomphe'],
                ['Christophe Colomb', 'Vasco de Gama', 'Magellan', 'Marco Polo'],
            ],
        ],
        'science' => [
            'questions' => [
                'Quelle est la formule chimique de l\'eau ?',
                'Combien de planètes compte le système solaire ?',
                'Quelle est la vitesse de la lumière ?',
                'Qui a découvert la pénicilline ?',
            ],
            'answers' => [
                ['H2O', 'CO2', 'O2', 'N2'],
                ['Oxygène', 'Hydrogène', 'Azote', 'Carbone'],
                ['Einstein', 'Newton', 'Galilée', 'Darwin'],
                ['Mars', 'Jupiter', 'Vénus', 'Saturne'],
            ],
        ],
    ];
    
    // Trouver le thème approprié
    foreach ($examples as $key => $data) {
        if (stripos($themeLower, $key) !== false) {
            $index = ($questionNumber - 1) % count($data['answers']);
            return [
                'question' => $data['questions'][$index] ?? 'Question exemple',
                'answers' => $data['answers'][$index] ?? ['Réponse 1', 'Réponse 2', 'Réponse 3', 'Réponse 4'],
            ];
        }
    }
    
    // Par défaut
    $defaultAnswers = [
        ['Paris', 'Lyon', 'Marseille', 'Bordeaux'],
        ['Rouge', 'Bleu', 'Vert', 'Jaune'],
        ['Mozart', 'Beethoven', 'Bach', 'Chopin'],
        ['Soleil', 'Lune', 'Étoile', 'Planète'],
    ];
    $index = ($questionNumber - 1) % count($defaultAnswers);
    
    return [
        'question' => 'Question exemple',
        'answers' => $defaultAnswers[$index],
    ];
}
@endphp

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
    width: 100%;
    height: 500px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    overflow: hidden;
}

.question-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-placeholder {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.6;
}

.image-label {
    font-size: 1.3rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.7);
}

@media (max-width: 768px) {
    .question-image {
        height: 450px;
    }
    
    .image-placeholder {
        font-size: 3.5rem;
    }
    
    .image-label {
        font-size: 1.2rem;
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
                    @php
                        $example = getThemeExamples($game->theme ?? $game->school_subject, $i, $game->question_types[0] ?? 'multiple_choice');
                    @endphp
                    
                    @if(in_array('image', $game->question_types))
                        <div class="question-image">
                            <div class="image-placeholder">🖼️</div>
                            <div class="image-label">Question image</div>
                        </div>
                        @foreach($example['answers'] as $index => $answer)
                            <div class="answer-item">{{ $index + 1 }}. {{ $answer }}</div>
                        @endforeach
                    @elseif(in_array('multiple_choice', $game->question_types))
                        <div class="question-text">{{ $example['question'] }}</div>
                        @foreach($example['answers'] as $index => $answer)
                            <div class="answer-item">{{ $index + 1 }}. {{ $answer }}</div>
                        @endforeach
                    @elseif(in_array('true_false', $game->question_types))
                        <div class="question-text">{{ $example['question'] }}</div>
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
