<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterGame extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_code',
        'firebase_id',
        'host_user_id',
        'name',
        'languages',
        'participants_expected',
        'mode',
        'total_questions',
        'question_types',
        'domain_type',
        'theme',
        'school_country',
        'school_level',
        'school_subject',
        'creation_mode',
        'ai_images_count',
        'status',
        'current_question',
        'quiz_validated',
        'started_at',
        'ended_at',
        'structure_type',
        'team_count',
        'team_size_cap',
        'skill_policy',
        'buzz_rule'
    ];

    protected $casts = [
        'languages' => 'array',
        'question_types' => 'array',
        'quiz_validated' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime'
    ];

    // Relations
    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function codes()
    {
        return $this->hasMany(MasterGameCode::class);
    }

    public function questions()
    {
        return $this->hasMany(MasterGameQuestion::class);
    }

    public function questionsOrdered()
    {
        return $this->hasMany(MasterGameQuestion::class)->orderBy('question_number');
    }

    public function players()
    {
        return $this->hasMany(MasterGamePlayer::class);
    }

    public function teams()
    {
        return $this->hasMany(MasterGameTeam::class);
    }
}
