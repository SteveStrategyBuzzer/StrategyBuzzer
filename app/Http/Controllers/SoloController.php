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
            'boss_name'       => $bossInfo['name'],
            'boss_avatar'     => $bossInfo['avatar'],
            'boss_skills'     => $bossInfo['skills'],
            'player_avatar'   => $playerAvatar,
        ];

        return view('resume', compact('params'));
    }

    public function resume()
    {
        $params = session('params', []);
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
            'Aucun'         => [],
            'Mathématicien' => ['Rejouer 1 fois', 'Analyse rapide', 'Double points'],
            'Scientifique'  => ['Éliminer 1 mauvaise réponse', 'Indice logique'],
            'Explorateur'   => ['+5 sec', 'Carte bonus'],
        ];
        return $skills[$avatar] ?? [];
    }

    private function getBossForLevel($niveau)
    {
        $bosses = [
            1  => ['name' => 'Robot Débutant', 'avatar' => 'images/avatars/ia-junior.png', 'skills' => ['Réflexion basique']],
            10 => ['name' => 'Challenger', 'avatar' => 'images/avatars/challenger.png', 'skills' => ['Analyse rapide', 'Contre-attaque']],
            20 => ['name' => 'Stratège', 'avatar' => 'images/avatars/stratege.png', 'skills' => ['Tactique avancée', 'Prédiction']],
            30 => ['name' => 'Visionnaire', 'avatar' => 'images/avatars/visionnaire.png', 'skills' => ['Anticipation', 'Double chance']],
            40 => ['name' => 'Sprinteur', 'avatar' => 'images/avatars/sprinteur.png', 'skills' => ['Vitesse accrue', 'Temps réduit']],
            50 => ['name' => 'Historien', 'avatar' => 'images/avatars/historien.png', 'skills' => ['Connaissance étendue', 'Indices historiques']],
            60 => ['name' => 'Comédienne', 'avatar' => 'images/avatars/comedienne.png', 'skills' => ['Distraction', 'Fausse réponse']],
            70 => ['name' => 'Magicienne', 'avatar' => 'images/avatars/magicienne.png', 'skills' => ['Illusion', 'Disparition de réponse']],
            80 => ['name' => 'Défenseur', 'avatar' => 'images/avatars/defenseur.png', 'skills' => ['Bouclier', 'Annulation d\'attaque']],
            90 => ['name' => 'Scientifique Suprême', 'avatar' => 'images/avatars/scientifique.png', 'skills' => ['Acidification', 'Analyse moléculaire']],
            100 => ['name' => 'Le Cerveau Ultime', 'avatar' => 'images/avatars/mathematicien.png', 'skills' => ['Calcul instantané', 'Omniscience', 'Manipulation du temps']],
        ];

        // Trouver le boss correspondant au niveau (arrondi à la dizaine inférieure)
        $bossLevel = floor($niveau / 10) * 10;
        if ($bossLevel < 1) $bossLevel = 1;
        if ($bossLevel > 100) $bossLevel = 100;

        return $bosses[$bossLevel] ?? $bosses[1];
    }
}
