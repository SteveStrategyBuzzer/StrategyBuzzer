<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageGenerationService
{
    /**
     * Génère une question image-mémoire via Imagen (Gemini API)
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
            
            // #88: image-question composition endpoint is admin-locked, send shared secret.
            $response = Http::timeout(60)
                ->withHeaders(['X-Admin-Token' => (string) env('MASTER_API_ADMIN_TOKEN', '')])
                ->post(env('QUESTION_API_URL', 'http://localhost:3000') . '/generate-image-question', [
                    'questionNumber' => $questionNumber,
                    'language' => $language,
                ]);
            
            if (!$response->successful()) {
                Log::error('ImageGenerationService: Échec appel API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }
            
            $data = $response->json();
            
            if (empty($data['success'])) {
                Log::error('ImageGenerationService: Réponse invalide', ['data' => $data]);
                return null;
            }
            
            $savedPath = null;

            if (!empty($data['image_base64'])) {
                $savedPath = $this->saveBase64Image($data['image_base64'], $data['image_mime'] ?? 'image/png');
            } elseif (!empty($data['image_url'])) {
                $filename = 'memory_' . uniqid() . '.png';
                $savedPath = $this->downloadAndSaveImage($data['image_url'], $filename);
            }
            
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
     * Décode une image base64 et la sauvegarde dans le storage public
     * 
     * @param string $base64Data Données base64 de l'image (sans préfixe data:...)
     * @param string $mimeType Type MIME (image/png, image/jpeg)
     * @return string|null Le chemin relatif de l'image sauvegardée ou null
     */
    private function saveBase64Image($base64Data, $mimeType = 'image/png')
    {
        try {
            $imageContent = base64_decode($base64Data, true);
            
            if ($imageContent === false || strlen($imageContent) < 100) {
                Log::error('ImageGenerationService: Données base64 invalides');
                return null;
            }

            $extension = $mimeType === 'image/jpeg' ? 'jpg' : 'png';
            $filename = 'memory_' . uniqid() . '.' . $extension;
            $directory = 'master_images';
            
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
            
            $path = $directory . '/' . $filename;
            Storage::disk('public')->put($path, $imageContent);
            
            Log::info('ImageGenerationService: Image base64 sauvegardée', [
                'path' => $path,
                'size_kb' => round(strlen($imageContent) / 1024)
            ]);
            
            return $path;
            
        } catch (\Exception $e) {
            Log::error('ImageGenerationService: Erreur sauvegarde base64', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Télécharge une image depuis une URL et la sauvegarde localement (fallback)
     * 
     * @param string $imageUrl URL de l'image à télécharger
     * @param string $filename Nom du fichier de destination
     * @return string|null Le chemin relatif de l'image sauvegardée ou null
     */
    private function downloadAndSaveImage($imageUrl, $filename)
    {
        try {
            $imageContent = file_get_contents($imageUrl);
            
            if (!$imageContent) {
                return null;
            }
            
            $directory = 'master_images';
            
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
            
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
            
            if ($i < $count) {
                usleep(500000);
            }
        }
        
        return $questions;
    }
}
