<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    public const EMBLEM_CATEGORIES = [
        'animals' => ['🦁', '🐯', '🐻', '🦊', '🐺', '🦅', '🦈', '🐉', '🦖', '🦋', '🐝', '🦂', '🦀', '🐙', '🦑', '🐬', '🐳', '🦭', '🐘', '🦏', '🦛', '🐪', '🦒', '🦘', '🦡', '🦔', '🐿️', '🦇', '🦉', '🐸', '🐊', '🐢', '🦎', '🐍', '🦓', '🦬', '🐗', '🦌', '🐏', '🐐', '🐓', '🦃', '🦢', '🦩', '🦚', '🦜', '🦤', '🕊️', '🐕', '🐈'],
        'warriors' => ['⚔️', '🗡️', '🛡️', '🏹', '🪓', '🔱', '⚒️', '🪃', '🎯', '💣', '🧨', '🪖', '⛑️', '🥷', '🤺', '🦸', '🦹', '👹', '👺', '💀', '☠️', '👻', '🤖', '👽', '👾', '🦾', '🦿', '🏴‍☠️', '⚓', '🎖️', '🏅', '🥇', '🥈', '🥉', '🎗️', '🎪', '🎭', '🪬', '🔮', '📿', '💎', '👑', '💍', '🧿', '⚜️', '🔰', '⚡', '🌟', '✨', '💫'],
        'sports' => ['🏆', '⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🥊', '🥋', '⛳', '🏌️', '🎿', '⛷️', '🏂', '🛷', '🥌', '🏄', '🚣', '🏊', '🚴', '🏇', '🤸', '🤼', '🤽', '🤾', '🧗', '🎳', '🎽', '🥅', '🎣', '🤿', '🎮', '🕹️', '🎲', '🎯', '🎰', '🧩', '♟️', '🎪', '🎨', '🎬'],
        'symbols' => ['🌟', '⭐', '✨', '💫', '🔥', '💧', '🌊', '⚡', '❄️', '🌈', '☀️', '🌙', '⚛️', '♾️', '☯️', '☮️', '✝️', '☪️', '🕉️', '✡️', '🔯', '🪬', '☸️', '⚜️', '♻️', '⚠️', '☢️', '☣️', '🔱', '📛', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '🟤', '⚫', '⚪', '🔶', '🔷', '🔺', '🔻', '💠', '🔘', '🔲', '🔳', '▪️', '▫️', '◾'],
        'elements' => ['🔥', '💧', '🌊', '⚡', '❄️', '🌪️', '☁️', '🌈', '☀️', '🌙', '⭐', '🌍', '🌏', '🌎', '🗺️', '🏔️', '⛰️', '🌋', '🗻', '🏕️', '🏝️', '🏜️', '🌲', '🌳', '🌴', '🌵', '🌾', '🌻', '🌺', '🌸', '🌹', '🥀', '🍀', '🍁', '🍂', '🍃', '💐', '🌷', '🪷', '🪻', '🌼', '🪴', '🎋', '🎍', '🎄', '🪵', '🪨', '💎', '🔮', '🧊'],
        'gaming' => ['🎮', '🕹️', '👾', '🤖', '🎲', '🎯', '🎰', '🧩', '♟️', '🃏', '🀄', '🎴', '🎪', '🎨', '🎬', '🎤', '🎧', '🎼', '🎹', '🥁', '🎷', '🎺', '🎸', '🪕', '🎻', '🪗', '🎭', '🎪', '🎟️', '🎫', '🎞️', '📽️', '🎥', '📺', '📻', '🔊', '📱', '💻', '🖥️', '⌨️', '🖱️', '🕶️', '🥽', '🎚️', '📡', '🔋', '💿', '📀', '💾', '🖲️'],
        'royalty' => ['👑', '🏰', '🏯', '⚜️', '🔱', '💍', '📿', '👸', '🤴', '🧔', '🧙', '🧚', '🧛', '🧜', '🧝', '🧞', '🧟', '🦸', '🦹', '🎭', '🗝️', '🔐', '📜', '📯', '🔔', '🎺', '🎖️', '🏅', '🥇', '🪙', '💰', '💎', '🏆', '🎗️', '🎀', '🎁', '🕯️', '🪔', '🏮', '🪭', '🪮', '👒', '🎩', '🧢', '💄', '💅', '🧣', '🧤', '🧦', '👗'],
        'flags' => ['🏳️', '🏴', '🏁', '🚩', '🎌', '🏴‍☠️', '🇫🇷', '🇬🇧', '🇺🇸', '🇩🇪', '🇪🇸', '🇮🇹', '🇵🇹', '🇧🇷', '🇯🇵', '🇰🇷', '🇨🇳', '🇮🇳', '🇷🇺', '🇦🇺', '🇨🇦', '🇲🇽', '🇦🇷', '🇨🇱', '🇨🇴', '🇵🇪', '🇻🇪', '🇪🇨', '🇧🇴', '🇵🇾', '🇺🇾', '🇬🇷', '🇹🇷', '🇪🇬', '🇿🇦', '🇳🇬', '🇰🇪', '🇲🇦', '🇹🇳', '🇸🇳', '🇨🇮', '🇬🇭', '🇨🇲', '🇸🇪', '🇳🇴', '🇩🇰', '🇫🇮', '🇮🇪', '🇳🇱', '🇧🇪'],
        'masks' => ['🎭', '👹', '👺', '🤡', '💀', '☠️', '👻', '👽', '👾', '🤖', '😈', '👿', '🎃', '🌚', '🌝', '🌛', '🌜', '🌞', '🙈', '🙉', '🙊', '🐵', '🦁', '🐯', '🐻', '🐼', '🐨', '🐮', '🐷', '🐸', '🐲', '🐉', '🦊', '🐺', '🦝', '🐱', '🐭', '🐹', '🐰', '🦄', '🦋', '🐝', '🦂', '🕷️', '🦅', '🦉', '🦇', '🐍', '🦎', '🐢'],
        'gems' => ['💎', '💍', '👑', '🔮', '🧿', '📿', '🪬', '✨', '⭐', '🌟', '💫', '🔥', '❄️', '🌈', '🌊', '⚡', '☀️', '🌙', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '💜', '💙', '💚', '💛', '🧡', '❤️', '🤍', '🖤', '🤎', '💔', '❣️', '💕', '💖', '💗', '💘', '💝', '💞', '💟', '♥️', '🩷', '🩵', '🩶', '🪩', '🎱', '🔘', '⚫'],
    ];

    protected $fillable = [
        'name',
        'tag',
        'team_code',
        'captain_id',
        'division',
        'points',
        'level',
        'matches_played',
        'matches_won',
        'matches_lost',
        'is_recruiting',
        'emblem_category',
        'emblem_index',
        'custom_emblem_path',
    ];

    protected $casts = [
        'is_recruiting' => 'boolean',
        'points' => 'integer',
        'level' => 'integer',
        'matches_played' => 'integer',
        'matches_won' => 'integer',
        'matches_lost' => 'integer',
        'emblem_index' => 'integer',
    ];

    public static array $emblemCategories = [
        'animals' => ['name' => 'Animaux', 'icon' => '🦁', 'count' => 50],
        'warriors' => ['name' => 'Guerriers', 'icon' => '⚔️', 'count' => 50],
        'sports' => ['name' => 'Sport', 'icon' => '🏆', 'count' => 50],
        'symbols' => ['name' => 'Symboles', 'icon' => '🌟', 'count' => 50],
        'elements' => ['name' => 'Éléments', 'icon' => '🔥', 'count' => 50],
        'gaming' => ['name' => 'Gaming', 'icon' => '🎮', 'count' => 50],
        'royalty' => ['name' => 'Royauté', 'icon' => '👑', 'count' => 50],
        'flags' => ['name' => 'Drapeaux', 'icon' => '🌍', 'count' => 50],
        'masks' => ['name' => 'Masques', 'icon' => '🎭', 'count' => 50],
        'gems' => ['name' => 'Gemmes', 'icon' => '💎', 'count' => 50],
    ];

    public function getEmblemUrl(): string
    {
        if ($this->custom_emblem_path) {
            return asset('storage/' . $this->custom_emblem_path);
        }
        return asset("emblems/{$this->emblem_category}/{$this->emblem_index}.svg");
    }

    public function getEmblemAttribute(): string
    {
        if ($this->emblem_category && isset(self::EMBLEM_CATEGORIES[$this->emblem_category])) {
            $emojis = self::EMBLEM_CATEGORIES[$this->emblem_category];
            $index = $this->emblem_index ?? 0;
            if (isset($emojis[$index])) {
                return $emojis[$index];
            }
        }
        return '🛡️';
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function matchesAsTeam1(): HasMany
    {
        return $this->hasMany(LeagueTeamMatch::class, 'team1_id');
    }

    public function matchesAsTeam2(): HasMany
    {
        return $this->hasMany(LeagueTeamMatch::class, 'team2_id');
    }

    public function isFull(): bool
    {
        return $this->members()->count() >= 5;
    }

    public function isMember(int $userId): bool
    {
        return $this->members()->where('users.id', $userId)->exists();
    }

    public function isCaptain(int $userId): bool
    {
        return $this->captain_id === $userId;
    }

    public function canAddMember(): bool
    {
        return !$this->isFull();
    }

    public function getWinRate(): float
    {
        if ($this->matches_played === 0) {
            return 0.0;
        }
        return ($this->matches_won / $this->matches_played) * 100;
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($team) {
            if (empty($team->team_code)) {
                $team->team_code = self::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = 'EQ-' . strtoupper(substr(md5(uniqid()), 0, 5));
        } while (self::where('team_code', $code)->exists());
        
        return $code;
    }
}
