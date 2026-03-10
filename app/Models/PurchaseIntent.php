<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_key',
        'product_type',
        'coin_type',
        'coins_to_deliver',
        'amount_cents',
        'currency',
        'stripe_session_id',
        'status',
        'fulfilled_at',
        'metadata',
    ];

    protected $casts = [
        'fulfilled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
