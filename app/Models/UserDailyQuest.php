<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyQuest extends Model
{
    protected $fillable = [
        'user_id',
        'quest_id',
        'quest_date',
        'progress',
        'completed_at',
        'rewarded',
    ];

    protected $casts = [
        'progress'     => 'array',
        'completed_at' => 'datetime',
        'rewarded'     => 'boolean',
        'quest_date'   => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }
}
