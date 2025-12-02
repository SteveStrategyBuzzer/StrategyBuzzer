<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'is_read',
        'related_match_id',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function relatedMatch(): BelongsTo
    {
        return $this->belongsTo(DuoMatch::class, 'related_match_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('receiver_id', $userId);
    }

    public function scopeConversation($query, int $userId1, int $userId2)
    {
        return $query->where(function ($q) use ($userId1, $userId2) {
            $q->where(function ($sub) use ($userId1, $userId2) {
                $sub->where('sender_id', $userId1)
                    ->where('receiver_id', $userId2);
            })->orWhere(function ($sub) use ($userId1, $userId2) {
                $sub->where('sender_id', $userId2)
                    ->where('receiver_id', $userId1);
            });
        });
    }
}
