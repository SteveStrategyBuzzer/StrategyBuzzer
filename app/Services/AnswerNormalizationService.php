<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AnswerNormalizationService
{
    /**
     * Normalise une réponse pour l'anti-duplication
     * Extrait le texte brut de manière déterministe, le nettoie et le met en minuscules
     * 
     * @param mixed $answer La réponse à normaliser
     * @return string La réponse normalisée
     */
    public static function normalize($answer): string
    {
        if (is_null($answer)) {
            return '';
        }
        
        // Cas principal : la réponse est déjà une chaîne (cas normal pour l'IA)
        if (is_string($answer)) {
            return trim(strtolower($answer));
        }
        
        // Cas objet : convertir en tableau d'abord pour éviter crash fatal
        if (is_object($answer)) {
            Log::warning('Answer normalization: object detected (converting to array)', [
                'object_class' => get_class($answer)
            ]);
            // Convertir en tableau via JSON pour extraction sécurisée
            $answer = json_decode(json_encode($answer), true);
            // Continuer le traitement comme un tableau
        }
        
        // Cas exceptionnel : la réponse est un tableau (ne devrait pas arriver avec l'IA actuelle)
        if (is_array($answer)) {
            Log::debug('Answer normalization: array detected (should be string)', [
                'answer_structure' => json_encode($answer)
            ]);
            
            // Chercher récursivement les clés textuelles connues (text avant value pour éviter collisions)
            $textKeys = ['text', 'label', 'content', 'answer', 'name'];
            foreach ($textKeys as $key) {
                if (isset($answer[$key])) {
                    // Récursion pour gérer les structures imbriquées
                    return self::normalize($answer[$key]);
                }
            }
            
            // Si c'est un tableau indexé numériquement avec des chaînes
            if (array_keys($answer) === range(0, count($answer) - 1)) {
                $firstElement = count($answer) > 0 ? $answer[0] : '';
                // Récursion pour gérer si le premier élément est aussi un tableau
                return self::normalize($firstElement);
            }
            
            // Dernier recours : json_encode pour les tableaux associatifs complexes
            return trim(strtolower(json_encode($answer, JSON_UNESCAPED_UNICODE)));
        }
        
        // Autres types (nombre, booléen) : convertir en chaîne
        return trim(strtolower((string)$answer));
    }
}
