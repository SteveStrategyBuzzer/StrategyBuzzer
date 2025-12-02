<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'member_user_id',
    ];

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(PlayerGroup::class, 'group_id');
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }
}
