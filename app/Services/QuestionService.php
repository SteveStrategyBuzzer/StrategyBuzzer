<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class QuestionService
{
    private $aiGenerator;

    public function __construct()
    {
        $this->aiGenerator = new AIQuestionGeneratorService();
    }

    /**
     * Génère une question unique via l'IA
     * 
     * @param string $theme Le thème de la question
     * @param int $niveau Le niveau du joueur (1-100)
     * @param int $questionNumber Le numéro de la question dans la partie
     * @param array $usedQuestionIds Les IDs des questions déjà utilisées
     * @return array La question générée avec réponses randomisées
     */
    public function generateQuestion($theme, $niveau, $questionNumber, $usedQuestionIds = [])
    {
        // Générer la question via l'IA
        $question = $this->aiGenerator->generateQuestion($theme, $niveau, $questionNumber, $usedQuestionIds);
        
        // Randomiser les réponses pour questions à choix multiples
        // Les questions vrai/faux gardent leurs positions fixes (Vrai toujours à gauche, Faux à droite)
        if ($question['type'] === 'multiple') {
            $correctAnswer = $question['answers'][$question['correct_index']];
            
            // Mélanger les réponses de manière aléatoire
            shuffle($question['answers']);
            
            // Trouver le nouvel index de la bonne réponse après mélange
            $question['correct_index'] = array_search($correctAnswer, $question['answers'], true);
        }
        
        return $question;
    }

    /**
     * Vérifie si la réponse du joueur est correcte
     * 
     * @param array $question La question
     * @param int $answerIndex L'index de la réponse du joueur
     * @return bool True si la réponse est correcte
     */
    public function checkAnswer($question, $answerIndex)
    {
        return $question['correct_index'] === $answerIndex;
    }

    /**
     * Simule le comportement complet de l'adversaire IA
     * 
     * @param int $niveau Le niveau du joueur
     * @param array $question La question
     * @param bool $playerBuzzed Si le joueur a buzzé
     * @param float $buzzTime Le temps de buzz du joueur
     * @param int $chronoTime Le temps du chrono
     * @return array Comportement de l'adversaire avec is_faster, is_correct, points, buzzes
     */
    public function simulateOpponentBehavior($niveau, $question, $playerBuzzed, $buzzTime, $chronoTime)
    {
        // Difficulté progressive : plus le niveau est élevé, plus l'adversaire est fort
        $difficulty = min($niveau / 100, 0.95); // Max 95% de difficulté
        
        // 1. L'adversaire décide de buzzer ou non (probabilité augmente avec le niveau)
        $opponentBuzzes = rand(1, 100) <= (30 + $difficulty * 60); // 30% à 90% de chance de buzzer
        
        if (!$opponentBuzzes) {
            // L'adversaire ne buzz pas = 0 points
            return [
                'buzzes' => false,
                'is_faster' => false,
                'is_correct' => false,
                'points' => 0,
                'buzz_time' => null,
            ];
        }
        
        // 2. Si l'adversaire buzz, calculer son temps de réaction
        // Base de temps : 1 à 7 secondes, plus rapide avec le niveau
        $opponentBuzzTime = rand(10, 70 - ($difficulty * 50)) / 10;
        
        // Si le joueur a buzzé, ajuster le temps de l'adversaire pour créer de la compétition
        if ($playerBuzzed) {
            // L'adversaire peut être légèrement plus rapide ou plus lent que le joueur
            $adjustment = rand(-15, 15) / 10; // -1.5s à +1.5s
            $opponentBuzzTime = max(0.1, $buzzTime + $adjustment - ($difficulty * 0.5));
        }
        
        // 3. L'adversaire est-il plus rapide que le joueur ?
        $isFaster = $playerBuzzed ? ($opponentBuzzTime < $buzzTime) : true;
        
        // 4. L'adversaire répond-il correctement ? (probabilité augmente avec le niveau)
        $answerProbability = 40 + ($difficulty * 50); // 40% à 90% de bonnes réponses
        $isCorrect = rand(1, 100) <= $answerProbability;
        
        // 5. Calculer les points de l'adversaire
        $points = 0;
        
        if ($isCorrect) {
            // Si l'adversaire répond correctement :
            // - Il est 1er (+2 pts) s'il est plus rapide que le joueur OU si le joueur n'a pas buzzé
            // - Il est 2ème (+1 pt) s'il est plus lent que le joueur buzzé
            if ($isFaster || !$playerBuzzed) {
                $points = 2;
            } else {
                $points = 1;
            }
        } else {
            // Mauvaise réponse = -2 points
            $points = -2;
        }
        
        return [
            'buzzes' => true,
            'is_faster' => $isFaster,
            'is_correct' => $isCorrect,
            'points' => $points,
            'buzz_time' => $opponentBuzzTime,
        ];
    }
}
