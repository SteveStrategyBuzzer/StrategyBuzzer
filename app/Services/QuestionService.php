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
     * @param array $usedQuestionIds Les IDs des questions déjà utilisées (historique permanent + session)
     * @param array $usedAnswers Réponses permanentes déjà vues par le joueur (historique complet)
     * @param array $sessionUsedAnswers Réponses utilisées dans la partie en cours seulement
     * @param array $sessionUsedQuestionTexts Textes des questions déjà posées dans la partie
     * @return array La question générée avec réponses randomisées
     */
    public function generateQuestion($theme, $niveau, $questionNumber, $usedQuestionIds = [], $usedAnswers = [], $sessionUsedAnswers = [], $sessionUsedQuestionTexts = [])
    {
        // Combiner les réponses permanentes et de session pour éviter tous les doublons
        $allUsedAnswers = array_unique(array_merge($usedAnswers, $sessionUsedAnswers));
        
        // Générer la question via l'IA
        $question = $this->aiGenerator->generateQuestion($theme, $niveau, $questionNumber, $usedQuestionIds, $allUsedAnswers, $sessionUsedQuestionTexts);
        
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
     * Détermine si le niveau est un Boss (10, 20, 30, etc.)
     */
    private function isBoss($niveau)
    {
        return $niveau % 10 === 0 && $niveau >= 10 && $niveau <= 100;
    }

    /**
     * Calcule la vitesse de lecture en mots par minute selon le niveau
     * Les étudiants héritent de la vitesse de leur Boss
     */
    private function getReadingSpeed($niveau)
    {
        // Déterminer le Boss de référence (arrondir au multiple de 10 supérieur)
        $bossLevel = ceil($niveau / 10) * 10;
        
        // Vitesses de lecture par Boss
        $speeds = [
            10 => 120,  // Boss niveau 10 et étudiants 1-9
            20 => 130,  // Boss niveau 20 et étudiants 11-19
            30 => 130,  // Boss niveau 30 et étudiants 21-29
            40 => 140,  // Boss niveau 40 et étudiants 31-39
            50 => 140,  // Boss niveau 50 et étudiants 41-49
            60 => 140,  // Boss niveau 60 et étudiants 51-59
            70 => 145,  // Boss niveau 70 et étudiants 61-69
            80 => 145,  // Boss niveau 80 et étudiants 71-79
            90 => 150,  // Boss niveau 90 et étudiants 81-89
            100 => 155, // Boss niveau 100 et étudiants 91-99
        ];
        
        return $speeds[$bossLevel] ?? 120;
    }

    /**
     * Obtient les statistiques de base d'un Boss
     */
    private function getBossStats($niveau)
    {
        if ($niveau >= 10 && $niveau <= 30) {
            return [
                'precision' => 60,      // 60% de bonnes réponses
                'abstention' => 60,     // 60% d'abstention si erreur pressentie
            ];
        } elseif ($niveau >= 40 && $niveau <= 60) {
            return [
                'precision' => 70,
                'abstention' => 70,
            ];
        } elseif ($niveau >= 70 && $niveau <= 90) {
            return [
                'precision' => 80,
                'abstention' => 80,
            ];
        } else { // niveau 100
            return [
                'precision' => 85,
                'abstention' => 90,
            ];
        }
    }

    /**
     * Calcule l'impact de la nervosité basé sur l'écart de score
     * @return array ['participation_modifier' => float, 'panic_mode' => bool, 'panic_speed' => int, 'panic_precision' => int]
     */
    private function calculateNervousness($playerScore, $opponentScore)
    {
        $scoreGap = $opponentScore - $playerScore;
        
        $result = [
            'participation_modifier' => 0,
            'panic_mode' => false,
            'panic_speed' => null,
            'panic_precision' => null,
        ];
        
        // Boss mène
        if ($scoreGap >= 3) {
            $result['participation_modifier'] = 20; // +20% abstention (prudence)
        } elseif ($scoreGap == 2) {
            $result['participation_modifier'] = 10; // +10% abstention
        }
        // Égalité ou Boss mène de 1
        elseif ($scoreGap >= 0 && $scoreGap <= 1) {
            $result['participation_modifier'] = -10; // -10% abstention (agressif)
        }
        // Boss perd
        elseif ($scoreGap <= -1) {
            $result['participation_modifier'] = -20; // -20% abstention (très agressif)
            
            // Mode panique si joueur mène beaucoup
            $playerLead = abs($scoreGap);
            if ($playerLead >= 1 && $playerLead <= 4) {
                $result['panic_mode'] = true;
                $result['panic_speed'] = 6; // Buzz dans les 6 secondes
                $result['panic_precision'] = 55; // 55% de précision
            } elseif ($playerLead >= 5) {
                $result['panic_mode'] = true;
                $result['panic_speed'] = 4; // Buzz dans les 4 secondes
                $result['panic_precision'] = 40; // 40% de précision
            }
        }
        
        return $result;
    }

    /**
     * Calcule la fatigue progressive (tous les 10 questions : -5%)
     */
    private function calculateFatigue($questionNumber)
    {
        $fatigueLevel = floor($questionNumber / 10);
        $fatigueMultiplier = 1 - ($fatigueLevel * 0.05);
        return max(0.7, $fatigueMultiplier); // Minimum 70% de capacité
    }

    /**
     * Simule le comportement complet de l'adversaire IA
     * 
     * @param int $niveau Le niveau du joueur
     * @param array $question La question
     * @param bool $playerBuzzed Si le joueur a buzzé
     * @param float $buzzTime Le temps de buzz du joueur
     * @param int $chronoTime Le temps du chrono
     * @param int $playerScore Le score actuel du joueur (pour nervosité)
     * @param int $opponentScore Le score actuel de l'adversaire (pour nervosité)
     * @param int $questionNumber Le numéro de la question (pour fatigue)
     * @return array Comportement de l'adversaire avec is_faster, is_correct, points, buzzes
     */
    public function simulateOpponentBehavior($niveau, $question, $playerBuzzed, $buzzTime, $chronoTime, $playerScore = 0, $opponentScore = 0, $questionNumber = 1)
    {
        // Déterminer si c'est un Boss et utiliser l'algorithme approprié
        if ($this->isBoss($niveau)) {
            return $this->simulateBossBehavior($niveau, $question, $playerBuzzed, $buzzTime, $chronoTime, $playerScore, $opponentScore, $questionNumber);
        }
        
        // Algorithme standard pour les étudiants (ancien comportement)
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

    /**
     * Simule le comportement psychologique d'un Boss
     * Inclut nervosité, fatigue, abstention stratégique et panique
     */
    private function simulateBossBehavior($niveau, $question, $playerBuzzed, $buzzTime, $chronoTime, $playerScore, $opponentScore, $questionNumber)
    {
        // 1. Obtenir les statistiques de base du Boss
        $bossStats = $this->getBossStats($niveau);
        $basePrecision = $bossStats['precision'];
        $baseAbstention = $bossStats['abstention'];
        
        // 2. Calculer la nervosité (impact psychologique de l'écart de score)
        $nervousness = $this->calculateNervousness($playerScore, $opponentScore);
        
        // 3. Calculer la fatigue progressive
        $fatigueMultiplier = $this->calculateFatigue($questionNumber);
        
        // 4. Appliquer fatigue sur précision et vitesse
        $currentPrecision = $basePrecision * $fatigueMultiplier;
        
        // 5. Mode panique : override la précision si le Boss est très en retard
        if ($nervousness['panic_mode']) {
            $currentPrecision = $nervousness['panic_precision'];
        }
        
        // 6. Décision de buzzer avec abstention stratégique
        // Première passe : le Boss "pressent" s'il va se tromper
        $willBeCorrect = rand(1, 100) <= $currentPrecision;
        
        // Si le Boss pressent une erreur, il peut s'abstenir stratégiquement
        if (!$willBeCorrect) {
            // Appliquer fatigue ET nervosité sur l'abstention
            $abstentionChance = $baseAbstention * $fatigueMultiplier;
            
            // Ajuster l'abstention selon la nervosité :
            // Boss mène → PLUS prudent (augmente abstention)
            // Boss perd → MOINS prudent (diminue abstention, prend plus de risques)
            if ($nervousness['participation_modifier'] > 0) {
                // Boss mène → Augmente l'abstention
                $abstentionChance += $nervousness['participation_modifier'];
            } else {
                // Boss perd → Diminue l'abstention (devient agressif/risqué)
                $abstentionChance += $nervousness['participation_modifier']; // Note: participation_modifier est négatif
            }
            
            // Limiter entre 0 et 100%
            $abstentionChance = max(0, min(100, $abstentionChance));
            
            $shouldAbstain = rand(1, 100) <= $abstentionChance;
            
            if ($shouldAbstain) {
                // Abstention stratégique - ne buzz pas
                return [
                    'buzzes' => false,
                    'is_faster' => false,
                    'is_correct' => false,
                    'points' => 0,
                    'buzz_time' => null,
                ];
            }
        }
        
        // 8. Calculer le temps de buzz basé sur la vitesse de lecture
        $readingSpeed = $this->getReadingSpeed($niveau) * $fatigueMultiplier; // mots/minute
        $wordsPerSecond = $readingSpeed / 60;
        
        // Compter les mots dans la question
        $questionText = $question['text'];
        $wordCount = str_word_count($questionText);
        
        // Temps minimum pour lire la question
        $readingTime = $wordCount / $wordsPerSecond;
        
        // Mode panique : buzz ultra vite (ignore lecture complète)
        if ($nervousness['panic_mode']) {
            $opponentBuzzTime = rand(20, $nervousness['panic_speed'] * 10) / 10;
        } else {
            // Temps normal : lecture + réflexion (0.5-2s)
            $thinkingTime = rand(5, 20) / 10; // 0.5 à 2 secondes
            $opponentBuzzTime = $readingTime + $thinkingTime;
            
            // Ajuster si le joueur a buzzé (compétition)
            if ($playerBuzzed) {
                $adjustment = rand(-10, 10) / 10; // -1s à +1s
                $opponentBuzzTime = max($readingTime, $buzzTime + $adjustment);
            }
        }
        
        // Limiter au temps du chrono
        $opponentBuzzTime = min($opponentBuzzTime, $chronoTime - 0.1);
        
        // 9. Comparaison de vitesse
        $isFaster = $playerBuzzed ? ($opponentBuzzTime < $buzzTime) : true;
        
        // 10. Précision finale (déjà calculée plus haut avec willBeCorrect)
        $isCorrect = $willBeCorrect;
        
        // 11. Attribution des points
        $points = 0;
        
        if ($isCorrect) {
            if ($isFaster || !$playerBuzzed) {
                $points = 2; // Premier
            } else {
                $points = 1; // Deuxième
            }
        } else {
            $points = -2; // Mauvaise réponse
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
