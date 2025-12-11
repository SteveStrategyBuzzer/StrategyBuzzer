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
            'portraits'  => ['label' => 'Portraits',   'quest' => 'Portraits variés','price' => 300],
            'cartoon'    => ['label' => 'Cartoon',     'quest' => 'Style cartoon',  'price' => 300],
            'animal'     => ['label' => 'Animal',      'quest' => 'Animaux',        'price' => 300],
            'mythique'   => ['label' => 'Mythique',    'quest' => 'Héros épiques',  'price' => 400],
            'paysage'    => ['label' => 'Paysage',     'quest' => 'Arrière-plans',  'price' => 250],
            'objet'      => ['label' => 'Objet',       'quest' => 'Objets divers',  'price' => 250],
            'clown'      => ['label' => 'Clown',       'quest' => 'Cirque',         'price' => 1000],
            'musicien'   => ['label' => 'Musicien',    'quest' => 'Musique',        'price' => 300],
            'automobile' => ['label' => 'Automobile',  'quest' => 'Voitures',       'price' => 350],
            'metier'     => ['label' => 'Métier',      'quest' => 'Professions',    'price' => 350],
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

        // ---- Buzzers
        $buzzers = [];
        foreach (glob(public_path('buzzers/*.{mp3,ogg,wav}'), GLOB_BRACE) ?: [] as $file) {
            $basename = basename($file);
            $slug = pathinfo($basename, PATHINFO_FILENAME);
            $buzzers[$slug] = [
                'slug'  => $slug,
                'label' => ucfirst(str_replace(['-', '_'], ' ', $slug)),
                'path'  => "buzzers/{$basename}",
                'price' => 120,
            ];
        }

        return array_merge(
            $packs,
            [
                'stratégiques' => [
                    'label' => 'Avatars stratégiques',
                    'items' => $stratégiques,
                ],
                'buzzers' => [
                    'label' => 'Buzzers & musiques',
                    'items' => $buzzers,
                ],
            ]
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
