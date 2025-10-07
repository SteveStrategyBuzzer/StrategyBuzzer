<?php

namespace App\Services;

class QuestionService
{
    private $themes = [
        'general' => 'culture générale',
        'geographie' => 'géographie',
        'histoire' => 'histoire',
        'sport' => 'sport',
        'cuisine' => 'cuisine et gastronomie',
        'faune' => 'animaux et nature',
        'sciences' => 'sciences',
    ];

    public function generateQuestion($theme, $niveau, $questionNumber)
    {
        $themeLabel = $this->themes[$theme] ?? 'culture générale';
        
        // Pour le moment, questions statiques
        // TODO: Intégrer une vraie IA plus tard
        $questions = $this->getStaticQuestions($theme, $niveau);
        
        // Sélectionner une question basée sur le numéro
        $index = ($questionNumber - 1) % count($questions);
        $question = $questions[$index];
        
        return [
            'id' => $questionNumber,
            'text' => $question['text'],
            'type' => $question['type'] ?? 'multiple', // 'multiple' ou 'true_false'
            'answers' => $question['answers'],
            'correct_index' => $question['correct_index'],
            'difficulty' => $niveau,
            'theme' => $theme,
        ];
    }

    private function getStaticQuestions($theme, $niveau)
    {
        $baseQuestions = [
            'general' => [
                [
                    'text' => 'Combien de pays sont dans l\'ONU?',
                    'type' => 'multiple',
                    'answers' => ['193', '201', '79', '101'],
                    'correct_index' => 0,
                ],
                [
                    'text' => 'La Tour Eiffel est à Paris',
                    'type' => 'true_false',
                    'answers' => ['Vrai', null, 'Faux', null],
                    'correct_index' => 0,
                ],
                [
                    'text' => 'Quelle est la capitale de l\'Australie?',
                    'type' => 'multiple',
                    'answers' => ['Canberra', 'Sydney', 'Melbourne', 'Brisbane'],
                    'correct_index' => 0,
                ],
            ],
            'geographie' => [
                [
                    'text' => 'Quel est le plus grand océan du monde?',
                    'type' => 'multiple',
                    'answers' => ['Pacifique', 'Atlantique', 'Indien', 'Arctique'],
                    'correct_index' => 0,
                ],
                [
                    'text' => 'Le Mont Everest est le plus haut sommet du monde',
                    'type' => 'true_false',
                    'answers' => ['Vrai', null, 'Faux', null],
                    'correct_index' => 0,
                ],
            ],
            'histoire' => [
                [
                    'text' => 'En quelle année a eu lieu la Révolution française?',
                    'type' => 'multiple',
                    'answers' => ['1789', '1776', '1804', '1815'],
                    'correct_index' => 0,
                ],
            ],
            'sport' => [
                [
                    'text' => 'Combien de joueurs y a-t-il dans une équipe de football?',
                    'type' => 'multiple',
                    'answers' => ['11', '10', '9', '12'],
                    'correct_index' => 0,
                ],
            ],
            'sciences' => [
                [
                    'text' => 'Quel est le symbole chimique de l\'or?',
                    'type' => 'multiple',
                    'answers' => ['Au', 'Ag', 'Fe', 'Cu'],
                    'correct_index' => 0,
                ],
            ],
        ];

        return $baseQuestions[$theme] ?? $baseQuestions['general'];
    }

    public function checkAnswer($questionData, $answerIndex)
    {
        return $answerIndex == $questionData['correct_index'];
    }
}
