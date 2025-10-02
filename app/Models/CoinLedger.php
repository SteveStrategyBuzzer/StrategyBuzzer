<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinLedger extends Model
{
    use HasFactory;

    protected $table = 'coin_ledger';

    protected $fillable = [
        'user_id',
        'delta',
        'reason',
        'ref_type',
        'ref_id',
        'balance_after',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
