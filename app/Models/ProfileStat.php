<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileStat extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'solo_matchs_joues',
        'solo_victoires',
        'solo_defaites',
        'solo_ratio_victoire',
        'solo_matchs_3_manches',
        'solo_victoires_3_manches',
        'solo_performance_moyenne',
        'solo_efficacite_joueur',
        'duo_matchs_joues',
        'duo_victoires',
        'duo_defaites',
        'duo_ratio_victoire',
        'duo_performance_moyenne',
        'duo_efficacite_joueur',
        'league_matchs_joues',
        'league_victoires',
        'league_defaites',
        'league_ratio_victoire',
        'league_performance_moyenne',
        'league_efficacite_joueur',
    ];
    
    protected $casts = [
        'solo_ratio_victoire' => 'decimal:2',
        'solo_performance_moyenne' => 'decimal:2',
        'solo_efficacite_joueur' => 'decimal:2',
        'duo_ratio_victoire' => 'decimal:2',
        'duo_performance_moyenne' => 'decimal:2',
        'duo_efficacite_joueur' => 'decimal:2',
        'league_ratio_victoire' => 'decimal:2',
        'league_performance_moyenne' => 'decimal:2',
        'league_efficacite_joueur' => 'decimal:2',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
