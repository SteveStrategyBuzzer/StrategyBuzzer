<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionHistory extends Model
{
    use HasFactory;

    protected $table = 'question_history';

    protected $fillable = [
        'user_id',
        'question_id',
        'question_hash',
        'correct_answer',
        'theme',
        'niveau',
    ];

    protected $casts = [
        'niveau' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * Relation avec User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Récupère toutes les questions déjà vues par un utilisateur
     * Retourne un tableau de question_ids
     */
    public static function getSeenQuestionIds($userId)
    {
        return self::where('user_id', $userId)
            ->pluck('question_id')
            ->toArray();
    }

    /**
     * Récupère tous les hashs de questions déjà vues par un utilisateur
     * Retourne un tableau de hashs MD5
     */
    public static function getSeenQuestionHashes($userId)
    {
        return self::where('user_id', $userId)
            ->pluck('question_hash')
            ->toArray();
    }

    /**
     * Récupère toutes les réponses correctes déjà vues par un utilisateur
     * Retourne un tableau de réponses
     */
    public static function getSeenAnswers($userId)
    {
        return self::where('user_id', $userId)
            ->pluck('correct_answer')
            ->toArray();
    }

    /**
     * Enregistre une question vue par un utilisateur
     */
    public static function recordQuestion($userId, $question)
    {
        // Éviter les doublons (au cas où)
        $exists = self::where('user_id', $userId)
            ->where('question_id', $question['id'])
            ->exists();
        
        if (!$exists) {
            // Normaliser la réponse correcte en chaîne de texte
            $correctAnswer = $question['answers'][$question['correct_index']] ?? '';
            $normalizedAnswer = is_array($correctAnswer) ? json_encode($correctAnswer) : trim(strtolower((string)$correctAnswer));
            
            self::create([
                'user_id' => $userId,
                'question_id' => $question['id'],
                'question_hash' => md5($question['text']),
                'correct_answer' => $normalizedAnswer,
                'theme' => $question['theme'] ?? null,
                'niveau' => $question['difficulty'] ?? null,
            ]);
        }
    }
}
