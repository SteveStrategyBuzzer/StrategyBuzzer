@extends('layouts.app')

@section('content')

@php
if (!function_exists('getThemeExamples')) {
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
    
    foreach ($examples as $key => $data) {
        if (stripos($themeLower, $key) !== false) {
            $index = ($questionNumber - 1) % count($data['answers']);
            return [
                'question' => $data['questions'][$index] ?? 'Question exemple',
                'answers' => $data['answers'][$index] ?? ['Réponse 1', 'Réponse 2', 'Réponse 3', 'Réponse 4'],
            ];
        }
    }
    
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
}

if (!function_exists('getQuestionTypeForNumber')) {
function getQuestionTypeForNumber($game, $questionNumber) {
    $types = $game->question_types ?? ['multiple_choice'];
    
    if (empty($types)) {
        return 'multiple_choice';
    }
    
    $index = ($questionNumber - 1) % count($types);
    return $types[$index];
}
}

$currentManche = $manche ?? 1;
$totalQuestions = $game->total_questions;
$questionsPerRound = (int) ceil($totalQuestions / 3);
$normalQuestions = $game->questions->where('is_tiebreaker', false);
$tiebreakerQuestion = $game->questions->where('is_tiebreaker', true)->first();

if ($currentManche <= 3) {
    $startQuestion = ($currentManche - 1) * $questionsPerRound + 1;
    $endQuestion = min($currentManche * $questionsPerRound, $totalQuestions);
    $mancheTitle = "Manche {$currentManche}";
    $isMancheUltime = false;
} else {
    $startQuestion = null;
    $endQuestion = null;
    $mancheTitle = "Manche Ultime";
    $isMancheUltime = true;
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
    padding: 0.5rem;
}

.compose-title {
    font-size: 1.8rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
    text-align: center;
    color: #FFD700;
}

.manche-title {
    font-size: 2.2rem;
    font-weight: 900;
    margin-bottom: 1.5rem;
    text-align: center;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: none;
}

.manche-ultime-title {
    background: linear-gradient(135deg, #FF6B35, #FF4444);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.manche-progress {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.manche-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transition: all 0.3s ease;
}

.manche-dot.active {
    background: #FFD700;
    box-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
}

.manche-dot.ultime {
    background: linear-gradient(135deg, #FF6B35, #FF4444);
    box-shadow: 0 0 10px rgba(255, 68, 68, 0.5);
}

.question-bubble {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    position: relative;
    padding-top: 3.5rem;
}

@media (max-width: 768px) {
    .question-bubble {
        padding-top: 3.5rem;
    }
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
    text-align: left;
}

.btn-create {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.95rem;
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
        padding: 6px 12px;
        font-size: 0.9rem;
        top: 0.5rem;
        right: 0.5rem;
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

.btn-next-manche {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #003DA5;
    padding: 1rem 3rem;
    border-radius: 12px;
    font-size: 1.2rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: block;
    margin: 2rem auto;
    text-decoration: none;
}

.btn-next-manche:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
    color: #003DA5;
}

.btn-manche-ultime {
    background: linear-gradient(135deg, #FF6B35, #FF4444);
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
    text-decoration: none;
}

.btn-manche-ultime:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 68, 68, 0.4);
    color: white;
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

.answer-correct {
    background: rgba(255, 215, 0, 0.3);
    border-left: 4px solid #FFD700;
    padding-left: 0.5rem;
    font-weight: 700;
}

.tiebreaker-section {
    margin-top: 2rem;
    padding: 1.5rem;
    background: rgba(255, 107, 53, 0.1);
    border-radius: 16px;
    border: 2px dashed rgba(255, 107, 53, 0.5);
}

.tiebreaker-title {
    text-align: center;
    color: #FF6B35;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.tiebreaker-bubble {
    border: 2px solid #FF6B35;
}

.tiebreaker-info {
    text-align: center;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.navigation-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}
</style>

@if($currentManche > 1)
    <a href="{{ route('master.compose', ['gameId' => $game->id, 'manche' => $currentManche - 1]) }}" class="header-back">
        {{ $currentManche == 4 ? '← Manche 3' : '← Manche ' . ($currentManche - 1) }}
    </a>
@else
    <a href="{{ route('master.create') }}" class="header-back">{{ __('Retour') }}</a>
@endif

<div class="compose-container">
    <h1 class="compose-title">{{ __('Mode ' . ucfirst($game->creation_mode)) }}</h1>
    
    <div class="manche-progress">
        @for($i = 1; $i <= 3; $i++)
            <div class="manche-dot {{ $currentManche == $i ? 'active' : '' }}"></div>
        @endfor
        <div class="manche-dot {{ $currentManche == 4 ? 'active ultime' : '' }}"></div>
    </div>
    
    <h2 class="manche-title {{ $isMancheUltime ? 'manche-ultime-title' : '' }}">{{ $mancheTitle }}</h2>
    
    @if($game->creation_mode === 'automatique')
        @if(!$isMancheUltime)
            @for ($i = $startQuestion; $i <= $endQuestion; $i++)
                @php
                    $existingQuestion = $normalQuestions->firstWhere('question_number', $i);
                    $questionType = getQuestionTypeForNumber($game, $i);
                    $defaultAnswers = $questionType === 'true_false' 
                        ? ['Vrai', 'Faux'] 
                        : ['Réponse 1', 'Réponse 2', 'Réponse 3', 'Réponse 4'];
                    
                    if ($existingQuestion) {
                        $displayQuestion = $existingQuestion->question_text ?? 'Question';
                        $displayAnswers = $existingQuestion->answers ?? $defaultAnswers;
                        $correctAnswer = $existingQuestion->correct_answer;
                        $displayImage = $existingQuestion->question_image;
                    } else {
                        $example = getThemeExamples($game->theme ?? $game->school_subject, $i, $questionType);
                        $displayQuestion = $example['question'] ?? 'Question';
                        $displayAnswers = $example['answers'] ?? $defaultAnswers;
                        $correctAnswer = null;
                        $displayImage = null;
                    }
                    
                    if (!is_array($displayAnswers)) {
                        $displayAnswers = $defaultAnswers;
                    }
                @endphp
                
                <div class="question-bubble">
                    <div class="bubble-number">{{ $i }}</div>
                    <a href="{{ route('master.question.edit', [$game->id, $i]) }}" class="btn-create" style="text-decoration: none; display: inline-block;">{{ __('Créer') }}</a>
                    
                    <div class="bubble-content">
                        @if($questionType === 'image')
                            <div class="question-image">
                                @if($displayImage)
                                    <img src="{{ asset('storage/' . $displayImage) }}" alt="Question Image">
                                @else
                                    <div class="image-placeholder">🖼️</div>
                                    <div class="image-label">{{ __('Question image') }}</div>
                                @endif
                            </div>
                            @foreach($displayAnswers as $index => $answer)
                                <div class="answer-item {{ $correctAnswer === $index ? 'answer-correct' : '' }}">{{ $index + 1 }}. {{ $answer }}</div>
                            @endforeach
                        @elseif($questionType === 'true_false')
                            <div class="question-text">{{ $displayQuestion }}</div>
                            @foreach($displayAnswers as $index => $answer)
                                <div class="answer-item {{ $correctAnswer === $index ? 'answer-correct' : '' }}">{{ $answer }}</div>
                            @endforeach
                        @else
                            <div class="question-text">{{ $displayQuestion }}</div>
                            @foreach($displayAnswers as $index => $answer)
                                <div class="answer-item {{ $correctAnswer === $index ? 'answer-correct' : '' }}">{{ $index + 1 }}. {{ $answer }}</div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endfor
            
            <div class="navigation-buttons">
                @if($currentManche < 3)
                    <a href="{{ route('master.compose', ['gameId' => $game->id, 'manche' => $currentManche + 1]) }}" class="btn-next-manche">
                        Manche {{ $currentManche + 1 }} →
                    </a>
                @else
                    <a href="{{ route('master.compose', ['gameId' => $game->id, 'manche' => 4]) }}" class="btn-manche-ultime">
                        ⚡ Manche Ultime →
                    </a>
                @endif
            </div>
        @else
            <div class="tiebreaker-section">
                <h3 class="tiebreaker-title">⚡ {{ __('Question de départage') }}</h3>
                
                @if($tiebreakerQuestion)
                    <div class="question-bubble tiebreaker-bubble">
                        <div class="bubble-number" style="color: #FF6B35;">⚡</div>
                        <a href="{{ route('master.question.edit', [$game->id, $tiebreakerQuestion->question_number]) }}" class="btn-create" style="text-decoration: none; display: inline-block; background: linear-gradient(135deg, #FF6B35, #FF4444); color: white;">{{ __('Modifier') }}</a>
                        
                        <div class="bubble-content">
                            <div class="question-text">{{ $tiebreakerQuestion->question_text ?? __('Question de départage') }}</div>
                            @foreach($tiebreakerQuestion->answers as $index => $answer)
                                <div class="answer-item {{ $tiebreakerQuestion->correct_answer === $index ? 'answer-correct' : '' }}">
                                    {{ $index + 1 }}. {{ $answer }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="question-bubble tiebreaker-bubble">
                        <div class="bubble-number" style="color: #FF6B35;">⚡</div>
                        <a href="{{ route('master.question.edit', [$game->id, $game->total_questions + 1]) }}" class="btn-create" style="text-decoration: none; display: inline-block; background: linear-gradient(135deg, #FF6B35, #FF4444); color: white;">{{ __('Créer') }}</a>
                        
                        <div class="bubble-content">
                            <div class="question-text" style="opacity: 0.4;">{{ __('Question de départage') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">1. {{ __('Réponse') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">2. {{ __('Réponse') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">3. {{ __('Réponse') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">4. {{ __('Réponse') }}</div>
                        </div>
                    </div>
                @endif
                
                <p class="tiebreaker-info">
                    {{ __('Utilisée uniquement en cas d\'égalité entre les joueurs') }}
                </p>
            </div>
            
            <div class="navigation-buttons">
                <button class="btn-validate" onclick="window.location.href='{{ route('master.codes', $game->id) }}'">
                    ✓ {{ __('Terminer') }}
                </button>
            </div>
        @endif
        
    @else
        @if(!$isMancheUltime)
            @for ($i = $startQuestion; $i <= $endQuestion; $i++)
                @php
                    $existingQuestion = $game->questions->firstWhere('question_number', $i);
                @endphp
                
                <div class="question-bubble">
                    <div class="bubble-number">{{ $i }}</div>
                    <a href="{{ route('master.question.edit', [$game->id, $i]) }}" class="btn-create" style="text-decoration: none; display: inline-block;">{{ __('Créer') }}</a>
                    
                    <div class="bubble-content">
                        @if($existingQuestion)
                            @if($existingQuestion->question_image)
                                <div class="question-image">
                                    <img src="{{ asset('storage/' . $existingQuestion->question_image) }}" alt="Question Image">
                                </div>
                            @else
                                <div class="question-text">{{ $existingQuestion->question_text }}</div>
                            @endif
                            
                            @foreach($existingQuestion->answers as $index => $answer)
                                <div class="answer-item {{ $existingQuestion->correct_answer === $index ? 'answer-correct' : '' }}">
                                    @if(!$existingQuestion->question_image || count($existingQuestion->answers) > 2)
                                        {{ $index + 1 }}.
                                    @endif
                                    {{ $answer }}
                                </div>
                            @endforeach
                        @else
                            <div class="question-text" style="opacity: 0.4;">{{ __('Question') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">1. {{ __('Réponse') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">2. {{ __('Réponse') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">3. {{ __('Réponse') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">4. {{ __('Réponse') }}</div>
                        @endif
                    </div>
                </div>
            @endfor
            
            <div class="navigation-buttons">
                @if($currentManche < 3)
                    <a href="{{ route('master.compose', ['gameId' => $game->id, 'manche' => $currentManche + 1]) }}" class="btn-next-manche">
                        Manche {{ $currentManche + 1 }} →
                    </a>
                @else
                    <a href="{{ route('master.compose', ['gameId' => $game->id, 'manche' => 4]) }}" class="btn-manche-ultime">
                        ⚡ Manche Ultime →
                    </a>
                @endif
            </div>
        @else
            <div class="tiebreaker-section">
                <h3 class="tiebreaker-title">⚡ {{ __('Question de départage') }}</h3>
                
                @php
                    $tiebreakerQuestionNum = $game->total_questions + 1;
                    $existingTiebreaker = $game->questions->firstWhere('question_number', $tiebreakerQuestionNum);
                @endphp
                
                <div class="question-bubble tiebreaker-bubble">
                    <div class="bubble-number" style="color: #FF6B35;">⚡</div>
                    <a href="{{ route('master.question.edit', [$game->id, $tiebreakerQuestionNum]) }}" class="btn-create" style="text-decoration: none; display: inline-block; background: linear-gradient(135deg, #FF6B35, #FF4444); color: white;">{{ $existingTiebreaker ? __('Modifier') : __('Créer') }}</a>
                    
                    <div class="bubble-content">
                        @if($existingTiebreaker)
                            <div class="question-text">{{ $existingTiebreaker->question_text }}</div>
                            @foreach($existingTiebreaker->answers as $index => $answer)
                                <div class="answer-item {{ $existingTiebreaker->correct_answer === $index ? 'answer-correct' : '' }}">
                                    {{ $index + 1 }}. {{ $answer }}
                                </div>
                            @endforeach
                        @else
                            <div class="question-text" style="opacity: 0.4;">{{ __('Question de départage') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">1. {{ __('Réponse') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">2. {{ __('Réponse') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">3. {{ __('Réponse') }}</div>
                            <div class="answer-item" style="opacity: 0.4;">4. {{ __('Réponse') }}</div>
                        @endif
                    </div>
                </div>
                
                <p class="tiebreaker-info">
                    {{ __('Utilisée uniquement en cas d\'égalité entre les joueurs') }}
                </p>
            </div>
            
            <div class="navigation-buttons">
                <button class="btn-validate" onclick="window.location.href='{{ route('master.codes', $game->id) }}'">
                    ✓ {{ __('Terminer') }}
                </button>
            </div>
        @endif
    @endif
</div>
@endsection
