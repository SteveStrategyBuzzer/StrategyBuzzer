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
}
