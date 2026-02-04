<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class AvatarSkillService
{
    public static function getAvatarSkills($avatar, $userId = null)
    {
        $skills = [
            'Aucun' => [
                'rarity' => null,
                'skills' => []
            ],
            
            // RARE - 1 compétence chacun
            'Mathématicien' => [
                'rarity' => 'rare',
                'icon' => '🧠',
                'skills' => [
                    [
                        'id' => 'illuminate_numbers',
                        'name' => 'Illumine si chiffre',
                        'icon' => '💡',
                        'description' => 'Met en évidence la bonne réponse si elle contient un chiffre',
                        'type' => 'visual',
                        'trigger' => 'question',
                        'uses_per_match' => 1,
                        'auto' => true
                    ]
                ]
            ],
            'Scientifique' => [
                'rarity' => 'rare',
                'icon' => '🧪',
                'skills' => [
                    [
                        'id' => 'acidify_error',
                        'name' => 'Acidifie erreur',
                        'icon' => '🧪',
                        'description' => 'Après avoir buzzé, acidifie 2 mauvaises réponses (1x par partie)',
                        'type' => 'visual',
                        'trigger' => 'answer',
                        'uses_per_match' => 1,
                        'auto' => false,
                        'requires_buzz' => true
                    ]
                ]
            ],
            'Explorateur' => [
                'rarity' => 'rare',
                'icon' => '🧭',
                'skills' => [
                    [
                        'id' => 'see_opponent_choice',
                        'name' => 'Voit choix adverse',
                        'icon' => '👁️',
                        'description' => 'Voit le choix de l\'adversaire (ou la réponse la plus cliquée en Master)',
                        'type' => 'info',
                        'trigger' => 'question',
                        'uses_per_match' => 1,
                        'auto' => false
                    ]
                ]
            ],
            'Défenseur' => [
                'rarity' => 'rare',
                'icon' => '🛡️',
                'skills' => [
                    [
                        'id' => 'block_attack',
                        'name' => 'Bouclier',
                        'icon' => '🛡️',
                        'description' => 'Annule une attaque provenant de n\'importe quel Avatar',
                        'type' => 'defensive',
                        'trigger' => 'passive',
                        'uses_per_match' => 1,
                        'auto' => true
                    ]
                ]
            ],
            
            // ÉPIQUE - 2 compétences chacun
            'Comédien' => [
                'rarity' => 'epic',
                'icon' => '🎭',
                'skills' => [
                    [
                        'id' => 'fake_score',
                        'name' => 'Score trompeur',
                        'icon' => '🎯',
                        'description' => 'Peut indiquer un score inférieur jusqu\'à la fin de la partie (mode Maître)',
                        'type' => 'deception',
                        'trigger' => 'match_start',
                        'uses_per_match' => 1,
                        'auto' => false,
                        'master_only' => true
                    ],
                    [
                        'id' => 'invert_answers',
                        'name' => 'Inversion',
                        'icon' => '🌀',
                        'description' => 'Peut tromper les joueurs en inversant bonne et mauvaise réponse',
                        'type' => 'deception',
                        'trigger' => 'question',
                        'uses_per_match' => 1,
                        'auto' => false
                    ]
                ]
            ],
            'Comédienne' => [
                'rarity' => 'epic',
                'icon' => '🎭',
                'skills' => [
                    [
                        'id' => 'fake_score',
                        'name' => 'Score trompeur',
                        'icon' => '🎯',
                        'description' => 'Affiche un score réduit à l\'adversaire pour le tromper',
                        'type' => 'deception',
                        'trigger' => 'match_start',
                        'uses_per_match' => 1,
                        'auto' => true
                    ],
                    [
                        'id' => 'invert_answers',
                        'name' => 'Inversion',
                        'icon' => '🌀',
                        'description' => 'La bonne réponse apparaît fausse pour l\'adversaire (1x par partie)',
                        'type' => 'deception',
                        'trigger' => 'question',
                        'uses_per_match' => 1,
                        'auto' => false
                    ]
                ]
            ],
            'Magicienne' => [
                'rarity' => 'epic',
                'icon' => '🧙‍♀️',
                'skills' => [
                    [
                        'id' => 'cancel_error',
                        'name' => 'Annule erreur',
                        'icon' => '⭐',
                        'description' => 'Annule une mauvaise réponse non-Buzz une fois par partie',
                        'type' => 'correction',
                        'trigger' => 'result',
                        'uses_per_match' => 1,
                        'auto' => false
                    ],
                    [
                        'id' => 'bonus_question',
                        'name' => 'Question bonus',
                        'icon' => '✨',
                        'description' => 'Obtient une question bonus par partie',
                        'type' => 'bonus',
                        'trigger' => 'result',
                        'uses_per_match' => 1,
                        'auto' => false
                    ]
                ]
            ],
            'Challenger' => [
                'rarity' => 'epic',
                'icon' => '🔥',
                'skills' => [
                    [
                        'id' => 'shuffle_answers',
                        'name' => 'Mélange',
                        'icon' => '🔄',
                        'description' => 'Fait changer la position des réponses toutes les 1.5 secondes',
                        'type' => 'attack',
                        'trigger' => 'question',
                        'uses_per_match' => 1,
                        'auto' => false,
                        'affects_others' => true
                    ],
                    [
                        'id' => 'reduce_time',
                        'name' => 'Diminue temps',
                        'icon' => '⏱️',
                        'description' => 'Réduit le temps de buzz de l\'adversaire de 8s à 6s',
                        'type' => 'attack',
                        'trigger' => 'question',
                        'uses_per_match' => 1,
                        'auto' => false,
                        'affects_others' => true
                    ]
                ]
            ],
            'Historien' => [
                'rarity' => 'epic',
                'icon' => '📚',
                'skills' => [
                    [
                        'id' => 'knowledge_without_time',
                        'name' => 'Savoir sans temps',
                        'icon' => '🪶',
                        'description' => 'Répondre après le timeout pour +1 pt',
                        'type' => 'bonus',
                        'trigger' => 'answer',
                        'uses_per_match' => 1,
                        'auto' => false
                    ],
                    [
                        'id' => 'history_corrects',
                        'name' => "L'histoire corrige",
                        'icon' => '📜',
                        'description' => 'Annule la pénalité -2 et donne des points après erreur',
                        'type' => 'correction',
                        'trigger' => 'result',
                        'uses_per_match' => 1,
                        'auto' => false
                    ]
                ]
            ],
            
            // LÉGENDAIRE - 3 compétences chacun
            'IA Junior' => [
                'rarity' => 'legendary',
                'icon' => '🤖',
                'skills' => [
                    [
                        'id' => 'ai_suggestion',
                        'name' => 'Suggestion IA',
                        'icon' => '💡',
                        'description' => 'A 90% de chance que la réponse illuminée soit correcte',
                        'type' => 'visual',
                        'trigger' => 'question',
                        'uses_per_match' => 1,
                        'auto' => false,
                        'success_rate' => 0.9
                    ],
                    [
                        'id' => 'eliminate_two',
                        'name' => 'Élimination',
                        'icon' => '❌',
                        'description' => 'Élimine 2 mauvaises réponses sur 4',
                        'type' => 'visual',
                        'trigger' => 'question',
                        'uses_per_match' => 1,
                        'auto' => false
                    ],
                    [
                        'id' => 'replay',
                        'name' => 'Rejouer',
                        'icon' => '↩️',
                        'description' => 'Rejouer après une erreur (1x)',
                        'type' => 'correction',
                        'trigger' => 'result',
                        'uses_per_match' => 1,
                        'auto' => false
                    ]
                ]
            ],
            'Stratège' => [
                'rarity' => 'legendary',
                'icon' => '🏆',
                'skills' => [
                    [
                        'id' => 'coin_bonus',
                        'name' => 'Bonus pièces',
                        'icon' => '💰',
                        'description' => 'Gagne +25% de pièces d\'intelligence et de compétence sur victoire',
                        'type' => 'passive',
                        'trigger' => 'victory',
                        'uses_per_match' => -1,
                        'auto' => true
                    ],
                    [
                        'id' => 'create_team',
                        'name' => 'Coéquipier',
                        'icon' => '👥',
                        'description' => 'Ajouter 1 avatar rare comme coéquipier dans tous les modes',
                        'type' => 'team',
                        'trigger' => 'match_start',
                        'uses_per_match' => 1,
                        'auto' => false
                    ],
                    [
                        'id' => 'avatar_discount',
                        'name' => 'Réduction avatars',
                        'icon' => '🏷️',
                        'description' => 'Rare -40%, Épique -30%, Légendaire -20%',
                        'type' => 'passive',
                        'trigger' => 'permanent',
                        'uses_per_match' => -1,
                        'auto' => true
                    ]
                ]
            ],
            'Sprinteur' => [
                'rarity' => 'legendary',
                'icon' => '⚡',
                'skills' => [
                    [
                        'id' => 'faster_buzz',
                        'name' => 'Réflexes',
                        'icon' => '⚡',
                        'description' => 'Les 5 premières questions affichent le buzzer à 0.75s du vrai temps',
                        'type' => 'passive',
                        'trigger' => 'first_5_questions',
                        'uses_per_match' => -1,
                        'auto' => true
                    ],
                    [
                        'id' => 'time_bonus',
                        'name' => 'Temps Bonus',
                        'icon' => '🕒',
                        'description' => '+3 secondes de réflexion supplémentaires (1x par manche)',
                        'type' => 'time',
                        'trigger' => 'question',
                        'uses_per_match' => 1,
                        'auto' => false
                    ],
                    [
                        'id' => 'skill_recharge',
                        'name' => 'Recharge',
                        'icon' => '🔋',
                        'description' => 'Réactive tous les skills automatiquement après chaque manche',
                        'type' => 'passive',
                        'trigger' => 'round_complete',
                        'uses_per_match' => -1,
                        'auto' => true
                    ]
                ]
            ],
            'Visionnaire' => [
                'rarity' => 'legendary',
                'icon' => '👁️',
                'skills' => [
                    [
                        'id' => 'premonition',
                        'name' => 'Prémonition',
                        'icon' => '👁️',
                        'description' => 'Voit un résumé thématique de la question suivante (👁️ 5/5 → 4/5 → ...)',
                        'type' => 'info',
                        'trigger' => 'result_page',
                        'uses_per_match' => 5,
                        'auto' => false,
                        'display_counter' => true
                    ],
                    [
                        'id' => 'fortress',
                        'name' => 'Forteresse',
                        'icon' => '🏰',
                        'description' => 'Immunité contre les attaques du Challenger',
                        'type' => 'defensive',
                        'trigger' => 'passive',
                        'uses_per_match' => -1,
                        'auto' => true
                    ],
                    [
                        'id' => 'secure_answer',
                        'name' => 'Réponse Sécurisée',
                        'icon' => '🎯',
                        'description' => 'Sur 2 pts, bonne réponse seule cliquable avec surbrillance',
                        'type' => 'visual',
                        'trigger' => 'answer_page',
                        'uses_per_match' => -1,
                        'auto' => false,
                        'condition' => 'player_at_2_points'
                    ]
                ]
            ],
        ];
        
        $slugToName = [
            'mathematicien' => 'Mathématicien',
            'scientifique' => 'Scientifique',
            'explorateur' => 'Explorateur',
            'defenseur' => 'Défenseur',
            'comedienne' => 'Comédienne',
            'comedien' => 'Comédien',
            'magicienne' => 'Magicienne',
            'challenger' => 'Challenger',
            'historien' => 'Historien',
            'ia-junior' => 'IA Junior',
            'ia junior' => 'IA Junior',
            'stratege' => 'Stratège',
            'stratège' => 'Stratège',
            'sprinteur' => 'Sprinteur',
            'visionnaire' => 'Visionnaire',
        ];
        
        $normalizedAvatar = $slugToName[strtolower($avatar)] ?? $avatar;
        
        $result = $skills[$normalizedAvatar] ?? ['rarity' => null, 'skills' => []];
        
        if (in_array(strtolower($normalizedAvatar), ['stratège', 'stratege'])) {
            $teammate = self::getEffectiveTeammate($userId);
            if ($teammate) {
                $teammateFullName = $slugToName[strtolower($teammate)] ?? $teammate;
                $teammateData = $skills[$teammateFullName] ?? null;
                
                if ($teammateData && !empty($teammateData['skills'])) {
                    $result['skills'] = array_merge($result['skills'], $teammateData['skills']);
                    $result['teammate'] = [
                        'name' => $teammateFullName,
                        'slug' => $teammate,
                        'rarity' => $teammateData['rarity'] ?? 'rare',
                        'icon' => $teammateData['icon'] ?? '🎯'
                    ];
                }
            }
            
            $result['has_unlocked_rare'] = self::hasUnlockedRareAvatar($userId);
        }
        
        return $result;
    }
    
    public static function getAvatarSkillsSimple($avatar, $userId = null)
    {
        $fullData = self::getAvatarSkills($avatar, $userId);
        if (empty($fullData['skills'])) {
            return [];
        }
        return array_map(function($skill) {
            return $skill['description'];
        }, $fullData['skills']);
    }
    
    private static function getEffectiveTeammate($userId = null)
    {
        $selectedTeammate = session('stratege_teammate');
        
        if ($selectedTeammate) {
            return $selectedTeammate;
        }
        
        $user = $userId ? \App\Models\User::find($userId) : Auth::user();
        if (!$user) {
            return null;
        }
        
        $settings = (array) ($user->profile_settings ?? []);
        $unlockedAvatars = $settings['unlocked_avatars'] ?? [];
        
        $rareAvatars = ['mathematicien', 'scientifique', 'explorateur', 'defenseur'];
        
        foreach ($rareAvatars as $rare) {
            if (in_array($rare, $unlockedAvatars)) {
                session(['stratege_teammate' => $rare]);
                return $rare;
            }
        }
        
        return null;
    }
    
    private static function hasUnlockedRareAvatar($userId = null)
    {
        $user = $userId ? \App\Models\User::find($userId) : Auth::user();
        if (!$user) {
            return false;
        }
        
        $settings = (array) ($user->profile_settings ?? []);
        $unlockedAvatars = $settings['unlocked_avatars'] ?? [];
        
        $rareAvatars = ['mathematicien', 'scientifique', 'explorateur', 'defenseur'];
        
        foreach ($rareAvatars as $rare) {
            if (in_array($rare, $unlockedAvatars)) {
                return true;
            }
        }
        
        return false;
    }
    
    public static function getStrategicAvatarPath($avatarName)
    {
        if ($avatarName === 'Aucun' || empty($avatarName)) {
            return '';
        }
        
        $slug = strtolower($avatarName);
        $slug = str_replace(['é', 'è', 'ê'], 'e', $slug);
        $slug = str_replace(['à', 'â'], 'a', $slug);
        $slug = str_replace(' ', '-', $slug);
        
        return asset("images/avatars/{$slug}.png");
    }
}
