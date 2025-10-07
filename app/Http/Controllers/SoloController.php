<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SoloController extends Controller
{
    public function index(Request $request)
    {
        // Nouveau joueur : démarre à 1 si absent
        if (!session()->has('choix_niveau')) {
            session(['choix_niveau' => 1]);
        }

        $choix_niveau       = session('choix_niveau', 1);            // niveau max débloqué
        $niveau_selectionne = session('niveau_selectionne', $choix_niveau); // par défaut le max débloqué
        $avatar             = session('avatar', 'Aucun');            // avatar optionnel
        $nb_questions       = session('nb_questions', null);

        return view('solo', [
            'choix_niveau'       => $choix_niveau,
            'niveau_selectionne' => $niveau_selectionne,
            'avatar_stratégique'      => $avatar,
            'nb_questions'       => $nb_questions,
        ]);
    }

    public function start(Request $request)
    {
        // Avatar non requis => on ne le valide pas ici
        $validated = $request->validate([
            'nb_questions'  => 'required|integer|min:1',
            'theme'         => 'required|string',
            'niveau_joueur' => 'required|integer|min:1|max:100',
        ]);

        $theme        = $validated['theme'];
        $nbQuestions  = $validated['nb_questions'];
        $niveau       = $validated['niveau_joueur'];

        // Sécurise : ne pas dépasser le niveau débloqué
        $max = session('choix_niveau', 1);
        if ($niveau > $max) $niveau = $max;

        // Persistance session
        session([
            'niveau_selectionne' => $niveau,
            'nb_questions'       => $nbQuestions,
            'theme'              => $theme,
        ]);

        // Avatar vraiment optionnel
        if (!session()->has('avatar') || empty(session('avatar'))) {
            session(['avatar' => 'Aucun']);
        }
        $avatar = session('avatar', 'Aucun');

        // Questions fictives (placeholder)
        $questions = [
            [
                'id' => 1,
                'question_text' => "Combien de pays sont dans l’ONU ?",
                'answers' => [
                    ['id' => 1, 'text' => '201'],
                    ['id' => 2, 'text' => '193'],
                    ['id' => 3, 'text' => '179'],
                    ['id' => 4, 'text' => '101'],
                ],
                'correct_id' => 2,
            ],
        ];

        $themeIcons = [
            'general'    => '🧠',
            'geographie' => '🌐',
            'histoire'   => '📜',
            'art'        => '🎨',
            'cinema'     => '🎬',
            'sport'      => '🏅',
            'cuisine'    => '🍳',
            'faune'      => '🦁',
            'sciences' => '🔬',
        ];

        $bossInfo = $this->getBossForLevel($niveau);
        $playerAvatar = session('selected_avatar', 'default');
        
        // Vérifier conflit d'avatar seulement s'il y a un boss
        $avatarConflict = false;
        if ($bossInfo) {
            // Extraire le nom du boss sans les emojis pour la comparaison
            $bossNameClean = trim(preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $bossInfo['name']));
            
            // Vérifier si l'avatar stratégique du joueur est le même que le boss
            if ($avatar !== 'Aucun' && $avatar === $bossNameClean) {
                $avatarConflict = true;
                $avatar = 'Aucun'; // Reset l'avatar si conflit
                session(['avatar' => 'Aucun']);
            }
        }

        $params = [
            'theme'           => $theme,
            'theme_icon'      => $themeIcons[$theme] ?? '❓',
            'avatar'          => $avatar,
            'avatar_skills'   => $this->getAvatarSkills($avatar),
            'nb_questions'    => $nbQuestions,
            'niveau_joueur'   => $niveau,
            'current'         => 1,
            'question_id'     => $questions[0]['id'],
            'question_text'   => $questions[0]['question_text'],
            'answers'         => $questions[0]['answers'],
            'boss_name'       => $bossInfo['name'] ?? null,
            'boss_avatar'     => $bossInfo['avatar'] ?? null,
            'boss_skills'     => $bossInfo['skills'] ?? [],
            'player_avatar'   => $playerAvatar,
            'avatar_conflict' => $avatarConflict,
            'has_boss'        => $bossInfo !== null,
        ];

        return view('resume', compact('params'));
    }

    public function resume()
    {
        // Récupérer les paramètres de la session ou créer des valeurs par défaut
        $theme = session('theme', 'general');
        $nbQuestions = session('nb_questions', 30);
        $niveau = session('niveau_selectionne', session('choix_niveau', 1));
        $avatar = session('avatar', 'Aucun');
        $playerAvatar = session('selected_avatar', 'default');
        
        $themeIcons = [
            'general'    => '🧠',
            'geographie' => '🌐',
            'histoire'   => '📜',
            'art'        => '🎨',
            'cinema'     => '🎬',
            'sport'      => '🏅',
            'cuisine'    => '🍳',
            'faune'      => '🦁',
            'sciences' => '🔬',
        ];
        
        $bossInfo = $this->getBossForLevel($niveau);
        
        // Vérifier conflit d'avatar seulement s'il y a un boss
        $avatarConflict = false;
        if ($bossInfo) {
            $bossNameClean = trim(preg_replace('/[\x{1F300}-\x{1F9FF}]/u', '', $bossInfo['name']));
            if ($avatar !== 'Aucun' && $avatar === $bossNameClean) {
                $avatarConflict = true;
                $avatar = 'Aucun';
                session(['avatar' => 'Aucun']);
            }
        }
        
        $params = [
            'theme'           => $theme,
            'theme_icon'      => $themeIcons[$theme] ?? '❓',
            'avatar'          => $avatar,
            'avatar_skills'   => $this->getAvatarSkills($avatar),
            'nb_questions'    => $nbQuestions,
            'niveau_joueur'   => $niveau,
            'boss_name'       => $bossInfo['name'] ?? null,
            'boss_avatar'     => $bossInfo['avatar'] ?? null,
            'boss_skills'     => $bossInfo['skills'] ?? [],
            'player_avatar'   => $playerAvatar,
            'avatar_conflict' => $avatarConflict,
            'has_boss'        => $bossInfo !== null,
        ];
        
        return view('resume', compact('params'));
    }

    public function game()
    {
        return view('solo_gameplay');
    }

    public function answer(Request $request)
    {
        // TODO: validation/logique de réponse
        return redirect()->route('solo.game');
    }

    public function stat()
    {
        $data = [
            'score'        => 8,
            'total'        => 10,
            'pourcentage'  => 80,
            'niveau'       => session('niveau_selectionne', '?'),
            'theme'        => session('theme', '?'),
            'avatar'       => session('avatar', 'Aucun'),
        ];

        return view('stat', compact('data'));
    }

    private function getAvatarSkills($avatar)
    {
        $skills = [
            'Aucun' => [],
            
            // Rare 🎯
            'Mathématicien' => [
                'Peut faire illuminer une bonne réponse si il y a un chiffre dans la réponse'
            ],
            'Scientifique' => [
                'Peut acidifier une mauvaise réponse 1 fois avant de choisir'
            ],
            'Explorateur' => [
                'La réponse s\'illumine du choix du joueur adverse ou la réponse la plus cliqué'
            ],
            'Défenseur' => [
                'Peut annuler une attaque de n\'importe quel Avatar'
            ],
            
            // Épique ⭐
            'Comédien' => [
                'Peut indiquer un score moins élevé jusqu\'à la fin de la partie (maître du jeu)',
                'Capacité de tromper les joueurs sur une bonne réponse en mauvaise réponse'
            ],
            'Magicien' => [
                'Peut avoir une question bonus par partie',
                'Peut annuler une mauvaise en réponse non buzzer 1 fois par partie'
            ],
            'Challenger' => [
                'Fait changer les réponses des participants d\'emplacement au 2 sec',
                'Diminue aux autres joueurs leur compte à rebours'
            ],
            'Historien' => [
                'Voit un indice texte avant les autres',
                '1 fois 2 sec de plus pour répondre'
            ],
            
            // Légendaire 👑
            'IA Junior' => [
                'Voit une suggestion IA qui illumine pour la réponse 1 fois',
                'Peut éliminer 2 mauvaises réponses sur les 4',
                'Peut reprendre une réponse 1 fois'
            ],
            'Stratège' => [
                'Gagne +20% de pièces d\'intelligence sur une victoire',
                'Peut créer un team (Ajouter 1 Avatar rare) en mode solo',
                'Réduit le coût de déblocage des Avatars stratégiques de 10%'
            ],
            'Sprinteur' => [
                'Peut reculer son temps de buzzer jusqu\'à 0.5s du plus rapide',
                'Peut utiliser 3 secondes de réflexion de plus 1 fois',
                'Après chaque niveau se réactivent automatiquement'
            ],
            'Visionnaire' => [
                'Peut voir 5 questions "future" (prochaine question révélée en avance 5 fois)',
                'Peut contrer l\'attaque du Challenger',
                'Si 2 points dans une manche, seule la bonne réponse est sélectionnable'
            ],
        ];
        return $skills[$avatar] ?? [];
    }

    private function getBossForLevel($niveau)
    {
        // Pas de boss avant le niveau 10
        if ($niveau < 10) {
            return null;
        }
        
        // Bosses = Épiques (⭐) et Légendaires (👑) uniquement
        $bosses = [
            // Épiques ⭐ (niveaux 10-40)
            10 => ['name' => '🎭 Comédien', 'avatar' => 'images/avatars/comedienne.png', 'skills' => $this->getAvatarSkills('Comédien')],
            20 => ['name' => '🧙‍♂️ Magicien', 'avatar' => 'images/avatars/magicienne.png', 'skills' => $this->getAvatarSkills('Magicien')],
            30 => ['name' => '🔥 Challenger', 'avatar' => 'images/avatars/challenger.png', 'skills' => $this->getAvatarSkills('Challenger')],
            40 => ['name' => '📚 Historien', 'avatar' => 'images/avatars/historien.png', 'skills' => $this->getAvatarSkills('Historien')],
            
            // Légendaires 👑 (niveaux 50-100)
            50 => ['name' => '🤖 IA Junior', 'avatar' => 'images/avatars/ia-junior.png', 'skills' => $this->getAvatarSkills('IA Junior')],
            60 => ['name' => '🏆 Stratège', 'avatar' => 'images/avatars/stratege.png', 'skills' => $this->getAvatarSkills('Stratège')],
            70 => ['name' => '⚡ Sprinteur', 'avatar' => 'images/avatars/sprinteur.png', 'skills' => $this->getAvatarSkills('Sprinteur')],
            80 => ['name' => '🌟 Visionnaire', 'avatar' => 'images/avatars/visionnaire.png', 'skills' => $this->getAvatarSkills('Visionnaire')],
            
            // Répétition des Légendaires pour niveaux supérieurs (difficulté croissante)
            90 => ['name' => '🏆 Stratège Maître', 'avatar' => 'images/avatars/stratege.png', 'skills' => $this->getAvatarSkills('Stratège')],
            100 => ['name' => '🌟 Visionnaire Suprême', 'avatar' => 'images/avatars/visionnaire.png', 'skills' => $this->getAvatarSkills('Visionnaire')],
        ];

        // Trouver le boss correspondant au niveau (arrondi à la dizaine inférieure)
        $bossLevel = floor($niveau / 10) * 10;
        if ($bossLevel < 10) $bossLevel = 10;
        if ($bossLevel > 100) $bossLevel = 100;

        return $bosses[$bossLevel] ?? null;
    }
}
