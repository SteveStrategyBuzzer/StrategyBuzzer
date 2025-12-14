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
        $TIER_PRICE = ['Rare' => 500, 'Épique' => 1000, 'Légendaire' => 1500];

        $stratégiques = [
            // Rare 🎯
            'mathematicien' => [
                'tier'   => 'Rare',
                'name'   => 'Mathématicien',
                'skills' => ['Illumine si chiffre'],
            ],
            'scientifique'  => [
                'tier'   => 'Rare',
                'name'   => 'Scientifique',
                'skills' => ['Acidifie erreur (1x)'],
            ],
            'explorateur'   => [
                'tier'   => 'Rare',
                'name'   => 'Explorateur',
                'skills' => ['Voit choix adverse'],
            ],
            'defenseur'     => [
                'tier'   => 'Rare',
                'name'   => 'Défenseur',
                'skills' => ['Annule attaque'],
            ],

            // Épique ⭐
            'comedienne'    => [
                'tier'   => 'Épique',
                'name'   => 'Comédienne',
                'skills' => ['Score - en MJ','Trompe réponse'],
            ],
            'magicienne'    => [
                'tier'   => 'Épique',
                'name'   => 'Magicienne',
                'skills' => ['Q° bonus (1x)','Annule erreur (1x)'],
            ],
            'challenger'    => [
                'tier'   => 'Épique',
                'name'   => 'Challenger',
                'skills' => ['Mélange réponses','Diminue temps'],
            ],
            'historien'     => [
                'tier'   => 'Épique',
                'name'   => 'Historien',
                'skills' => ['Indice texte','+2s réponse'],
            ],

            // Légendaire 👑
            'ia-junior'     => [
                'tier'   => 'Légendaire',
                'name'   => 'IA Junior',
                'skills' => ['Suggestion IA','Élimine 2','Rejouer (1x)'],
            ],
            'stratege'      => [
                'tier'   => 'Légendaire',
                'name'   => 'Stratège',
                'skills' => ['+20% pièces','Créer team','-10% coût avatars'],
            ],
            'sprinteur'     => [
                'tier'   => 'Légendaire',
                'name'   => 'Sprinteur',
                'skills' => ['Buzzer + rapide','+3s réflexion','Auto-réactivation'],
            ],
            'visionnaire'   => [
                'tier'   => 'Légendaire',
                'name'   => 'Visionnaire',
                'skills' => ['5 Q° futures','Contre Challenger','2 pts sécurisés'],
            ],
        ];

        foreach ($stratégiques as $slug => &$a) {
            $a['slug']  = $slug;
            $a['path']  = "images/avatars/{$slug}.png";
            $a['price'] = $TIER_PRICE[$a['tier']] ?? 500;
            $a['quest'] = 'Débloquer via boutique';
        }

        // ---- Buzzer categories configuration
        $buzzerCategories = [
            'punchy'   => ['label' => 'Punchy', 'icon' => '👊', 'price' => 180],
            'vintage'  => ['label' => 'Vintage', 'icon' => '📻', 'price' => 180],
            'premium'  => ['label' => 'Premium', 'icon' => '⭐', 'price' => 180],
            'absurde'  => ['label' => 'Absurde', 'icon' => '🤪', 'price' => 180],
            'stade'    => ['label' => 'Stade', 'icon' => '🏟️', 'price' => 180],
            'discret'  => ['label' => 'Discret', 'icon' => '🤫', 'price' => 180],
            'fun'      => ['label' => 'Fun', 'icon' => '🎉', 'price' => 180],
            'electro'  => ['label' => 'Électro', 'icon' => '⚡', 'price' => 180],
            'lazer'    => ['label' => 'Lazer', 'icon' => '🔫', 'price' => 180],
            'fart'     => ['label' => 'Fart', 'icon' => '💨', 'price' => 180],
        ];

        $allBuzzerCategories = [];
        foreach ($buzzerCategories as $catSlug => $catInfo) {
            $items = [];
            foreach (glob(public_path("buzzers/{$catSlug}/*.{mp3,ogg,wav,MP3}"), GLOB_BRACE) ?: [] as $file) {
                $basename = basename($file);
                $slug = "{$catSlug}-" . pathinfo($basename, PATHINFO_FILENAME);
                $items[$slug] = [
                    'slug'  => $slug,
                    'label' => ucfirst(str_replace(['-', '_'], ' ', pathinfo($basename, PATHINFO_FILENAME))),
                    'path'  => "buzzers/{$catSlug}/{$basename}",
                    'price' => $catInfo['price'],
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
}
