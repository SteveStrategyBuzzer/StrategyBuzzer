<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\QuestService;
use App\Services\StatisticsService;
use App\Services\AnswerNormalizationService;
use App\Services\ProfileStatsService;
use App\Models\QuestionHistory;

class SoloController extends Controller
{
    
    public function index(Request $request)
    {
        // Restaurer le niveau depuis profile_settings pour les utilisateurs authentifiés
        $user = auth()->user();
        if ($user) {
            $settings = (array) ($user->profile_settings ?? []);
            
            // Restaurer depuis choix_niveau (source de vérité unique)
            $savedLevel = (int) data_get($settings, 'choix_niveau', 1);
            
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

    public function opponents()
    {
        $regularOpponents = config('opponents.regular_opponents', []);
        $bossOpponents = config('opponents.boss_opponents', []);
        
        // Fusionner en préservant les clés numériques (niveau) avec l'opérateur +
        // array_merge() réindexerait les clés, ce qui casserait la correspondance niveau->adversaire
        $opponents = $regularOpponents + $bossOpponents;
        
        $playerLevel = session('choix_niveau', 1);

        return view('opponents_gallery', [
            'opponents' => $opponents,
            'playerLevel' => $playerLevel,
        ]);
    }

    public function selectOpponent($level)
    {
        $level = (int) $level;
        $maxLevel = session('choix_niveau', 1);
        
        if ($level > $maxLevel) {
            return response()->json(['success' => false, 'message' => 'Niveau verrouillé'], 403);
        }
        
        session(['niveau_selectionne' => $level]);
        
        return response()->json(['success' => true]);
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
        
        // CHARGER L'HISTORIQUE PERMANENT DES QUESTIONS DU JOUEUR
        // Note : $user est toujours présent car toutes les routes Solo nécessitent auth middleware
        $permanentUsedQuestionIds = QuestionHistory::getSeenQuestionIds($user->id);
        $permanentUsedAnswers = QuestionHistory::getSeenAnswers($user->id);
        
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
            'used_question_ids' => $permanentUsedQuestionIds,  // HISTORIQUE PERMANENT (DB ou session)
            'used_answers' => $permanentUsedAnswers,           // RÉPONSES PERMANENTES (DB ou session)
            'session_used_answers' => [],      // Réponses utilisées dans cette partie seulement (réinitialisé chaque partie)
            'current_question' => null,        // Sera généré au premier game()
            'global_stats' => [],              // Statistiques globales toutes manches
            'round_efficiencies' => [],        // Efficacités de chaque manche (pour calcul de l'efficacité de la partie)
            'round_summaries' => [],           // Stats détaillées par manche (pour affichage UI)
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
        $usedAnswers = session('used_answers', []);               // Historique permanent
        $sessionUsedAnswers = session('session_used_answers', []); // Réponses de cette partie
        
        // Générer la question SEULEMENT si elle n'existe pas déjà (première visite ou après nextQuestion)
        if (!session()->has('current_question') || session('current_question') === null) {
            $question = $questionService->generateQuestion($theme, $niveau, $currentQuestion, $usedQuestionIds, $usedAnswers, $sessionUsedAnswers);
            
            // DEBUG Bug #1: Log la question fraîchement générée
            \Log::info('[BUG#1 DEBUG] Question AFTER generation:', [
                'id' => $question['id'] ?? 'no-id',
                'text' => $question['text'] ?? 'no-text',
                'answers' => $question['answers'] ?? [],
                'correct_index' => $question['correct_index'] ?? -1,
                'correct_answer' => isset($question['answers'], $question['correct_index']) ? $question['answers'][$question['correct_index']] : 'N/A',
            ]);
            
            session(['current_question' => $question]);
            
            // DEBUG Bug #1: Log ce qui est stocké en session
            $stored = session('current_question');
            \Log::info('[BUG#1 DEBUG] Question AFTER session write:', [
                'id' => $stored['id'] ?? 'no-id',
                'text' => $stored['text'] ?? 'no-text',
                'answers' => $stored['answers'] ?? [],
                'correct_index' => $stored['correct_index'] ?? -1,
                'correct_answer' => isset($stored['answers'], $stored['correct_index']) ? $stored['answers'][$stored['correct_index']] : 'N/A',
            ]);
            
            // Ajouter l'ID de la question aux questions utilisées
            $usedQuestionIds[] = $question['id'];
            session(['used_question_ids' => $usedQuestionIds]);
            
            // Ajouter la réponse correcte aux réponses utilisées dans cette partie (évite doublons)
            $correctAnswer = $question['answers'][$question['correct_index']] ?? null;
            if ($correctAnswer) {
                // Normaliser la réponse avec le service partagé
                $normalizedAnswer = AnswerNormalizationService::normalize($correctAnswer);
                
                $sessionUsedAnswers = session('session_used_answers', []);
                $sessionUsedAnswers[] = $normalizedAnswer;
                session(['session_used_answers' => $sessionUsedAnswers]);
            }
            
            // Sauvegarder dans l'historique permanent de la database
            // Note : $user est toujours présent car toutes les routes Solo nécessitent auth middleware
            QuestionHistory::recordQuestion($user->id, $question);
        } else {
            $question = session('current_question');
            
            // DEBUG Bug #1: Log la question récupérée depuis session
            \Log::info('[BUG#1 DEBUG] Question FROM session (already exists):', [
                'id' => $question['id'] ?? 'no-id',
                'text' => $question['text'] ?? 'no-text',
                'answers' => $question['answers'] ?? [],
                'correct_index' => $question['correct_index'] ?? -1,
                'correct_answer' => isset($question['answers'], $question['correct_index']) ? $question['answers'][$question['correct_index']] : 'N/A',
            ]);
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
        
        // DEBUG Bug #1: Log la question AVANT passage à la vue
        \Log::info('[BUG#1 DEBUG] Question BEFORE view render:', [
            'id' => $question['id'] ?? 'no-id',
            'text' => $question['text'] ?? 'no-text',
            'answers' => $question['answers'] ?? [],
            'correct_index' => $question['correct_index'] ?? -1,
            'correct_answer' => isset($question['answers'], $question['correct_index']) ? $question['answers'][$question['correct_index']] : 'N/A',
        ]);
        
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
        
        // Récupérer les scores actuels et le numéro de question pour l'algorithme Boss
        $playerScore = session('score', 0);
        $opponentScore = session('opponent_score', 0);
        $questionNumber = session('current_question_number', 1);
        
        // Simuler le comportement complet de l'adversaire IA (passer timing du buzz)
        $opponentBehavior = $questionService->simulateOpponentBehavior(
            $niveau, 
            $question, 
            $playerBuzzed, 
            $buzzTime, 
            $chronoTime,
            $playerScore,
            $opponentScore,
            $questionNumber
        );
        
        // Calculer les points du joueur selon les nouvelles règles
        $playerPoints = 0;
        
        if ($playerBuzzed) {
            // Le joueur a buzzé
            if ($isCorrect) {
                // Le joueur est 2ème (+1 pt) si l'adversaire est plus rapide (peu importe s'il a réussi ou raté)
                // Sinon le joueur est 1er (+2 pts)
                $playerPoints = $opponentBehavior['is_faster'] ? 1 : 2;
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
            'player_points' => $playerPoints,  // Stocker les points RÉELS (+2, +1, 0, ou -2)
            'opponent_faster' => $opponentBehavior['is_faster'],  // Nécessaire pour calculer max points possible
            'opponent_correct' => $opponentBehavior['is_correct'],
            'round' => session('current_round', 1),
            'is_bonus' => false,  // Marquer explicitement comme question normale (pas bonus)
        ];
        session(['global_stats' => $globalStats]);
        
        // Vérifier et compléter les quêtes (si connecté)
        $user = auth()->user();
        if ($user) {
            $questService = new QuestService();
            
            // Quête : Buzz rapides (first_buzz_10)
            // Le joueur est premier si : il a buzzé ET (l'adversaire n'a pas buzzé OU l'adversaire était plus lent)
            $playerWasFirst = $playerBuzzed && (!$opponentBehavior['buzzes'] || $opponentBehavior['is_faster'] === false);
            if ($playerWasFirst) {
                $questService->checkAndCompleteQuests($user, 'first_buzz_10', [
                    'first_buzz' => true,
                ]);
            }
            
            // Quêtes nécessitant une réponse correcte
            if ($isCorrect && $playerBuzzed) {
                // Quête : Réponses rapides (fast_answers_10)
                // Si le joueur a répondu rapidement (< 2 secondes)
                if ($buzzTime < 2) {
                    $questService->checkAndCompleteQuests($user, 'fast_answers_10', [
                        'answer_time' => $buzzTime,
                    ]);
                }
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
        $totalQuestionsPlayed = 0;
        
        foreach ($globalStats as $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans les statistiques globales
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                continue;
            }
            
            $totalQuestionsPlayed++;
            
            if (!$stat['player_buzzed']) {
                $totalUnanswered++;
            } elseif ($stat['is_correct']) {
                $totalCorrect++;
            } else {
                $totalIncorrect++;
            }
        }
        
        // Calculer l'efficacité basée sur les points
        $globalEfficiency = $this->calculateEfficiency($globalStats);

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
            'total_questions_played' => $totalQuestionsPlayed,
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
            
            // IMPORTANT : Sauvegarder les stats de la manche qui vient de se terminer
            // (même si le match va se terminer après)
            $this->saveRoundStatistics();
            
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
                        
                        // Mettre à jour le niveau solo (choix_niveau = source unique de vérité)
                        $settings['choix_niveau'] = $newChoixNiveau;
                        
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
            
            // Calculer l'efficacité de la manche qui vient de se terminer basée sur les points RÉELS
            // BUG FIX #11: Utiliser calculateRoundStatistics() pour avoir les mêmes calculs partout
            $currentRound = session('current_round', 1);
            $roundStats = $this->calculateRoundStatistics($currentRound);
            $roundEfficiency = $roundStats['efficiency'];
            $pointsEarned = $roundStats['points_earned'];
            $pointsPossible = $roundStats['points_possible'];
            
            // Sauvegarder l'efficacité de cette manche dans un tableau
            $roundEfficiencies = session('round_efficiencies', []);
            $roundEfficiencies[$currentRound] = $roundEfficiency;
            session(['round_efficiencies' => $roundEfficiencies]);
            
            // Sauvegarder les infos de la manche pour la page de résultat
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
                // NE PAS réinitialiser used_question_ids (historique permanent + questions de la partie)
                // NE PAS réinitialiser session_used_answers (doublons réponses interdits dans toute la partie)
            ]);
            
            // BUG FIX #7: Nettoyer la question actuelle pour éviter qu'elle réapparaisse dans la nouvelle manche
            session()->forget('current_question');
            session()->forget('question_start_time');
            session()->forget('chrono_time');
            session()->forget('buzzed');
            session()->forget('buzz_time');
            
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
        $totalQuestionsPlayed = 0;
        
        foreach ($globalStats as $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans les statistiques globales
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                continue;
            }
            
            $totalQuestionsPlayed++;
            
            if (!$stat['player_buzzed']) {
                $totalUnanswered++;
            } elseif ($stat['is_correct']) {
                $totalCorrect++;
            } else {
                $totalIncorrect++;
            }
        }
        
        // Calculer l'efficacité globale basée sur les points
        $globalEfficiency = $this->calculateEfficiency($globalStats);
        
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
                $settings['choix_niveau'] = $newLevel; // Source unique de vérité pour le niveau solo
                $user->profile_settings = $settings;
                $user->save();
            }
            
            // Rediriger vers la page de victoire
            return redirect()->route('solo.victory');
        } elseif ($opponentRoundsWon >= 2) {
            // DÉFAITE DU JOUEUR - Rediriger vers une page de défaite
            return redirect()->route('solo.defeat');
        }
        
        // Calculer les statistiques de la manche qui vient de se terminer
        $roundNumber = $currentRound - 1; // La manche qui vient de se terminer
        $completedRoundStats = $this->calculateRoundStatistics($roundNumber);
        
        // Stocker les stats de cette manche dans round_summaries
        $roundSummaries = session('round_summaries', []);
        $roundSummaries[$roundNumber] = $completedRoundStats;
        session(['round_summaries' => $roundSummaries]);
        
        // Calculer les métriques supplémentaires selon le système défini
        $roundEfficiencies = session('round_efficiencies', []);
        
        // Efficacité Max Possible (fin manche 1) : (% efficacité Manche + 100%) / 2
        $efficiencyMaxPossible = null;
        if ($roundNumber == 1 && isset($roundEfficiencies[1])) {
            $efficiencyMaxPossible = round(($roundEfficiencies[1] + 100) / 2, 2);
        }
        
        // Efficacité de la Partie (moyenne de toutes les manches jouées)
        $partyEfficiency = null;
        if (count($roundEfficiencies) > 0) {
            $partyEfficiency = round(array_sum($roundEfficiencies) / count($roundEfficiencies), 2);
        }
        
        // Sinon, afficher le résultat de la manche et continuer
        $params = [
            'round_number' => $roundNumber,        // La manche qui vient de se terminer
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
            // Métriques supplémentaires
            'efficiency_max_possible' => $efficiencyMaxPossible,
            'party_efficiency' => $partyEfficiency,
            // Stats par manche (toutes les manches complétées jusqu'à maintenant)
            'round_summaries' => $roundSummaries,
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
        $totalQuestionsPlayed = 0;
        
        foreach ($globalStats as $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans les statistiques globales
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                continue;
            }
            
            $totalQuestionsPlayed++;
            
            if (!$stat['player_buzzed']) {
                $totalUnanswered++;
            } elseif ($stat['is_correct']) {
                $totalCorrect++;
            } else {
                $totalIncorrect++;
            }
        }
        
        // Calculer l'efficacité globale basée sur les points
        $globalEfficiency = $this->calculateEfficiency($globalStats);
        
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
                'efficacite_brute' => $matchStats->efficacite_brute ?? 0,
                'efficacite_partie' => $matchStats->efficacite_partie,
                'efficacite_joueur' => $statsService->getPlayerStatistics($user->id, 'solo')->efficacite_joueur ?? 0,
                'taux_participation' => $matchStats->taux_participation,
                'taux_precision' => $matchStats->taux_precision,
                'ratio_performance' => $matchStats->ratio_performance,
            ];
            
            $statsService->updateGlobalStatistics($user->id, 'solo');
            
            // Enregistrer les stats dans profile_stats pour déblocage et suivi
            $playerRoundsWon = session('player_rounds_won', 2);
            $opponentRoundsWon = session('opponent_rounds_won', 0);
            $roundsPlayed = $playerRoundsWon + $opponentRoundsWon;
            
            ProfileStatsService::updateSoloStats(
                $user,
                true, // victoire
                $roundsPlayed,
                $matchStats->efficacite_partie,
                $newLevel,
                $gameId
            );
        }
        
        // Calculer l'efficacité moyenne de la partie
        $roundEfficiencies = session('round_efficiencies', []);
        $partyEfficiency = null;
        if (count($roundEfficiencies) > 0) {
            $partyEfficiency = round(array_sum($roundEfficiencies) / count($roundEfficiencies), 2);
        }
        
        // Récupérer les stats par manche (toutes les manches complétées)
        $roundSummaries = session('round_summaries', []);
        
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
            'party_efficiency' => $partyEfficiency,
            'next_opponent_name' => $nextOpponentName,
            'stats_metrics' => $statsMetrics,
            // Stats par manche (toutes les manches de la partie)
            'round_summaries' => $roundSummaries,
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
        $totalQuestionsPlayed = 0;
        
        foreach ($globalStats as $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans les statistiques globales
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                continue;
            }
            
            $totalQuestionsPlayed++;
            
            if (!$stat['player_buzzed']) {
                $totalUnanswered++;
            } elseif ($stat['is_correct']) {
                $totalCorrect++;
            } else {
                $totalIncorrect++;
            }
        }
        
        // Calculer l'efficacité globale basée sur les points
        $globalEfficiency = $this->calculateEfficiency($globalStats);
        
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
                'efficacite_brute' => $matchStats->efficacite_brute ?? 0,
                'efficacite_partie' => $matchStats->efficacite_partie,
                'efficacite_joueur' => $statsService->getPlayerStatistics($user->id, 'solo')->efficacite_joueur ?? 0,
                'taux_participation' => $matchStats->taux_participation,
                'taux_precision' => $matchStats->taux_precision,
                'ratio_performance' => $matchStats->ratio_performance,
            ];
            
            $statsService->updateGlobalStatistics($user->id, 'solo');
            
            // Enregistrer les stats dans profile_stats pour suivi
            $playerRoundsWon = session('player_rounds_won', 0);
            $opponentRoundsWon = session('opponent_rounds_won', 2);
            $roundsPlayed = $playerRoundsWon + $opponentRoundsWon;
            
            ProfileStatsService::updateSoloStats(
                $user,
                false, // défaite
                $roundsPlayed,
                $matchStats->efficacite_partie,
                null, // pas de nouveau niveau en cas de défaite
                $gameId
            );
        }
        
        // Calculer l'efficacité moyenne de la partie
        $roundEfficiencies = session('round_efficiencies', []);
        $partyEfficiency = null;
        if (count($roundEfficiencies) > 0) {
            $partyEfficiency = round(array_sum($roundEfficiencies) / count($roundEfficiencies), 2);
        }
        
        // Récupérer les stats par manche (toutes les manches complétées)
        $roundSummaries = session('round_summaries', []);
        
        $nextLifeRegen = null;
        if ($user && $user->next_life_regen) {
            if ($user->next_life_regen instanceof \DateTimeInterface) {
                $nextLifeRegen = $user->next_life_regen->format('c');
            } elseif (is_string($user->next_life_regen)) {
                $nextLifeRegen = $user->next_life_regen;
            }
        }
        
        $params = [
            'current_level' => $currentLevel,
            'theme' => $theme,
            'total_correct' => $totalCorrect,
            'total_incorrect' => $totalIncorrect,
            'total_unanswered' => $totalUnanswered,
            'global_efficiency' => $globalEfficiency,
            'party_efficiency' => $partyEfficiency,
            'remaining_lives' => $remainingLives,
            'has_lives' => $hasLives,
            'cooldown_time' => $cooldownTime,
            'next_life_regen' => $nextLifeRegen,
            'is_guest' => false, // Toujours false car auth middleware requis
            'stats_metrics' => $statsMetrics,
            // Stats par manche (toutes les manches de la partie)
            'round_summaries' => $roundSummaries,
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
            $bossData = $bossOpponents[$niveau];
            
            // Les boss sont stockés comme ['name' => ..., 'slug' => ...]
            return [
                'name' => $bossData['name'],
                'slug' => $bossData['slug'],
                'avatar' => "images/avatars/bosses/{$bossData['slug']}.png"
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
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans les stats globales
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                continue;  // Sauter les questions bonus
            }
            
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

    /**
     * Calcule les statistiques pour une manche spécifique
     * Filtre global_stats par numéro de manche et agrège les résultats
     * 
     * @param int $roundNumber Le numéro de la manche (1, 2, ou 3)
     * @return array Stats détaillées de la manche
     */
    private function calculateRoundStatistics(int $roundNumber): array
    {
        $globalStats = session('global_stats', []);
        
        // Filtrer les stats pour cette manche uniquement
        $roundStats = array_filter($globalStats, function($stat) use ($roundNumber) {
            return isset($stat['round']) && $stat['round'] == $roundNumber;
        });
        
        // Agrégation des statistiques
        $questions = 0;
        $buzzed = 0;
        $correct = 0;
        $wrong = 0;
        $unanswered = 0;
        $pointsEarned = 0;
        $pointsPossible = 0;
        
        foreach ($roundStats as $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans le total
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                continue;  // Sauter les questions bonus
            }
            
            $questions++;
            
            // Calculer le maximum de points possibles pour cette question
            // Si adversaire a buzzé en premier → max = 1 pt, sinon max = 2 pts
            $maxPointsForQuestion = (isset($stat['opponent_faster']) && $stat['opponent_faster']) ? 1 : 2;
            $pointsPossible += $maxPointsForQuestion;
            
            // Utiliser les points RÉELS si disponibles
            if (isset($stat['player_points'])) {
                $pointsEarned += $stat['player_points'];
                Log::info("Manche {$roundNumber} - Q#{$questions}: pts={$stat['player_points']}, buzzed=" . ($stat['player_buzzed'] ? '1' : '0') . ", correct=" . ($stat['is_correct'] ? '1' : '0') . ", skill=" . (isset($stat['skill_adjusted']) ? '1' : '0') . " | Total cumulé: {$pointsEarned}");
            }
            
            if (!$stat['player_buzzed']) {
                $unanswered++;
            } else {
                $buzzed++;
                if ($stat['is_correct']) {
                    $correct++;
                } else {
                    $wrong++;
                }
            }
        }
        
        // Calculer l'efficacité : (points_gagnés / max_possible) puis moyenne avec 100%
        $efficiency = 50; // Défaut si pas de questions
        if ($pointsPossible > 0) {
            // Efficacité brute = (points gagnés / max possible) × 100
            $rawEfficiency = ($pointsEarned / $pointsPossible) * 100;
            $rawEfficiency = max(-100, min(100, $rawEfficiency));
            // Efficacité finale = moyenne entre efficacité brute et 100%
            $efficiency = ($rawEfficiency + 100) / 2;
            $efficiency = round($efficiency, 2);
        }
        
        return [
            'round' => $roundNumber,
            'questions' => $questions,
            'buzzed' => $buzzed,
            'correct' => $correct,
            'wrong' => $wrong,
            'unanswered' => $unanswered,
            'points_earned' => $pointsEarned,
            'points_possible' => $pointsPossible,
            'efficiency' => $efficiency,
        ];
    }

    /**
     * Calcule l'efficacité basée sur les points RÉELS selon la formule :
     * Efficacité = (Points gagnés / Points max possibles) × 100
     * Utilise les points réels stockés qui peuvent être +2, +1, 0, ou -2
     * Points max dépend de opponent_faster : 1 pt si adversaire plus rapide, sinon 2 pts
     */
    private function calculateEfficiency(array $stats): float
    {
        $pointsEarned = 0;
        $pointsPossible = 0;
        
        foreach ($stats as $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans le calcul d'efficacité
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                continue;  // Sauter les questions bonus
            }
            
            // Calculer le maximum de points possibles pour cette question
            // Si adversaire a buzzé en premier → max = 1 pt, sinon max = 2 pts
            $maxPointsForQuestion = (isset($stat['opponent_faster']) && $stat['opponent_faster']) ? 1 : 2;
            $pointsPossible += $maxPointsForQuestion;
            
            // Utiliser les points RÉELS si disponibles, sinon fallback sur l'ancienne logique
            if (isset($stat['player_points'])) {
                $pointsEarned += $stat['player_points'];
            } else {
                // Fallback pour compatibilité avec anciennes données
                if ($stat['player_buzzed']) {
                    if ($stat['is_correct']) {
                        $pointsEarned += 2;
                    } else {
                        $pointsEarned -= 2;
                    }
                }
            }
        }
        
        if ($pointsPossible > 0) {
            $rawEfficiency = ($pointsEarned / $pointsPossible) * 100;
            $rawEfficiency = max(-100, min(100, $rawEfficiency)); // Clamp entre -100 et 100
            // Transformer échelle -100/+100 en 0/100 avec formule (efficacité_brute + 100%) / 2
            $efficiency = ($rawEfficiency + 100) / 2;
            return round($efficiency, 2);
        }
        
        return 50; // 50% = neutre (0% brut transformé)
    }

    public function cancelError(Request $request)
    {
        $avatar = session('avatar', 'Aucun');
        
        if ($avatar !== 'Magicienne') {
            return response()->json(['success' => false, 'message' => 'Skill non disponible pour cet avatar'], 403);
        }
        
        $usedSkills = session('used_skills', []);
        if (in_array('cancel_error', $usedSkills)) {
            return response()->json(['success' => false, 'message' => 'Skill déjà utilisé'], 403);
        }
        
        $globalStats = session('global_stats', []);
        if (empty($globalStats)) {
            return response()->json(['success' => false, 'message' => 'Aucune question à annuler'], 403);
        }
        
        $lastIndex = count($globalStats) - 1;
        $lastStat = $globalStats[$lastIndex];
        
        if (!$lastStat['player_buzzed'] || $lastStat['is_correct']) {
            return response()->json(['success' => false, 'message' => 'La dernière question n\'était pas une erreur'], 403);
        }
        
        $playerPoints = $lastStat['player_points'] ?? -2;
        if ($playerPoints >= 0) {
            return response()->json(['success' => false, 'message' => 'La dernière question n\'était pas une erreur'], 403);
        }
        
        $pointsToRecover = abs($playerPoints);
        $currentScore = session('score', 0);
        session(['score' => $currentScore + $pointsToRecover]);
        
        // BUG FIX #9 & #14: Transformer l'échec en "sans réponse" (annuler complètement l'action)
        $answeredQuestions = session('answered_questions', []);
        $answeredLastIndex = count($answeredQuestions) - 1;
        if ($answeredLastIndex >= 0) {
            $answeredQuestions[$answeredLastIndex]['player_buzzed'] = false;  // Plus de buzz
            $answeredQuestions[$answeredLastIndex]['is_correct'] = false;      // Plus correct
            $answeredQuestions[$answeredLastIndex]['player_points'] = 0;
            $answeredQuestions[$answeredLastIndex]['skill_adjusted'] = true;
            session(['answered_questions' => $answeredQuestions]);
        }
        
        // Transformer aussi dans global_stats
        $globalStats[$lastIndex]['player_buzzed'] = false;  // Maintenant compté comme "sans réponse"
        $globalStats[$lastIndex]['is_correct'] = false;
        $globalStats[$lastIndex]['player_points'] = 0;
        $globalStats[$lastIndex]['skill_adjusted'] = true;
        session(['global_stats' => $globalStats]);
        
        $usedSkills[] = 'cancel_error';
        session(['used_skills' => $usedSkills]);
        
        return response()->json([
            'success' => true, 
            'message' => 'Erreur annulée ! +' . $pointsToRecover . ' points récupérés',
            'new_score' => session('score'),
            'used_skills' => $usedSkills
        ]);
    }

    public function bonusQuestion()
    {
        $avatar = session('avatar', 'Aucun');
        
        if ($avatar !== 'Magicienne') {
            return redirect()->route('solo.game')->with('error', 'Skill non disponible pour cet avatar');
        }
        
        $usedSkills = session('used_skills', []);
        if (in_array('bonus_question', $usedSkills)) {
            return redirect()->route('solo.game')->with('error', 'Skill déjà utilisé');
        }
        
        $usedSkills[] = 'bonus_question';
        session(['used_skills' => $usedSkills]);
        
        $questionService = new \App\Services\QuestionService();
        $theme = session('theme', 'Général');
        $niveau = session('niveau_selectionne', 1);
        $usedQuestionIds = session('used_question_ids', []);
        $usedAnswers = session('used_answers', []);
        $sessionUsedAnswers = session('session_used_answers', []);
        
        $question = $questionService->generateQuestion($theme, $niveau, 999, $usedQuestionIds, $usedAnswers, $sessionUsedAnswers);
        
        // Enregistrer la question bonus dans l'historique permanent
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user) {
            QuestionHistory::recordQuestion($user->id, $question);
            
            // Ajouter l'ID et la réponse aux listes d'exclusion
            $usedQuestionIds[] = $question['id'];
            session(['used_question_ids' => $usedQuestionIds]);
            
            // Normaliser et ajouter la réponse correcte
            $correctAnswer = $question['answers'][$question['correct_index']] ?? null;
            if ($correctAnswer) {
                $normalizedAnswer = AnswerNormalizationService::normalize($correctAnswer);
                $sessionUsedAnswers[] = $normalizedAnswer;
                session(['session_used_answers' => $sessionUsedAnswers]);
            }
        }
        
        session(['bonus_question' => $question]);
        session(['bonus_question_start_time' => time()]);
        
        $params = [
            'question' => $question,
            'score' => session('score', 0),
            'opponent_score' => session('opponent_score', 0),
            'current_round' => session('current_round', 1),
            'avatar' => $avatar,
        ];
        
        return view('bonus_question', compact('params'));
    }

    public function answerBonus(Request $request)
    {
        $avatar = session('avatar', 'Aucun');
        
        if ($avatar !== 'Magicienne') {
            return redirect()->route('solo.game')->with('error', 'Skill non disponible pour cet avatar');
        }
        
        $answerIndex = (int) $request->input('answer_index', -1);
        $question = session('bonus_question');
        $startTime = session('bonus_question_start_time', time());
        $timeElapsed = time() - $startTime;
        
        if (!$question) {
            return redirect()->route('solo.game')->with('error', 'Question bonus expirée');
        }
        
        $questionService = new \App\Services\QuestionService();
        $isCorrect = false;
        $points = 0;
        
        if ($answerIndex >= 0) {
            $isCorrect = $questionService->checkAnswer($question, $answerIndex);
            $points = $isCorrect ? 2 : -2;
        }
        
        $currentScore = session('score', 0);
        session(['score' => $currentScore + $points]);
        
        // Enregistrer la question bonus dans global_stats avec flag is_bonus
        $currentRound = session('current_round', 1);
        $globalStats = session('global_stats', []);
        $globalStats[] = [
            'is_correct' => $isCorrect,
            'player_buzzed' => $answerIndex >= 0,
            'player_points' => $points,
            'opponent_buzzed' => false,
            'opponent_faster' => false,
            'round' => $currentRound,
            'is_bonus' => true,  // FLAG POUR IDENTIFIER LES QUESTIONS BONUS
        ];
        session(['global_stats' => $globalStats]);
        
        $usedSkills = session('used_skills', []);
        $usedSkills[] = 'bonus_question';
        session(['used_skills' => $usedSkills]);
        
        // Sauvegarder le résultat du bonus pour affichage ultérieur
        session(['bonus_question_result' => [
            'points' => $points,
            'is_correct' => $isCorrect,
            'answered' => $answerIndex >= 0
        ]]);
        
        session()->forget('bonus_question');
        session()->forget('bonus_question_start_time');
        
        return redirect()->route('solo.game')->with('bonus_result', [
            'is_correct' => $isCorrect,
            'points' => $points,
            'time_elapsed' => $timeElapsed
        ]);
    }

    /**
     * Sauvegarder les statistiques de la manche qui vient de se terminer dans round_summaries
     * Cette méthode est appelée à la fin de chaque manche, que le match continue ou se termine
     */
    private function saveRoundStatistics(): void
    {
        // La manche qui vient de se terminer = current_round - 1
        // (car current_round a déjà été incrémenté par endRound())
        $roundNumber = max(1, session('current_round', 1) - 1);
        
        // Calculer les statistiques de la manche
        $completedRoundStats = $this->calculateRoundStatistics($roundNumber);
        
        // Stocker les stats de cette manche dans round_summaries
        $roundSummaries = session('round_summaries', []);
        $roundSummaries[$roundNumber] = $completedRoundStats;
        session(['round_summaries' => $roundSummaries]);
    }
}
