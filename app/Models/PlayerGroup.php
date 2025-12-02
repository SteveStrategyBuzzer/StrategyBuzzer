<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function members()
    {
        return $this->hasMany(PlayerGroupMember::class, 'group_id');
    }

    public function memberUsers()
    {
        return $this->belongsToMany(User::class, 'player_group_members', 'group_id', 'member_user_id');
    }
}
