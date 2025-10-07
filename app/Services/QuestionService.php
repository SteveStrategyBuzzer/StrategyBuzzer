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

    public function generateQuestion($theme, $niveau, $questionNumber, $usedQuestionIds = [])
    {
        $themeLabel = $this->themes[$theme] ?? 'culture générale';
        
        $questions = $this->getStaticQuestions($theme, $niveau);
        
        // Filtrer les questions déjà utilisées
        $availableQuestions = array_filter($questions, function($question, $index) use ($usedQuestionIds, $theme) {
            $questionId = $theme . '_' . $index;
            return !in_array($questionId, $usedQuestionIds);
        }, ARRAY_FILTER_USE_BOTH);
        
        // Si toutes les questions ont été utilisées, réinitialiser
        if (empty($availableQuestions)) {
            $availableQuestions = $questions;
        }
        
        // Sélectionner aléatoirement une question disponible
        $randomIndex = array_rand($availableQuestions);
        $question = $availableQuestions[$randomIndex];
        $questionId = $theme . '_' . $randomIndex;
        
        return [
            'id' => $questionId,
            'text' => $question['text'],
            'type' => $question['type'] ?? 'multiple',
            'answers' => $question['answers'],
            'correct_index' => $question['correct_index'],
            'difficulty' => $niveau,
            'theme' => $theme,
        ];
    }

    private function getStaticQuestions($theme, $niveau)
    {
        $difficultyMultiplier = ceil($niveau / 10);
        
        $baseQuestions = [
            'general' => [
                ['text' => 'Combien de pays sont dans l\'ONU?', 'type' => 'multiple', 'answers' => ['193', '201', '79', '101'], 'correct_index' => 0],
                ['text' => 'La Tour Eiffel est à Paris', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de l\'Australie?', 'type' => 'multiple', 'answers' => ['Canberra', 'Sydney', 'Melbourne', 'Brisbane'], 'correct_index' => 0],
                ['text' => 'En quelle année a débuté le 21ème siècle?', 'type' => 'multiple', 'answers' => ['2001', '2000', '1999', '2002'], 'correct_index' => 0],
                ['text' => 'Combien de continents y a-t-il sur Terre?', 'type' => 'multiple', 'answers' => ['7', '5', '6', '8'], 'correct_index' => 0],
                ['text' => 'Le Soleil est une étoile', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la langue la plus parlée au monde?', 'type' => 'multiple', 'answers' => ['Mandarin', 'Anglais', 'Espagnol', 'Hindi'], 'correct_index' => 0],
                ['text' => 'Combien de secondes y a-t-il dans une heure?', 'type' => 'multiple', 'answers' => ['3600', '60', '600', '360'], 'correct_index' => 0],
                ['text' => 'La Lune tourne autour de la Terre', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel est le plus petit pays du monde?', 'type' => 'multiple', 'answers' => ['Vatican', 'Monaco', 'San Marin', 'Liechtenstein'], 'correct_index' => 0],
            ],
            'geographie' => [
                ['text' => 'Quel est le plus grand océan du monde?', 'type' => 'multiple', 'answers' => ['Pacifique', 'Atlantique', 'Indien', 'Arctique'], 'correct_index' => 0],
                ['text' => 'Le Mont Everest est le plus haut sommet du monde', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale du Canada?', 'type' => 'multiple', 'answers' => ['Ottawa', 'Toronto', 'Montréal', 'Vancouver'], 'correct_index' => 0],
                ['text' => 'Quel est le plus long fleuve du monde?', 'type' => 'multiple', 'answers' => ['Nil', 'Amazone', 'Yangtsé', 'Mississippi'], 'correct_index' => 0],
                ['text' => 'L\'Islande est en Europe', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel désert est le plus grand au monde?', 'type' => 'multiple', 'answers' => ['Sahara', 'Gobi', 'Kalahari', 'Atacama'], 'correct_index' => 0],
                ['text' => 'Combien de pays partagent une frontière avec la France?', 'type' => 'multiple', 'answers' => ['8', '6', '7', '5'], 'correct_index' => 0],
                ['text' => 'Le Brésil est en Amérique du Sud', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
            ],
            'histoire' => [
                ['text' => 'En quelle année a eu lieu la Révolution française?', 'type' => 'multiple', 'answers' => ['1789', '1776', '1804', '1815'], 'correct_index' => 0],
                ['text' => 'Napoléon Bonaparte était français', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pharaon a construit la Grande Pyramide?', 'type' => 'multiple', 'answers' => ['Khéops', 'Ramsès II', 'Toutânkhamon', 'Cléopâtre'], 'correct_index' => 0],
                ['text' => 'En quelle année s\'est terminée la Seconde Guerre mondiale?', 'type' => 'multiple', 'answers' => ['1945', '1944', '1946', '1943'], 'correct_index' => 0],
                ['text' => 'Jules César était un empereur romain', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel événement a déclenché la Première Guerre mondiale?', 'type' => 'multiple', 'answers' => ['Assassinat de l\'archiduc François-Ferdinand', 'Invasion de la Pologne', 'Traité de Versailles', 'Révolution russe'], 'correct_index' => 0],
            ],
            'sport' => [
                ['text' => 'Combien de joueurs y a-t-il dans une équipe de football?', 'type' => 'multiple', 'answers' => ['11', '10', '9', '12'], 'correct_index' => 0],
                ['text' => 'Le tennis se joue avec une balle', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pays a remporté la Coupe du Monde 2018?', 'type' => 'multiple', 'answers' => ['France', 'Brésil', 'Allemagne', 'Argentine'], 'correct_index' => 0],
                ['text' => 'Combien de sets faut-il gagner au tennis pour gagner un match masculin en Grand Chelem?', 'type' => 'multiple', 'answers' => ['3', '2', '4', '5'], 'correct_index' => 0],
                ['text' => 'Le basketball a été inventé aux États-Unis', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de joueurs y a-t-il dans une équipe de rugby?', 'type' => 'multiple', 'answers' => ['15', '11', '13', '12'], 'correct_index' => 0],
            ],
            'sciences' => [
                ['text' => 'Quel est le symbole chimique de l\'or?', 'type' => 'multiple', 'answers' => ['Au', 'Ag', 'Fe', 'Cu'], 'correct_index' => 0],
                ['text' => 'L\'eau bout à 100°C', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de planètes y a-t-il dans le système solaire?', 'type' => 'multiple', 'answers' => ['8', '9', '7', '10'], 'correct_index' => 0],
                ['text' => 'Quelle est la vitesse de la lumière?', 'type' => 'multiple', 'answers' => ['300 000 km/s', '150 000 km/s', '450 000 km/s', '200 000 km/s'], 'correct_index' => 0],
                ['text' => 'L\'ADN contient l\'information génétique', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel gaz respirons-nous principalement?', 'type' => 'multiple', 'answers' => ['Azote', 'Oxygène', 'CO2', 'Hydrogène'], 'correct_index' => 0],
            ],
            'cuisine' => [
                ['text' => 'D\'où vient la pizza?', 'type' => 'multiple', 'answers' => ['Italie', 'France', 'Grèce', 'Espagne'], 'correct_index' => 0],
                ['text' => 'Le champagne vient de France', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pays est le plus grand producteur de café?', 'type' => 'multiple', 'answers' => ['Brésil', 'Colombie', 'Vietnam', 'Éthiopie'], 'correct_index' => 0],
                ['text' => 'Combien d\'épices y a-t-il traditionnellement dans le mélange "cinq épices chinoises"?', 'type' => 'multiple', 'answers' => ['5', '4', '6', '7'], 'correct_index' => 0],
            ],
            'faune' => [
                ['text' => 'Quel est le plus grand animal terrestre?', 'type' => 'multiple', 'answers' => ['Éléphant d\'Afrique', 'Girafe', 'Rhinocéros', 'Hippopotame'], 'correct_index' => 0],
                ['text' => 'Les dauphins sont des mammifères', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de pattes a une araignée?', 'type' => 'multiple', 'answers' => ['8', '6', '10', '12'], 'correct_index' => 0],
                ['text' => 'Quel est l\'animal le plus rapide au monde?', 'type' => 'multiple', 'answers' => ['Guépard', 'Lion', 'Gazelle', 'Léopard'], 'correct_index' => 0],
                ['text' => 'Les pingouins vivent au Pôle Nord', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
            ],
        ];

        return $baseQuestions[$theme] ?? $baseQuestions['general'];
    }

    public function checkAnswer($questionData, $answerIndex)
    {
        return $answerIndex == $questionData['correct_index'];
    }
    
    /**
     * Simule le comportement complet de l'adversaire IA
     * Retourne: ['buzzes' => bool, 'is_faster' => bool, 'is_correct' => bool, 'points' => int]
     */
    public function simulateOpponentBehavior($niveau, $questionData, $playerBuzzed = true)
    {
        // ÉTAPE 1: L'IA décide si elle buzz (Sans Réponse)
        $buzzChance = $this->getOpponentBuzzChance($niveau);
        $opponentBuzzes = (rand(1, 100) <= $buzzChance);
        
        // Si l'IA ne buzz pas, elle ne gagne ni ne perd de points
        if (!$opponentBuzzes) {
            return [
                'buzzes' => false,
                'is_faster' => false,
                'is_correct' => false,
                'points' => 0
            ];
        }
        
        // ÉTAPE 2: L'IA détermine sa vitesse (si elle est plus rapide que le joueur)
        $speedChance = $this->getOpponentSpeedChance($niveau);
        $isFaster = (rand(1, 100) <= $speedChance);
        
        // ÉTAPE 3: L'IA répond (Taux de Réussite)
        $successRate = $this->getOpponentSuccessRate($niveau);
        $isCorrect = (rand(1, 100) <= $successRate);
        
        // ÉTAPE 4: Calcul des points
        // 1er + correct = +2 pts
        // 2ème + correct = +1 pt
        // Buzz + incorrect = -2 pts
        $points = 0;
        if ($isCorrect) {
            $points = $isFaster ? 2 : 1; // 1er = 2 pts, 2ème = 1 pt
        } else {
            $points = -2; // Incorrect = -2 pts
        }
        
        return [
            'buzzes' => true,
            'is_faster' => $isFaster,
            'is_correct' => $isCorrect,
            'points' => $points
        ];
    }
    
    /**
     * Détermine la probabilité que l'IA buzz (Sans Réponse inversé)
     */
    private function getOpponentBuzzChance($niveau)
    {
        if ($niveau <= 20) {
            return 65 + ($niveau * 0.5); // 65-75% de chance de buzzer
        } elseif ($niveau <= 60) {
            return 75 + (($niveau - 20) * 0.25); // 75-85% de chance
        } elseif ($niveau <= 90) {
            return 85 + (($niveau - 60) * 0.33); // 85-95% de chance
        } else {
            return 95 + (($niveau - 90) * 0.5); // 95-100% de chance
        }
    }
    
    /**
     * Détermine la probabilité que l'IA soit plus rapide que le joueur
     */
    private function getOpponentSpeedChance($niveau)
    {
        if ($niveau <= 20) {
            return 20 + ($niveau * 0.75); // 20-35% de chance d'être plus rapide
        } elseif ($niveau <= 60) {
            return 35 + (($niveau - 20) * 0.625); // 35-60% de chance
        } elseif ($niveau <= 90) {
            return 60 + (($niveau - 60) * 0.833); // 60-85% de chance
        } else {
            return 85 + (($niveau - 90) * 0.5); // 85-90% de chance
        }
    }
    
    /**
     * Détermine le taux de réussite de l'IA
     */
    private function getOpponentSuccessRate($niveau)
    {
        if ($niveau <= 20) {
            return 60 + ($niveau * 0.5); // 60-70% de réussite
        } elseif ($niveau <= 60) {
            return 70 + (($niveau - 20) * 0.375); // 70-85% de réussite
        } elseif ($niveau <= 90) {
            return 85 + (($niveau - 60) * 0.333); // 85-95% de réussite
        } else {
            return 95 + (($niveau - 90) * 0.5); // 95-100% de réussite
        }
    }
    
    /**
     * Ancienne méthode pour compatibilité (deprecated)
     */
    public function simulateOpponentAnswer($niveau, $questionData)
    {
        $behavior = $this->simulateOpponentBehavior($niveau, $questionData);
        return $behavior['is_correct'];
    }
}
