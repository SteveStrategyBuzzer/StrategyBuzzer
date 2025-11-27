<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageGenerationService
{
    /**
     * Génère une question image-mémoire avec DALL-E
     * Exclusif au mode Maître du Jeu
     * 
     * @param int $questionNumber Numéro de la question
     * @param string $language Code langue (fr, en, es, etc.)
     * @return array|null La question générée avec l'image ou null en cas d'erreur
     */
    public function generateImageQuestion($questionNumber = 1, $language = 'fr')
    {
        try {
            Log::info('ImageGenerationService: Démarrage génération image-mémoire', [
                'questionNumber' => $questionNumber,
                'language' => $language
            ]);
            
            // Appel à l'API Node.js pour générer l'image
            $response = Http::timeout(60)->post('http://localhost:3000/generate-image-question', [
                'questionNumber' => $questionNumber,
                'language' => $language
            ]);
            
            if (!$response->successful()) {
                Log::error('ImageGenerationService: Échec appel API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }
            
            $data = $response->json();
            
            if (!$data['success'] || !isset($data['image_url'])) {
                Log::error('ImageGenerationService: Réponse invalide', ['data' => $data]);
                return null;
            }
            
            // Télécharger et sauvegarder l'image localement
            $imageUrl = $data['image_url'];
            $filename = 'memory_' . uniqid() . '.png';
            
            $savedPath = $this->downloadAndSaveImage($imageUrl, $filename);
            
            if (!$savedPath) {
                Log::error('ImageGenerationService: Échec sauvegarde image');
                return null;
            }
            
            Log::info('ImageGenerationService: Image générée avec succès', [
                'path' => $savedPath
            ]);
            
            return [
                'type' => 'image',
                'question_text' => $data['question']['text'],
                'question_image' => $savedPath,
                'answers' => $data['question']['answers'],
                'correct_answer' => $data['question']['correct_index'],
                'scenario' => $data['question']['scenario'] ?? null
            ];
            
        } catch (\Exception $e) {
            Log::error('ImageGenerationService: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Télécharge une image depuis une URL et la sauvegarde localement
     * 
     * @param string $imageUrl URL de l'image à télécharger
     * @param string $filename Nom du fichier de destination
     * @return string|null Le chemin relatif de l'image sauvegardée ou null
     */
    private function downloadAndSaveImage($imageUrl, $filename)
    {
        try {
            // Télécharger l'image
            $imageContent = file_get_contents($imageUrl);
            
            if (!$imageContent) {
                return null;
            }
            
            // Créer le dossier si nécessaire
            $directory = 'master_images';
            
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
            
            // Sauvegarder l'image
            $path = $directory . '/' . $filename;
            Storage::disk('public')->put($path, $imageContent);
            
            return $path;
            
        } catch (\Exception $e) {
            Log::error('ImageGenerationService: Erreur téléchargement', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Génère plusieurs questions images pour un quiz
     * 
     * @param int $count Nombre d'images à générer
     * @param string $language Code langue
     * @return array Liste des questions générées
     */
    public function generateMultipleImageQuestions($count = 3, $language = 'fr')
    {
        $questions = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $question = $this->generateImageQuestion($i, $language);
            if ($question) {
                $questions[] = $question;
            }
            
            // Petit délai entre les générations pour éviter le rate limiting
            if ($i < $count) {
                usleep(500000); // 500ms
            }
        }
        
        return $questions;
    }
}
