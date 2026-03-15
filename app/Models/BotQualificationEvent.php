<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotQualificationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_type',
        'reference_id',
        'counted_at',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
