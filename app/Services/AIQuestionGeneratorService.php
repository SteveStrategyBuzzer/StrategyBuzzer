<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIQuestionGeneratorService
{
    /**
     * Génère une question unique basée sur l'IA
     * 
     * @param string $theme Le thème de la question
     * @param int $niveau Le niveau du joueur (1-100)
     * @param int $questionNumber Le numéro de la question dans la partie
     * @param array $usedQuestionIds Les IDs des questions déjà utilisées
     * @param array $usedAnswers Toutes les réponses déjà utilisées (permanent + session)
     * @return array La question générée
     */
    public function generateQuestion($theme, $niveau, $questionNumber, $usedQuestionIds = [], $usedAnswers = [])
    {
        // Extraire les hash de texte de toutes les questions IA déjà utilisées
        $usedTextHashes = $this->extractUsedTextHashes($usedQuestionIds);
        
        // Augmenter les tentatives pour garantir une question unique de l'IA
        $maxRetries = 10;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                // Appel à l'API OpenAI via Node.js avec tentative d'unicité
                $response = Http::timeout(20)->post('http://localhost:3000/generate-question', [
                    'theme' => $theme,
                    'niveau' => $niveau,
                    'questionNumber' => $questionNumber,
                    'attempt' => $attempt,
                    'usedAnswers' => $usedAnswers, // NOUVEAU : Éviter doublons de réponses
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Créer un hash unique basé sur le texte de la question pour détecter les doublons
                    $questionHash = md5($data['text']);
                    $questionId = $theme . '_ai_' . $questionHash;
                    
                    // Vérifier si cette question a déjà été utilisée (par ID OU par hash de texte)
                    if (in_array($questionId, $usedQuestionIds) || in_array($questionHash, $usedTextHashes)) {
                        $attempt++;
                        Log::info('Duplicate AI question detected (ID or text hash match), retrying', [
                            'attempt' => $attempt, 
                            'hash' => $questionHash,
                            'theme' => $theme,
                            'niveau' => $niveau
                        ]);
                        
                        // Attendre un peu avant de réessayer pour éviter les doublons
                        usleep(100000); // 100ms
                        continue;
                    }
                    
                    return [
                        'id' => $questionId,
                        'text' => $data['text'],
                        'type' => $data['type'],
                        'answers' => $data['answers'],
                        'correct_index' => $data['correct_index'],
                        'difficulty' => $niveau,
                        'theme' => $theme,
                    ];
                }
                
                // Si l'API échoue, réessayer avec un délai
                $attempt++;
                Log::warning('AI API call failed, retrying', ['attempt' => $attempt, 'status' => $response->status()]);
                usleep(200000); // 200ms avant retry
                
            } catch (\Exception $e) {
                $attempt++;
                Log::error('AI Question Generation Error', [
                    'error' => $e->getMessage(), 
                    'attempt' => $attempt,
                    'theme' => $theme,
                    'niveau' => $niveau
                ]);
                usleep(200000); // 200ms avant retry
            }
        }
        
        // Si après toutes les tentatives l'IA n'a pas réussi, lancer une exception
        throw new \Exception("Impossible de générer une question unique par IA après {$maxRetries} tentatives. Vérifiez que le service d'IA est opérationnel.");
    }

    /**
     * Extrait tous les hash MD5 de texte des questions IA déjà utilisées
     */
    private function extractUsedTextHashes($usedQuestionIds)
    {
        $usedTextHashes = [];
        
        // Extraire les hash des questions IA (format: theme_ai_HASH)
        foreach ($usedQuestionIds as $usedId) {
            if (preg_match('/_ai_([a-f0-9]{32})/', $usedId, $matches)) {
                $usedTextHashes[] = $matches[1];
            }
        }
        
        return $usedTextHashes;
    }
}
