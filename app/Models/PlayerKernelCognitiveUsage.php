<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PlayerKernelCognitiveUsage
 *
 * Mémoire gameplay durable : 1 ligne par (joueur × noyau × famille × forme) cognitif.
 * Une ligne est créée UNIQUEMENT après exposition complète d'un cognitif
 * (question + bonne réponse + Saviez-Vous), via KernelConsumptionService.
 *
 * Pas de updated_at : la consommation est un fait immuable (consumed_at = 1ère fois).
 */
class PlayerKernelCognitiveUsage extends Model
{
    protected $table = 'player_kernel_cognitive_usage';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'kernel_code',
        'question_intent_id',
        'depth',
        'domain',
        'cognitive_family',
        'cognitive_form',
        'match_ref',
        'mode',
        'consumed_at',
    ];

    protected $casts = [
        'user_id'            => 'integer',
        'question_intent_id' => 'integer',
        'depth'              => 'integer',
        'consumed_at'        => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
