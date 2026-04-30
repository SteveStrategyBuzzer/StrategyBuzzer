<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriticalActionLog extends Model
{
    public $timestamps = false;

    protected $table = 'critical_actions_log';

    protected $fillable = [
        'user_id',
        'action',
        'payload',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
