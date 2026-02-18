<?php

namespace App\Services;

class AvatarCatalog
{
    /**
     * Retourne tout le catalogue (packs d’avatars, stratégiques, buzzers audio).
     */
    public static function get(): array
    {
        // ---- Packs
        $packs = [
            'standards'  => ['label' => 'Standards',   'quest' => 'Par défaut',      'price' => 0],
            'portraits'  => ['label' => 'Portraits',   'quest' => 'Portraits variés','price' => 800],
            'cartoon'    => ['label' => 'Cartoon',     'quest' => 'Style cartoon',  'price' => 800],
            'animal'     => ['label' => 'Animal',      'quest' => 'Animaux',        'price' => 800],
            'mythique'   => ['label' => 'Mythique',    'quest' => 'Héros épiques',  'price' => 800],
            'paysage'    => ['label' => 'Paysage',     'quest' => 'Arrière-plans',  'price' => 850],
            'instrument' => ['label' => 'Instrument',  'quest' => 'Instruments',    'price' => 850],
            'objet'      => ['label' => 'Objet',       'quest' => 'Objets divers',  'price' => 800],
            'clown'      => ['label' => 'Clown',       'quest' => 'Cirque',         'price' => 800],
            'musicien'   => ['label' => 'Musicien',    'quest' => 'Musique',        'price' => 800],
            'automobile' => ['label' => 'Automobile',  'quest' => 'Voitures',       'price' => 800],
            'metier'     => ['label' => 'Métier',      'quest' => 'Professions',    'price' => 800],
            'nation'     => ['label' => 'Nation',      'quest' => 'Monde',          'price' => 800],
        ];

        foreach ($packs as $slug => $p) {
            $packs[$slug]['slug']   = $slug;
            $packs[$slug]['images'] = self::scanImages("images/avatars/{$slug}");
            $packs[$slug]['count']  = count($packs[$slug]['images']);
        }

        // ---- Stratégiques (skills raccourcis corrigés)
        $TIER_PRICE = ['Rare' => 1000, 'Épique' => 2000, 'Légendaire' => 3000];

        $stratégiques = [
            // Rare 🎯
            'mathematicien' => [
                'tier'   => 'Rare',
                'name'   => 'Mathématicien',
                'skills' => ['💡 Illumination : Skill Automatique, Met en évidence la bonne réponse si elle contient un chiffre'],
                'skills_short' => ['💡 Illumine la bonne réponse si elle contient un chiffre'],
            ],
            'scientifique'  => [
                'tier'   => 'Rare',
                'name'   => 'Scientifique',
                'skills' => ['🧪 Acidification : Skill Sélectionnable, Après avoir buzzé marque visuellement 2 mauvaises réponses (1x par partie)'],
                'skills_short' => ['🧪 Acidifie 2 réponses (après buzz, 1x)'],
            ],
            'explorateur'   => [
                'tier'   => 'Rare',
                'name'   => 'Explorateur',
                'skills' => ['👁️ Vision : Skill Sélectionnable, Affiche le choix de l\'adversaire ou de l\'IA (1x par partie)'],
                'skills_short' => ['👁️ Voit le choix de l\'adversaire (1x)'],
            ],
            'defenseur'     => [
                'tier'   => 'Rare',
                'name'   => 'Défenseur',
                'skills' => ['🛡️ Bouclier : Skill Automatique, Annule la prochaine attaque d\'un adversaire'],
                'skills_short' => ['🛡️ Annule la prochaine attaque'],
            ],

            // Épique ⭐
            'comedienne'    => [
                'tier'   => 'Épique',
                'name'   => 'Comédienne',
                'skills' => [
                    '🎭 Faux Score : Skill Automatique, Affiche un score réduit à l\'adversaire pour le tromper',
                    '🔀 Inversion : Skill Sélectionnable, La bonne réponse apparaît fausse pour l\'adversaire (1x par partie)'
                ],
                'skills_short' => ['🎭 Affiche un faux score réduit', '🔀 Inverse la bonne réponse (1x)'],
            ],
            'magicienne'    => [
                'tier'   => 'Épique',
                'name'   => 'Magicienne',
                'skills' => [
                    '✨ Question Bonus : Skill Sélectionnable, Ajoute une question supplémentaire pour marquer des points (1x par partie)',
                    '🔮 Annulation : Skill Sélectionnable, Annule les points perdus sur une erreur (1x par partie)'
                ],
                'skills_short' => ['✨ Ajoute une question bonus (1x)', '🔮 Annule les points perdus (1x)'],
            ],
            'challenger'    => [
                'tier'   => 'Épique',
                'name'   => 'Challenger',
                'skills' => [
                    '🔀 Mélange Réponses : Skill Sélectionnable, Les réponses de l\'adversaire se mélangent toutes les 1.5s (1x par partie)',
                    '⏱️ Chrono Réduit : Skill Sélectionnable, Réduit le temps de buzz de l\'adversaire de 8s à 6s (1x par partie)'
                ],
                'skills_short' => ['🔀 Mélange les réponses adverses (1x)', '⏱️ Réduit -2 sec le buzzer adverse (1x)'],
            ],
            'historien'     => [
                'tier'   => 'Épique',
                'name'   => 'Historien',
                'skills' => [
                    '📜 Savoir Intemporel : Skill Automatique, Permet de répondre après le timeout pour +1 pt',
                    '✍️ Correction Historique : Skill Sélectionnable, Annule la pénalité -2 et donne des points après un mauvais buzz (1x par partie)'
                ],
                'skills_short' => ['📜 Répond après le timeout (+1 pt)', '✍️ Annule la pénalité -2 (1x)'],
            ],

            // Légendaire 👑
            'ia-junior'     => [
                'tier'   => 'Légendaire',
                'name'   => 'IA Junior',
                'skills' => [
                    '🤖 IA Assist : Skill Sélectionnable, L\'IA suggère une réponse avec 90% de précision (1x par partie)',
                    '❌ Élimination : Skill Sélectionnable, Élimine 2 mauvaises réponses sur les 4 (1x par partie)',
                    '↩️ Seconde Chance : Skill Sélectionnable, Après une erreur, permet de rechoisir parmi les 3 autres réponses (1x par partie)'
                ],
                'skills_short' => ['🤖 Suggère la réponse à 90% (1x)', '❌ Élimine 2 mauvaises réponses (1x)', '↩️ Rejouer après erreur (1x)'],
            ],
            'stratege'      => [
                'tier'   => 'Légendaire',
                'name'   => 'Stratège',
                'skills' => [
                    '💰 Bonus Pièces : Skill Automatique, +25% de pièces d\'intelligence et de compétence gagnées sur les victoires',
                    '👥 Coéquipier : Skill Passif, Permet d\'ajouter 1 Avatar rare comme coéquipier dans tous les modes',
                    '🏷️ Réduction : Skill Passif, Réduit le coût de déblocage des avatars : Rare -40%, Épique -30%, Légendaire -20%'
                ],
                'skills_short' => ['💰 +25% pièces sur victoires', '👥 Ajouter 1 coéquipier rare (tous modes)', '🏷️ Avatars: Rare -40%, Épique -30%, Légendaire -20%'],
            ],
            'sprinteur'     => [
                'tier'   => 'Légendaire',
                'name'   => 'Sprinteur',
                'skills' => [
                    '⚡ Réflexes : Skill Automatique, Les 5 premières questions affichent le buzzer à 0.75s du vrai temps',
                    '🕒 Temps Bonus : Skill Sélectionnable, +3 secondes de réflexion supplémentaires (1x par manche)',
                    '🔋 Recharge : Skill Automatique, Réactive tous les skills automatiquement après chaque manche'
                ],
                'skills_short' => ['⚡ Buzzer à 0.75s (5 premières questions)', '🕒 +3s réflexion (1x/manche)', '🔋 Skills réactivés après chaque manche'],
            ],
            'visionnaire'   => [
                'tier'   => 'Légendaire',
                'name'   => 'Visionnaire',
                'skills' => [
                    '👁️ Prémonition : Skill Sélectionnable, Voit un résumé thématique de la question suivante depuis la page Résultat (👁️ 5/5 → 4/5 → ...)',
                    '🏰 Forteresse : Skill Automatique, Immunité contre les attaques du Challenger',
                    '🎯 Points Sécurisés : Skill Sélectionnable, Sur 2 pts la bonne réponse devient la seule cliquable avec surbrillance'
                ],
                'skills_short' => ['👁️ Résumé question suivante (5/5)', '🏰 Contre attaque Challenger', '🎯 Bonne réponse en surbrillance (2 pts)'],
            ],
        ];

        foreach ($stratégiques as $slug => &$a) {
            $a['slug']  = $slug;
            $a['path']  = "images/avatars/{$slug}.png";
            $a['price'] = $TIER_PRICE[$a['tier']] ?? 1000;
            $a['quest'] = 'Débloquer via boutique';
        }

        // ---- GamePlay sounds categories configuration
        $buzzerCategories = [
            'punchy'    => ['label' => 'Punchy', 'icon' => '👊'],
            'vintage'   => ['label' => 'Vintage', 'icon' => '📻'],
            'premium'   => ['label' => 'Premium', 'icon' => '⭐'],
            'absurde'   => ['label' => 'Absurde', 'icon' => '🤪'],
            'stade'     => ['label' => 'Stade', 'icon' => '🏟️'],
            'discret'   => ['label' => 'Discret', 'icon' => '🤫'],
            'fun'       => ['label' => 'Fun', 'icon' => '🎉'],
            'electro'   => ['label' => 'Électro', 'icon' => '⚡'],
            'laser'     => ['label' => 'Laser', 'icon' => '🔫'],
            'fart'      => ['label' => 'Fart', 'icon' => '💨'],
            'correct'   => ['label' => 'Bonne réponse', 'icon' => '✅'],
            'incorrect' => ['label' => 'Mauvaise réponse', 'icon' => '❌'],
        ];

        $allBuzzerCategories = [];
        foreach ($buzzerCategories as $catSlug => $catInfo) {
            $items = [];
            foreach (glob(public_path("buzzers/{$catSlug}/*.{mp3,ogg,wav,MP3}"), GLOB_BRACE) ?: [] as $file) {
                $basename = basename($file);
                $slug = "{$catSlug}-" . pathinfo($basename, PATHINFO_FILENAME);
                $duration = self::getAudioDuration($file);
                $price = self::calculateBuzzerPrice($duration);
                $items[$slug] = [
                    'slug'     => $slug,
                    'label'    => ucfirst(str_replace(['-', '_'], ' ', pathinfo($basename, PATHINFO_FILENAME))),
                    'path'     => "buzzers/{$catSlug}/{$basename}",
                    'price'    => $price,
                    'duration' => $duration,
                    'category' => $catInfo['label'],
                ];
            }
            $allBuzzerCategories["buzzers_{$catSlug}"] = [
                'label' => $catInfo['label'],
                'icon'  => $catInfo['icon'],
                'items' => $items,
            ];
        }

        return array_merge(
            $packs,
            [
                'stratégiques' => [
                    'label' => 'Avatars stratégiques',
                    'items' => $stratégiques,
                ],
            ],
            $allBuzzerCategories
        );
    }

    /**
     * Retourne uniquement les avatars stratégiques.
     */
    public static function getStrategiques(): array
    {
        $catalog = self::get();
        $strategiques = $catalog['stratégiques'] ?? [];
        return $strategiques['items'] ?? [];
    }

    private static function scanImages(string $relativeDir): array
    {
        $dir = public_path($relativeDir);
        if (!is_dir($dir)) return [];
        $out = [];
        $files = glob($dir . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
        natsort($files);
        foreach ($files as $f) {
            $out[] = $relativeDir . '/' . basename($f);
        }
        return $out;
    }

    private static function getAudioDuration(string $filePath): float
    {
        try {
            $getID3 = new \getID3();
            $fileInfo = $getID3->analyze($filePath);
            return $fileInfo['playtime_seconds'] ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private static function calculateBuzzerPrice(float $duration): int
    {
        if ($duration < 1) {
            return 180;
        }
        $extraHalfSeconds = floor(($duration - 1) / 0.5);
        return 180 + ((int) $extraHalfSeconds * 40);
    }
}
