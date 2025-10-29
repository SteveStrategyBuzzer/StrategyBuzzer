<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\QuestService;
use App\Services\StatisticsService;

class SoloController extends Controller
{
    public function index(Request $request)
    {
        // Restaurer le niveau depuis profile_settings pour les utilisateurs authentifiés
        $user = auth()->user();
        if ($user) {
            $settings = (array) ($user->profile_settings ?? []);
            $savedLevel = (int) data_get($settings, 'gm.solo_level', 1);
            
            // Si le niveau sauvegardé est supérieur au niveau en session, utiliser le niveau sauvegardé
            if ($savedLevel > session('choix_niveau', 1)) {
                session(['choix_niveau' => $savedLevel]);
            }
        }
        
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
        // Vérifier que le joueur a des vies disponibles (sauf pour les invités)
        $user = auth()->user();
        $lifeService = new \App\Services\LifeService();
        
        if ($user && !$lifeService->hasLivesAvailable($user)) {
            return redirect()->route('menu')->with('error', 'Vous n\'avez plus de vies disponibles. Revenez plus tard !');
        }
        
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

        // NOUVEAU SYSTÈME : Best of 3 manches
        // Une manche = TOUTES les questions sélectionnées
        // Gagner 2 manches sur 3 pour gagner la partie
        
        // Persistance session - initialiser TOUTES les variables de jeu
        session([
            'niveau_selectionne' => $niveau,
            'nb_questions'       => $nbQuestions,
            'theme'              => $theme,
            'current_question_number' => 1,
            'current_round' => 1,              // Manche actuelle (1, 2 ou 3)
            'player_rounds_won' => 0,          // Manches gagnées par le joueur
            'opponent_rounds_won' => 0,        // Manches gagnées par l'adversaire
            'score' => 0,                      // Score de la manche actuelle
            'opponent_score' => 0,             // Score adversaire de la manche actuelle
            'answered_questions' => [],
            'used_question_ids' => [],
            'current_question' => null,        // Sera généré au premier game()
            'global_stats' => [],              // Statistiques globales toutes manches
            'match_result_processed' => false, // Réinitialiser le flag pour nouvelle partie
            'used_skills' => [],               // Tracking des skills utilisés (persistant pour toute la partie)
        ]);

        // Avatar vraiment optionnel - tenter de restaurer depuis profile_settings
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!session()->has('avatar') || empty(session('avatar'))) {
            if ($user) {
                $settings = (array) ($user->profile_settings ?? []);
                $strategicName = (string) data_get($settings, 'strategic_avatar.name', '');
                session(['avatar' => $strategicName ?: 'Aucun']);
            } else {
                session(['avatar' => 'Aucun']);
            }
        }
        
        // Synchroniser l'avatar joueur depuis profile_settings (normaliser 'default' et valeurs vides)
        $currentAvatar = session('selected_avatar', '');
        // Normaliser les anciennes valeurs 'default' ou vides
        if (!$currentAvatar || $currentAvatar === 'default') {
            if ($user) {
                $settings = (array) ($user->profile_settings ?? []);
                $playerAvatarUrl = (string) data_get($settings, 'avatar.url', '');
                // Normaliser aussi 'default' dans profile_settings
                if ($playerAvatarUrl && $playerAvatarUrl !== 'default') {
                    session(['selected_avatar' => $playerAvatarUrl]);
                } else {
                    // Utiliser standard1 comme avatar par défaut
                    session(['selected_avatar' => 'images/avatars/standard/standard1.png']);
                }
            } else {
                // Invités : utiliser standard1 comme avatar par défaut
                session(['selected_avatar' => 'images/avatars/standard/standard1.png']);
            }
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
        $opponentInfo = $this->getOpponentInfo($niveau);
        $playerAvatar = session('selected_avatar', 'images/avatars/standard/standard1.png');
        
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
            'opponent_info'   => $opponentInfo,
            'player_avatar'   => $playerAvatar,
            'avatar_conflict' => $avatarConflict,
            'has_boss'        => $bossInfo !== null,
        ];

        return view('resume', compact('params'));
    }

    public function resume()
    {
        // Synchroniser l'avatar stratégique depuis profile_settings si absent ou 'Aucun'
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && (!session()->has('avatar') || session('avatar') === 'Aucun')) {
            $settings = (array) ($user->profile_settings ?? []);
            $strategicName = (string) data_get($settings, 'strategic_avatar.name', '');
            if ($strategicName) {
                session(['avatar' => $strategicName]);
            }
        }
        
        // Synchroniser l'avatar joueur depuis profile_settings (normaliser 'default' et valeurs vides)
        $currentAvatar = session('selected_avatar', '');
        // Normaliser les anciennes valeurs 'default' ou vides
        if (!$currentAvatar || $currentAvatar === 'default') {
            if ($user) {
                $settings = (array) ($user->profile_settings ?? []);
                $playerAvatarUrl = (string) data_get($settings, 'avatar.url', '');
                // Normaliser aussi 'default' dans profile_settings
                if ($playerAvatarUrl && $playerAvatarUrl !== 'default') {
                    session(['selected_avatar' => $playerAvatarUrl]);
                } else {
                    // Utiliser standard1 comme avatar par défaut
                    session(['selected_avatar' => 'images/avatars/standard/standard1.png']);
                }
            } else {
                // Invités : utiliser standard1 comme avatar par défaut
                session(['selected_avatar' => 'images/avatars/standard/standard1.png']);
            }
        }
        
        // Récupérer les paramètres de la session ou créer des valeurs par défaut
        $theme = session('theme', 'general');
        $nbQuestions = session('nb_questions', 30);
        $niveau = session('niveau_selectionne', session('choix_niveau', 1));
        $avatar = session('avatar', 'Aucun');
        $playerAvatar = session('selected_avatar', 'images/avatars/standard/standard1.png');
        
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
        $opponentInfo = $this->getOpponentInfo($niveau);
        
        // DEBUG TEMPORAIRE
        \Illuminate\Support\Facades\Log::info('RESUME DEBUG - Niveau: ' . $niveau);
        \Illuminate\Support\Facades\Log::info('RESUME DEBUG - OpponentInfo: ' . json_encode($opponentInfo));
        
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
        
        // Construire le chemin de l'image de l'avatar stratégique
        $strategicAvatarPath = '';
        if ($avatar !== 'Aucun') {
            $strategicAvatarSlug = strtolower($avatar);
            $strategicAvatarSlug = str_replace(['é', 'è', 'ê'], 'e', $strategicAvatarSlug);
            $strategicAvatarSlug = str_replace(['à', 'â'], 'a', $strategicAvatarSlug);
            $strategicAvatarSlug = str_replace(' ', '-', $strategicAvatarSlug);
            $strategicAvatarPath = 'images/avatars/' . $strategicAvatarSlug . '.png';
        }
        
        $params = [
            'theme'           => $theme,
            'theme_icon'      => $themeIcons[$theme] ?? '❓',
            'avatar'          => $avatar,
            'avatar_image'    => $strategicAvatarPath,
            'avatar_skills'   => $this->getAvatarSkills($avatar),
            'nb_questions'    => $nbQuestions,
            'niveau_joueur'   => $niveau,
            'boss_name'       => $bossInfo['name'] ?? null,
            'boss_avatar'     => $bossInfo['avatar'] ?? null,
            'boss_skills'     => $bossInfo['skills'] ?? [],
            'opponent_info'   => $opponentInfo,
            'player_avatar'   => $playerAvatar,
            'avatar_conflict' => $avatarConflict,
            'has_boss'        => $bossInfo !== null,
        ];
        
        return view('resume', compact('params'));
    }

    public function prepare()
    {
        // Simple méthode qui affiche juste l'écran de préparation
        // Le compte à rebours est géré par JavaScript dans la vue
        return view('game_preparation');
    }

    public function game()
    {
        // Synchroniser l'avatar stratégique depuis profile_settings si absent ou 'Aucun'
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && (!session()->has('avatar') || session('avatar') === 'Aucun')) {
            $settings = (array) ($user->profile_settings ?? []);
            $strategicName = (string) data_get($settings, 'strategic_avatar.name', '');
            if ($strategicName) {
                session(['avatar' => $strategicName]);
            }
        }
        
        // Synchroniser l'avatar joueur depuis profile_settings (normaliser 'default' et valeurs vides)
        $currentAvatar = session('selected_avatar', '');
        // Normaliser les anciennes valeurs 'default' ou vides
        if (!$currentAvatar || $currentAvatar === 'default') {
            if ($user) {
                $settings = (array) ($user->profile_settings ?? []);
                $playerAvatarUrl = (string) data_get($settings, 'avatar.url', '');
                // Normaliser aussi 'default' dans profile_settings
                if ($playerAvatarUrl && $playerAvatarUrl !== 'default') {
                    session(['selected_avatar' => $playerAvatarUrl]);
                } else {
                    // Utiliser standard1 comme avatar par défaut
                    session(['selected_avatar' => 'images/avatars/standard/standard1.png']);
                }
            } else {
                // Invités : utiliser standard1 comme avatar par défaut
                session(['selected_avatar' => 'images/avatars/standard/standard1.png']);
            }
        }
        
        $questionService = new \App\Services\QuestionService();
        
        // Récupérer les paramètres de session
        $theme = session('theme', 'general');
        $nbQuestions = session('nb_questions', 30);
        $niveau = session('niveau_selectionne', 1);
        $avatar = session('avatar', 'Aucun');
        $currentQuestion = session('current_question_number', 1);
        $usedQuestionIds = session('used_question_ids', []);
        
        // Générer la question SEULEMENT si elle n'existe pas déjà (première visite ou après nextQuestion)
        if (!session()->has('current_question') || session('current_question') === null) {
            $question = $questionService->generateQuestion($theme, $niveau, $currentQuestion, $usedQuestionIds);
            session(['current_question' => $question]);
            
            // Ajouter l'ID de la question aux questions utilisées
            $usedQuestionIds[] = $question['id'];
            session(['used_question_ids' => $usedQuestionIds]);
        } else {
            $question = session('current_question');
        }
        
        // Calculer le temps de chrono de base (4-8 secondes selon niveau)
        $baseTime = max(4, 8 - floor($niveau / 10));
        
        // Initialiser le timer SEULEMENT si pas déjà commencé (évite reset si on revient)
        if (!session()->has('question_start_time')) {
            session(['question_start_time' => time()]);
            session(['chrono_time' => $baseTime]);
        }
        
        // Récupérer les informations complètes de l'adversaire
        $opponentInfo = $this->getOpponentInfo($niveau);
        
        $params = [
            'question' => $question,
            'current_question' => $currentQuestion,
            'total_questions' => $nbQuestions,
            'score' => session('score', 0),
            'opponent_score' => session('opponent_score', 0),
            'chrono_time' => $baseTime,
            'avatar' => $avatar,
            'theme' => $theme,
            'niveau' => $niveau,
            'current_round' => session('current_round', 1),
            'total_rounds' => session('total_rounds', 5),
            'opponent_info' => $opponentInfo,
        ];
        
        return view('game_question', compact('params'));
    }

    public function buzz(Request $request)
    {
        // Enregistrer le temps de buzz
        $buzzTime = time() - session('question_start_time');
        session(['buzz_time' => $buzzTime]);
        session(['buzzed' => true]);
        
        return $this->renderAnswerView(true, $buzzTime);
    }
    
    public function useSkill(Request $request)
    {
        $skillName = $request->input('skill_name');
        
        // Ajouter le skill à la liste des skills utilisés (persistant pour toute la partie)
        $usedSkills = session('used_skills', []);
        if (!in_array($skillName, $usedSkills)) {
            $usedSkills[] = $skillName;
            session(['used_skills' => $usedSkills]);
        }
        
        return response()->json(['success' => true, 'used_skills' => $usedSkills]);
    }
    
    private function renderAnswerView($playerBuzzed, $buzzTime = null)
    {
        // Récupérer la question actuelle
        $question = session('current_question');
        $currentQuestion = session('current_question_number');
        $nbQuestions = session('nb_questions', 30);
        $avatar = session('avatar', 'Aucun');
        
        // Calculer temps pour répondre (10 secondes de base)
        $answerTime = 10;
        
        $params = [
            'question' => $question,
            'current_question' => $currentQuestion,
            'total_questions' => $nbQuestions,
            'score' => session('score', 0),
            'answer_time' => $answerTime,
            'buzz_time' => $buzzTime,
            'player_buzzed' => $playerBuzzed,
            'current_round' => session('current_round', 1),
            'total_rounds' => session('total_rounds', 5),
            'avatar' => $avatar,  // Avatar stratégique pour les skills
            'avatar_skills' => $this->getAvatarSkills($avatar),  // Skills de l'avatar
            'used_skills' => session('used_skills', []),  // Skills déjà utilisés dans la partie
            'correct_index' => $question['correct_index'] ?? -1,  // Index de la bonne réponse pour les sons
        ];
        
        return view('game_answer', compact('params'));
    }

    public function answer(Request $request)
    {
        $questionService = new \App\Services\QuestionService();
        
        $answerIndex = (int) $request->input('answer_index');
        $question = session('current_question');
        $niveau = session('niveau_selectionne', 1);
        
        // Vérifier si le joueur a buzzé
        $playerBuzzed = session('buzzed', false);
        
        // Récupérer le temps de buzz et le temps du chrono
        $buzzTime = session('buzz_time', 0);
        $chronoTime = session('chrono_time', 8);
        
        // Vérifier la réponse du joueur
        $isCorrect = $questionService->checkAnswer($question, $answerIndex);
        
        // Simuler le comportement complet de l'adversaire IA (passer timing du buzz)
        $opponentBehavior = $questionService->simulateOpponentBehavior($niveau, $question, $playerBuzzed, $buzzTime, $chronoTime);
        
        // Calculer les points du joueur selon les nouvelles règles
        $playerPoints = 0;
        
        if ($playerBuzzed) {
            // Le joueur a buzzé
            if ($isCorrect) {
                // Le joueur est 2ème (+1 pt) SEULEMENT si l'adversaire est plus rapide ET a répondu correctement
                // Sinon le joueur est 1er (+2 pts)
                $playerPoints = ($opponentBehavior['is_faster'] && $opponentBehavior['is_correct']) ? 1 : 2;
            } else {
                // Mauvaise réponse = -2 pts
                $playerPoints = -2;
            }
        } else {
            // Le joueur n'a PAS buzzé mais répond quand même = 0 points (ni gain ni perte)
            $playerPoints = 0;
        }
        
        // Mettre à jour les scores
        $currentScore = session('score', 0);
        $currentOpponentScore = session('opponent_score', 0);
        
        session(['score' => $currentScore + $playerPoints]);
        session(['opponent_score' => $currentOpponentScore + $opponentBehavior['points']]);
        
        // Sauvegarder la réponse avec détails complets
        $answeredQuestions = session('answered_questions', []);
        $answeredQuestions[] = [
            'question_id' => $question['id'],
            'answer_index' => $answerIndex,
            'is_correct' => $isCorrect,
            'player_points' => $playerPoints,
            'opponent_buzzed' => $opponentBehavior['buzzes'],
            'opponent_faster' => $opponentBehavior['is_faster'],
            'opponent_correct' => $opponentBehavior['is_correct'],
            'opponent_points' => $opponentBehavior['points'],
            'player_buzzed' => $playerBuzzed,
        ];
        session(['answered_questions' => $answeredQuestions]);
        
        // Ajouter aux statistiques globales (toutes manches confondues)
        $globalStats = session('global_stats', []);
        $globalStats[] = [
            'is_correct' => $isCorrect,
            'player_buzzed' => $playerBuzzed,
            'round' => session('current_round', 1),
        ];
        session(['global_stats' => $globalStats]);
        
        // Vérifier et compléter les quêtes (si connecté)
        $user = auth()->user();
        if ($user && $isCorrect && $playerBuzzed) {
            $questService = new QuestService();
            
            // Quête : Réponses rapides (fast_answers_10)
            // Si le joueur a répondu rapidement (< 2 secondes)
            if ($buzzTime < 2) {
                $questService->checkAndCompleteQuests($user, 'fast_answers_10', [
                    'answer_time' => $buzzTime,
                ]);
            }
            
            // Quête : Buzz rapides (buzz_fast_10)
            // Si le joueur a buzzé rapidement (< 3 secondes)
            if ($buzzTime < 3) {
                $questService->checkAndCompleteQuests($user, 'buzz_fast_10', [
                    'buzz_time' => $buzzTime,
                ]);
            }
        }
        
        // Calculer les données de progression avec valeurs par défaut sécurisées
        $currentQuestion = session('current_question_number', 1);
        $nbQuestions = session('nb_questions', 30);
        $viesRestantes = session('vies_restantes', 3);
        $skillsRestants = session('skills_restants', 3);
        
        // Calculer pourcentage avec protection contre division par zéro
        $questionsRepondues = max(0, $currentQuestion - 1);
        $pourcentage = $nbQuestions > 0 ? round(($questionsRepondues / $nbQuestions) * 100) : 0;
        $questionsRestantes = max(0, $nbQuestions - $questionsRepondues);
        
        // Position (niveau >= 70) avec scores actuels
        $currentScore = session('score', 0);
        $currentOpponentScore = session('opponent_score', 0);
        $showPosition = $niveau >= 70;
        $position = $showPosition ? ($currentScore >= $currentOpponentScore ? 1 : 2) : null;
        
        // Calculer les statistiques globales (toutes manches confondues)
        $globalStats = session('global_stats', []);
        $totalCorrect = 0;
        $totalIncorrect = 0;
        $totalUnanswered = 0;
        
        foreach ($globalStats as $stat) {
            if (!$stat['player_buzzed']) {
                $totalUnanswered++;
            } elseif ($stat['is_correct']) {
                $totalCorrect++;
            } else {
                $totalIncorrect++;
            }
        }
        
        $totalQuestions = count($globalStats);
        $globalEfficiency = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100) : 0;

        $params = [
            'question' => $question,
            'answer_index' => $answerIndex,
            'is_correct' => $isCorrect,
            'current_question' => $currentQuestion,
            'total_questions' => $nbQuestions,
            'score' => session('score', 0),
            'opponent_score' => session('opponent_score', 0),
            // Nouvelles données selon arborescence point 8
            'niveau' => $niveau,
            'vies_restantes' => $viesRestantes,
            'skills_restants' => $skillsRestants,
            'pourcentage' => $pourcentage,
            'questions_restantes' => $questionsRestantes,
            'show_position' => $showPosition,
            'position' => $position,
            // Données du nouveau système de pointage
            'player_points' => $playerPoints,
            'opponent_buzzed' => $opponentBehavior['buzzes'],
            'opponent_faster' => $opponentBehavior['is_faster'],
            'opponent_correct' => $opponentBehavior['is_correct'],
            'opponent_points' => $opponentBehavior['points'],
            // Données de manche (Best of 3)
            'current_round' => session('current_round', 1),
            'player_rounds_won' => session('player_rounds_won', 0),
            'opponent_rounds_won' => session('opponent_rounds_won', 0),
            // Statistiques globales (toutes manches)
            'total_correct' => $totalCorrect,
            'total_incorrect' => $totalIncorrect,
            'total_unanswered' => $totalUnanswered,
            'total_questions_played' => $totalQuestions,
            'global_efficiency' => $globalEfficiency,
            'theme' => session('theme', 'Général'),
        ];
        
        return view('game_result', compact('params'));
    }

    public function timeout()
    {
        // Le joueur n'a pas buzzé à temps - marquer qu'il n'a pas buzzé
        session(['buzzed' => false]);
        
        // Afficher la page de réponse pour permettre au joueur de répondre quand même
        return $this->renderAnswerView(false, null);
    }

    public function nextQuestion()
    {
        // Récupérer les données de session
        $currentQuestion = session('current_question_number', 1);
        $nbQuestions = session('nb_questions', 30);
        
        // SYSTÈME BEST OF 3 : Vérifier si la manche est terminée
        if ($currentQuestion >= $nbQuestions) {
            // Fin de la manche - déterminer le gagnant de la manche
            $playerScore = session('score', 0);
            $opponentScore = session('opponent_score', 0);
            
            $playerRoundsWon = session('player_rounds_won', 0);
            $opponentRoundsWon = session('opponent_rounds_won', 0);
            
            // Qui a gagné cette manche ?
            if ($playerScore > $opponentScore) {
                $playerRoundsWon++;
            } else {
                $opponentRoundsWon++;
            }
            
            session([
                'player_rounds_won' => $playerRoundsWon,
                'opponent_rounds_won' => $opponentRoundsWon,
            ]);
            
            // Vérifier si quelqu'un a gagné la partie (2 manches sur 3)
            if ($playerRoundsWon >= 2 || $opponentRoundsWon >= 2) {
                // FIN DE LA PARTIE - rediriger vers victoire ou défaite
                if ($playerRoundsWon >= 2) {
                    // VICTOIRE - Débloquer le niveau suivant
                    $currentChoixNiveau = session('choix_niveau', 1);
                    $newChoixNiveau = min($currentChoixNiveau + 1, 100); // Maximum niveau 100
                    
                    // Mettre à jour la session
                    session(['choix_niveau' => $newChoixNiveau]);
                    
                    // Sauvegarder dans profile_settings pour les utilisateurs authentifiés
                    $user = auth()->user();
                    if ($user instanceof \Illuminate\Database\Eloquent\Model) {
                        $settings = (array) ($user->profile_settings ?? []);
                        
                        // Initialiser 'gm' si absent
                        if (!isset($settings['gm'])) {
                            $settings['gm'] = [];
                        }
                        
                        // Mettre à jour le niveau solo
                        $settings['gm']['solo_level'] = $newChoixNiveau;
                        
                        // Calculer l'XP et les statistiques de progression
                        $currentXP = (int) data_get($settings, 'gm.xp', 0);
                        $totalVictories = (int) data_get($settings, 'gm.total_victories', 0);
                        
                        // Ajouter XP basé sur le niveau (plus le niveau est élevé, plus on gagne d'XP)
                        $xpGained = 50 + ($currentChoixNiveau * 10); // 50 base + 10 par niveau
                        $settings['gm']['xp'] = $currentXP + $xpGained;
                        $settings['gm']['total_victories'] = $totalVictories + 1;
                        $settings['gm']['last_victory_date'] = now()->toDateTimeString();
                        
                        $user->profile_settings = $settings;
                        $user->save();
                    }
                    
                    // Marquer le flag pour éviter déduction multiple
                    session(['match_result_processed' => true]);
                    return redirect()->route('solo.victory');
                } else {
                    // DÉFAITE - déduire une vie UNE SEULE FOIS et sauvegarder les statistiques
                    if (!session('match_result_processed')) {
                        $user = auth()->user();
                        
                        // Déduire la vie
                        $lifeService = new \App\Services\LifeService();
                        $lifeService->deductLife($user);
                        
                        // Sauvegarder les statistiques de défaite
                        if ($user instanceof \Illuminate\Database\Eloquent\Model) {
                            $settings = (array) ($user->profile_settings ?? []);
                            
                            // Initialiser 'gm' si absent
                            if (!isset($settings['gm'])) {
                                $settings['gm'] = [];
                            }
                            
                            $totalDefeats = (int) data_get($settings, 'gm.total_defeats', 0);
                            $settings['gm']['total_defeats'] = $totalDefeats + 1;
                            $settings['gm']['last_defeat_date'] = now()->toDateTimeString();
                            
                            $user->profile_settings = $settings;
                            $user->save();
                        }
                        
                        // Marquer le flag pour éviter déduction multiple
                        session(['match_result_processed' => true]);
                    }
                    return redirect()->route('solo.defeat');
                }
            }
            
            // Calculer l'efficacité de la manche qui vient de se terminer
            $answeredQuestions = session('answered_questions', []);
            $correctAnswers = 0;
            foreach ($answeredQuestions as $answer) {
                if ($answer['is_correct']) {
                    $correctAnswers++;
                }
            }
            $roundEfficiency = count($answeredQuestions) > 0 ? round(($correctAnswers / count($answeredQuestions)) * 100) : 0;
            
            // Sauvegarder les infos de la manche pour la page de résultat
            $currentRound = session('current_round', 1);
            $niveau = session('niveau_selectionne', 1);
            $viesRestantes = session('vies_restantes', 3);
            
            session([
                'last_round_efficiency' => $roundEfficiency,
                'last_round_player_score' => $playerScore,
                'last_round_opponent_score' => $opponentScore,
                'current_round' => $currentRound + 1,
                'current_question_number' => 1,  // Recommencer à la question 1
                'score' => 0,                     // Réinitialiser les scores
                'opponent_score' => 0,
                'answered_questions' => [],
                'used_question_ids' => [],       // Réinitialiser les questions utilisées
            ]);
            
            // Rediriger vers une page de transition de manche
            return redirect()->route('solo.round-result');
        }
        
        // Continuer dans la manche actuelle
        session(['current_question_number' => $currentQuestion + 1]);
        
        // Nettoyer la question actuelle pour forcer une nouvelle génération
        session()->forget('current_question');
        session()->forget('question_start_time');
        session()->forget('chrono_time');
        session()->forget('buzzed');
        session()->forget('buzz_time');
        
        return redirect()->route('solo.game');
    }

    public function roundResult()
    {
        $currentRound = session('current_round', 1);
        $playerRoundsWon = session('player_rounds_won', 0);
        $opponentRoundsWon = session('opponent_rounds_won', 0);
        $niveau = session('niveau_selectionne', 1);
        $viesRestantes = session('vies_restantes', 3);
        $roundEfficiency = session('last_round_efficiency', 0);
        $playerScore = session('last_round_player_score', 0);
        $opponentScore = session('last_round_opponent_score', 0);
        $theme = session('theme', 'Général');
        $avatar = session('avatar', 'Aucun');
        
        // Calculer les statistiques globales (toutes manches confondues)
        $globalStats = session('global_stats', []);
        $totalCorrect = 0;
        $totalIncorrect = 0;
        $totalUnanswered = 0;
        
        foreach ($globalStats as $stat) {
            if (!$stat['player_buzzed']) {
                $totalUnanswered++;
            } elseif ($stat['is_correct']) {
                $totalCorrect++;
            } else {
                $totalIncorrect++;
            }
        }
        
        $totalQuestionsPlayed = count($globalStats);
        $globalEfficiency = $totalQuestionsPlayed > 0 ? round(($totalCorrect / $totalQuestionsPlayed) * 100) : 0;
        
        // VÉRIFIER SI LA PARTIE EST TERMINÉE (best of 3: premier à 2 manches gagnées)
        if ($playerRoundsWon >= 2) {
            // VICTOIRE DU JOUEUR - Débloquer le niveau suivant
            $currentLevel = session('choix_niveau', 1);
            $newLevel = min($currentLevel + 1, 100); // Maximum niveau 100
            
            // Sauvegarder dans la session
            session(['choix_niveau' => $newLevel]);
            
            // Sauvegarder dans profile_settings si utilisateur connecté
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user && $user instanceof \App\Models\User) {
                $settings = (array) ($user->profile_settings ?? []);
                $settings['gm'] = $settings['gm'] ?? [];
                $settings['gm']['solo_level'] = $newLevel;
                $user->profile_settings = $settings;
                $user->save();
            }
            
            // Rediriger vers la page de victoire
            return redirect()->route('solo.victory');
        } elseif ($opponentRoundsWon >= 2) {
            // DÉFAITE DU JOUEUR - Rediriger vers une page de défaite
            return redirect()->route('solo.defeat');
        }
        
        // Sinon, afficher le résultat de la manche et continuer
        $params = [
            'round_number' => $currentRound - 1,  // La manche qui vient de se terminer
            'next_round' => $currentRound,         // La prochaine manche
            'player_rounds_won' => $playerRoundsWon,
            'opponent_rounds_won' => $opponentRoundsWon,
            'nb_questions' => session('nb_questions', 30),
            'niveau_adversaire' => $niveau,        // Niveau de l'adversaire
            'vies_restantes' => $viesRestantes,    // Vies restantes
            'round_efficiency' => $roundEfficiency, // % efficacité manche
            'player_score' => $playerScore,        // Score joueur manche
            'opponent_score' => $opponentScore,    // Score adversaire manche
            'theme' => $theme,                     // Thème joué
            'avatar' => $avatar,                   // Avatar stratégique
            // Statistiques globales
            'total_correct' => $totalCorrect,
            'total_incorrect' => $totalIncorrect,
            'total_unanswered' => $totalUnanswered,
            'total_questions_played' => $totalQuestionsPlayed,
            'global_efficiency' => $globalEfficiency,
        ];
        
        return view('round_result', compact('params'));
    }

    public function victory()
    {
        $currentLevel = session('niveau_selectionne', 1);
        $newLevel = session('choix_niveau', 1);
        $theme = session('theme', 'Général');
        
        // Calculer les statistiques globales finales
        $globalStats = session('global_stats', []);
        $totalCorrect = 0;
        $totalIncorrect = 0;
        $totalUnanswered = 0;
        
        foreach ($globalStats as $stat) {
            if (!$stat['player_buzzed']) {
                $totalUnanswered++;
            } elseif ($stat['is_correct']) {
                $totalCorrect++;
            } else {
                $totalIncorrect++;
            }
        }
        
        $totalQuestionsPlayed = count($globalStats);
        $globalEfficiency = $totalQuestionsPlayed > 0 ? round(($totalCorrect / $totalQuestionsPlayed) * 100) : 0;
        
        // Vérifier et compléter les quêtes
        $user = auth()->user();
        if ($user) {
            $questService = new QuestService();
            
            // Quête : Première partie de 10 questions
            $questService->checkAndCompleteQuests($user, 'first_match_10q', [
                'match_completed' => true,
                'total_questions' => $totalQuestionsPlayed,
            ]);
            
            // Quête : Score parfait
            if ($totalCorrect == $totalQuestionsPlayed && $totalQuestionsPlayed >= 10) {
                $questService->checkAndCompleteQuests($user, 'perfect_score', [
                    'user_correct_answers' => $totalCorrect,
                    'total_questions' => $totalQuestionsPlayed,
                ]);
            }
        }
        
        // Enregistrer les statistiques de match (victoire)
        $matchStats = null;
        $statsMetrics = null;
        if ($user) {
            $statsService = new StatisticsService();
            $matchData = $this->calculateMatchStatistics();
            
            $gameId = 'solo_' . $currentLevel . '_' . time();
            $matchStats = $statsService->recordMatchStatistics(
                $user->id,
                'solo',
                $gameId,
                $matchData
            );
            
            $statsMetrics = [
                'efficacite_brute' => $matchStats->efficacite_brute,
                'taux_participation' => $matchStats->taux_participation,
                'taux_precision' => $matchStats->taux_precision,
                'ratio_performance' => $matchStats->ratio_performance,
            ];
            
            $statsService->updateGlobalStatistics($user->id, 'solo');
        }
        
        // Récupérer le nom de l'adversaire du prochain niveau
        $opponents = config('opponents');
        $nextOpponentName = $this->getOpponentName($newLevel);
        
        $params = [
            'current_level' => $currentLevel,
            'new_level' => $newLevel,
            'theme' => $theme,
            'total_correct' => $totalCorrect,
            'total_incorrect' => $totalIncorrect,
            'total_unanswered' => $totalUnanswered,
            'global_efficiency' => $globalEfficiency,
            'next_opponent_name' => $nextOpponentName,
            'stats_metrics' => $statsMetrics,
        ];
        
        return view('victory', compact('params'));
    }
    
    public function defeat()
    {
        $currentLevel = session('niveau_selectionne', 1);
        $theme = session('theme', 'Général');
        $user = auth()->user();
        
        // La vie a déjà été déduite dans nextQuestion() avant la redirection
        // On récupère juste les informations pour l'affichage
        $lifeService = new \App\Services\LifeService();
        
        // Régénérer automatiquement les vies si le cooldown est écoulé
        $lifeService->regenerateLives($user);
        
        // Récupérer les vies restantes
        $remainingLives = $user ? (int)($user->lives ?? 0) : null;
        $hasLives = $lifeService->hasLivesAvailable($user);
        $cooldownTime = $lifeService->timeUntilNextRegen($user);
        
        // Calculer les statistiques globales finales
        $globalStats = session('global_stats', []);
        $totalCorrect = 0;
        $totalIncorrect = 0;
        $totalUnanswered = 0;
        
        foreach ($globalStats as $stat) {
            if (!$stat['player_buzzed']) {
                $totalUnanswered++;
            } elseif ($stat['is_correct']) {
                $totalCorrect++;
            } else {
                $totalIncorrect++;
            }
        }
        
        $totalQuestionsPlayed = count($globalStats);
        $globalEfficiency = $totalQuestionsPlayed > 0 ? round(($totalCorrect / $totalQuestionsPlayed) * 100) : 0;
        
        // Enregistrer les statistiques de match (défaite)
        $matchStats = null;
        $statsMetrics = null;
        if ($user) {
            $statsService = new StatisticsService();
            $matchData = $this->calculateMatchStatistics();
            
            $gameId = 'solo_' . $currentLevel . '_' . time();
            $matchStats = $statsService->recordMatchStatistics(
                $user->id,
                'solo',
                $gameId,
                $matchData
            );
            
            $statsMetrics = [
                'efficacite_brute' => $matchStats->efficacite_brute,
                'taux_participation' => $matchStats->taux_participation,
                'taux_precision' => $matchStats->taux_precision,
                'ratio_performance' => $matchStats->ratio_performance,
            ];
            
            $statsService->updateGlobalStatistics($user->id, 'solo');
        }
        
        $params = [
            'current_level' => $currentLevel,
            'theme' => $theme,
            'total_correct' => $totalCorrect,
            'total_incorrect' => $totalIncorrect,
            'total_unanswered' => $totalUnanswered,
            'global_efficiency' => $globalEfficiency,
            'remaining_lives' => $remainingLives,
            'has_lives' => $hasLives,
            'cooldown_time' => $cooldownTime,
            'next_life_regen' => $user && $user->next_life_regen ? $user->next_life_regen->toIso8601String() : null,
            'is_guest' => !$user,
            'stats_metrics' => $statsMetrics,
        ];
        
        return view('defeat', compact('params'));
    }

    
    private function getOpponentName($niveau)
    {
        $opponents = config('opponents');
        
        // Vérifier si c'est un boss (niveaux 10, 20, 30, etc.)
        if ($niveau % 10 === 0) {
            $bossData = $opponents['boss_opponents'][$niveau] ?? null;
            return $bossData ? $bossData['name'] : 'Adversaire';
        }
        
        // Sinon, adversaire régulier
        $opponentData = $opponents['regular_opponents'][$niveau] ?? null;
        return $opponentData ? $opponentData['name'] : 'Adversaire';
    }
    
    private function getOpponentInfo($niveau)
    {
        $opponents = config('opponents');
        
        // Vérifier si c'est un boss (niveaux 10, 20, 30, etc.)
        if ($niveau % 10 === 0) {
            $bossData = $opponents['boss_opponents'][$niveau] ?? null;
            if ($bossData) {
                return [
                    'name' => $bossData['name'],
                    'is_boss' => true,
                    'avatar' => $bossData['slug'],
                    'age' => null,
                    'next_boss' => null,
                    'description' => null,
                ];
            }
        } else {
            // Sinon, adversaire régulier
            $opponentData = $opponents['regular_opponents'][$niveau] ?? null;
            if ($opponentData) {
                $description = "Votre adversaire {$opponentData['name']} {$opponentData['age']} ans élève de {$opponentData['next_boss']}";
                return [
                    'name' => $opponentData['name'],
                    'is_boss' => false,
                    'avatar' => $opponentData['avatar'],
                    'age' => $opponentData['age'],
                    'next_boss' => $opponentData['next_boss'],
                    'description' => $description,
                ];
            }
        }
        
        return [
            'name' => 'Adversaire',
            'is_boss' => false,
            'avatar' => 'default',
            'age' => 8,
            'next_boss' => 'Le Stratège',
            'description' => 'Votre adversaire Adversaire 8 ans élève de Le Stratège',
        ];
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

    public function getBossForLevel($niveau)
    {
        // Charger les boss depuis la configuration
        $opponents = config('opponents');
        $bossOpponents = $opponents['boss_opponents'] ?? [];
        
        // Retourner le boss UNIQUEMENT si le niveau est exactement un niveau de boss (10, 20, 30, etc.)
        if (isset($bossOpponents[$niveau])) {
            $bossName = $bossOpponents[$niveau];
            
            // Créer le slug pour le chemin de l'image
            $slug = strtolower($bossName);
            $slug = str_replace(['é', 'è', 'ê'], 'e', $slug);
            $slug = str_replace(['à', 'â'], 'a', $slug);
            $slug = str_replace(' ', '-', $slug);
            $slug = str_replace('\'', '', $slug);
            
            return [
                'name' => $bossName,
                'slug' => $slug,
                'avatar' => "images/avatars/boss/{$slug}.png"
            ];
        }
        
        return null;
    }

    private function calculateMatchStatistics()
    {
        $globalStats = session('global_stats', []);
        
        $totalQuestions = 0;
        $questionsBuzzed = 0;
        $correctAnswers = 0;
        $wrongAnswers = 0;
        $pointsEarned = 0;
        $pointsPossible = 0;
        
        foreach ($globalStats as $stat) {
            $totalQuestions++;
            $pointsPossible += 2;
            
            if ($stat['player_buzzed']) {
                $questionsBuzzed++;
                
                if ($stat['is_correct']) {
                    $correctAnswers++;
                    $pointsEarned += 2;
                } else {
                    $wrongAnswers++;
                    $pointsEarned -= 2;
                }
            }
        }
        
        return [
            'total_questions' => $totalQuestions,
            'questions_buzzed' => $questionsBuzzed,
            'correct_answers' => $correctAnswers,
            'wrong_answers' => $wrongAnswers,
            'points_earned' => $pointsEarned,
            'points_possible' => $pointsPossible,
        ];
    }
}
