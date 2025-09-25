<?php 

namespace App\Services;

class AvatarCatalog
{
    /**
     * Retourne tout le catalogue (packs d’avatars, stratégiques stratégiques, buzzers audio).
     * - Les images sont lues dans public/images/avatars/<slug> (packs)
     * - Les stratégiques ont des PNG fixes dans public/images/avatars/
     * - Les buzzers audio sont dans public/buzzers (mp3/ogg/wav)
     */
    public static function get(): array
    {
        // ---- Packs (dossiers)
        $packs = [
            'standards'  => ['label' => 'Standards',   'quest' => 'Par défaut',     'price' => 0],
            'portraits'  => ['label' => 'Portraits',   'quest' => 'Portraits variés','price' => 300],
            'cartoon'    => ['label' => 'Cartoon',     'quest' => 'Style cartoon',  'price' => 300],
            'animal'     => ['label' => 'Animaux',     'quest' => 'Animaux',        'price' => 300],
            'mythique'   => ['label' => 'Mythiques',   'quest' => 'Héros épiques',  'price' => 400],
            'paysage'    => ['label' => 'Paysages',    'quest' => 'Arrière-plans',  'price' => 250],
            'objet'      => ['label' => 'Objets',      'quest' => 'Objets divers',  'price' => 250],
            'clown'      => ['label' => 'Clowns',      'quest' => 'Cirque',         'price' => 200],
            'musicien'   => ['label' => 'Musiciens',   'quest' => 'Musique',        'price' => 300],
            'automobile' => ['label' => 'Automobiles', 'quest' => 'Voitures',       'price' => 350],
        ];

        foreach ($packs as $slug => $p) {
            $packs[$slug]['slug']   = $slug;
            $packs[$slug]['images'] = self::scanImages("images/avatars/{$slug}");
            $packs[$slug]['count']  = count($packs[$slug]['images']);
        }

        // ---- Stratégiques (avatars stratégiques avec SKILLS)
        $TIER_PRICE = ['Rare' => 500, 'Épique' => 1000, 'Légendaire' => 1500];
        $stratégiques = [
            'mathematicien' => [
                'tier' => 'Rare', 'name' => 'Mathématicien', 'skills' => ['Illumine si chiffre'],
            ],
            'scientifique'  => [
                'tier' => 'Rare', 'name' => 'Scientifique', 'skills' => ['Annule erreur (1x)'],
            ],
            'explorateur'   => [
                'tier' => 'Rare', 'name' => 'Explorateur', 'skills' => ['Voit choix adverse'],
            ],
            'defenseur'     => [
                'tier' => 'Rare', 'name' => 'Défenseur', 'skills' => ['Bloque attaque'],
            ],
            'comedienne'    => [
                'tier' => 'Épique', 'name' => 'Comédienne', 'skills' => ['Score - en MJ', 'Trompe réponse'],
            ],
            'magicienne'    => [
                'tier' => 'Épique', 'name' => 'Magicienne', 'skills' => ['Change Q° (1x)', 'Annule erreur (1x)'],
            ],
            'challenger'    => [
                'tier' => 'Épique', 'name' => 'Challenger', 'skills' => ['Mélange réponses', 'Diminue temps'],
            ],
            'historien'     => [
                'tier' => 'Épique', 'name' => 'Historien', 'skills' => ['Indice texte', '+2s réponse'],
            ],
            'ia-junior'     => [
                'tier' => 'Légendaire', 'name' => 'IA Junior', 'skills' => ['Suggestion IA', 'Élimine 2', 'Rejouer (1x)'],
            ],
            'stratege'      => [
                'tier' => 'Légendaire', 'name' => 'Stratège', 'skills' => ['+20% pièces', 'Créer team', 'Auto skills'],
            ],
            'sprinteur'     => [
                'tier' => 'Légendaire', 'name' => 'Sprinteur', 'skills' => ['Buzzer + rapide', '+3s réflexion', 'Pré-buzzer audio'],
            ],
            'visionnaire'   => [
                'tier' => 'Légendaire', 'name' => 'Visionnaire', 'skills' => ['Voit Q° future', 'Contre Challenger', 'Réponses audio visibles'],
            ],
        ];

        foreach ($stratégiques as $slug => $a) {
            $path = "images/avatars/{$slug}.png"; // fichiers PNG dans public/images/avatars/
            $stratégiques[$slug] = array_merge($a, [
                'slug'  => $slug,
                'path'  => $path,
                'price' => $TIER_PRICE[$a['tier']] ?? 250,
                'quest' => 'Débloquer via boutique',
            ]);
        }

        // ---- Buzzers (audio) dans public/buzzers/
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

        // Assemblage final
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

    /**
     * Liste les images d’un dossier public/<dir> et retourne des chemins relatifs utilisables par asset()
     */
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

