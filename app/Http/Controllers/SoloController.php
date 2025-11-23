<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\QuestService;
use App\Services\StatisticsService;
use App\Services\AnswerNormalizationService;
use App\Services\ProfileStatsService;
use App\Models\QuestionHistory;

class SoloController extends Controller
{
    private function getUserLanguage(): string
    {
        $user = auth()->user();
        return $user?->preferred_language ?? config('languages.default', 'fr');
    }
    
    public function index(Request $request)
    {
        // Restaurer le niveau et l'avatar depuis profile_settings pour les utilisateurs authentifiés
        $user = auth()->user();
        if ($user) {
            $settings = (array) ($user->profile_settings ?? []);
            
            // Restaurer depuis choix_niveau (source de vérité unique)
            $savedLevel = (int) data_get($settings, 'choix_niveau', 1);
            
            // Si le niveau sauvegardé est supérieur au niveau en session, utiliser le niveau sauvegardé
            if ($savedLevel > session('choix_niveau', 1)) {
                session(['choix_niveau' => $savedLevel]);
            }
            
            // Restaurer l'avatar stratégique depuis profile_settings
            $savedAvatar = (string) data_get($settings, 'strategic_avatar.name', '');
            if (!empty($savedAvatar)) {
                session(['avatar' => $savedAvatar]);
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
        $nbQuestions  = (int) $validated['nb_questions'];  // Cast explicite en integer
        $niveau       = (int) $validated['niveau_joueur'];

        // Sécurise : ne pas dépasser le niveau débloqué
        $max = session('choix_niveau', 1);
        if ($niveau > $max) $niveau = $max;

        // NOUVEAU SYSTÈME : Best of 3 manches
        // Une manche = TOUTES les questions sélectionnées
        // Gagner 2 manches sur 3 pour gagner la partie
        
        // CHARGER L'HISTORIQUE PERMANENT DES QUESTIONS DU JOUEUR
        // Note : $user est toujours présent car la route Solo nécessite le middleware auth
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
            'session_used_question_texts' => [], // Textes des questions posées dans cette partie (évite doublons dans la même partie)
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
        
        // Récupérer le pseudonyme du joueur depuis profile_settings
        $playerPseudonym = 'Joueur';
        if ($user) {
            $settings = (array) ($user->profile_settings ?? []);
            $playerPseudonym = (string) data_get($settings, 'pseudonym', 'Joueur');
        }
        
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
            'niveau_progression' => session('choix_niveau', 1),
            'current'         => 1,
            'question_id'     => $questions[0]['id'],
            'question_text'   => $questions[0]['question_text'],
            'answers'         => $questions[0]['answers'],
            'boss_name'       => $bossInfo['name'] ?? null,
            'boss_avatar'     => $bossInfo['avatar'] ?? null,
            'boss_skills'     => $bossInfo['skills'] ?? [],
            'opponent_info'   => $opponentInfo,
            'player_avatar'   => $playerAvatar,
            'player_pseudonym' => $playerPseudonym,
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
        
        // Récupérer le pseudonyme du joueur depuis profile_settings
        $playerPseudonym = 'Joueur';
        if ($user) {
            $settings = (array) ($user->profile_settings ?? []);
            $playerPseudonym = (string) data_get($settings, 'pseudonym', 'Joueur');
        }
        
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
            'niveau_progression' => session('choix_niveau', 1),
            'boss_name'       => $bossInfo['name'] ?? null,
            'boss_avatar'     => $bossInfo['avatar'] ?? null,
            'boss_skills'     => $bossInfo['skills'] ?? [],
            'opponent_info'   => $opponentInfo,
            'player_avatar'   => $playerAvatar,
            'player_pseudonym' => $playerPseudonym,
            'avatar_conflict' => $avatarConflict,
            'has_boss'        => $bossInfo !== null,
        ];
        
        return view('resume', compact('params'));
    }

    public function prepare()
    {
        // Simple méthode qui affiche juste l'écran de préparation
        // Le compte à rebours est géré par JavaScript dans la vue
        
        // Récupérer le niveau sélectionné pour afficher le profil du boss si c'est un niveau boss
        $niveau = session('niveau_selectionne', 1);
        $bossProfile = $this->getBossProfile($niveau);
        
        // Passer le profil boss à la vue (null si ce n'est pas un boss)
        return view('game_preparation', [
            'boss_profile' => $bossProfile,
            'niveau' => $niveau
        ]);
    }

    public function game()
    {
        // IMPORTANT : Désactiver le flag de génération dès le début de game()
        // pour éviter qu'il reste bloqué en cas d'erreur ou de flux alternatif
        session(['question_generation_pending' => false]);
        
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
        $sessionUsedQuestionTexts = session('session_used_question_texts', []); // Textes des questions de cette partie
        
        // NOUVEAU : Récupérer l'info de l'adversaire pour adapter la difficulté des questions
        $opponentInfo = $this->getOpponentInfo($niveau);
        $opponentAge = $opponentInfo['age'] ?? null;          // 8-26 ans pour étudiants, null pour Boss
        $isBoss = $opponentInfo['is_boss'] ?? false;         // true si combat contre Boss
        
        // Générer la question SEULEMENT si elle n'existe pas déjà (première visite ou après nextQuestion)
        if (!session()->has('current_question') || session('current_question') === null) {
            // NOUVEAU SYSTÈME PROGRESSIF : Utiliser le stock de questions généré par blocs
            $currentRound = session('current_round', 1);
            $stockKey = "question_stock_round_{$currentRound}";
            $questionStock = session($stockKey, []);
            
            // NETTOYAGE AUTOMATIQUE : Si on démarre une nouvelle manche (question 1) et que le stock existe déjà,
            // c'est probablement un reste de la manche précédente → le nettoyer pour éviter questions stale
            if ($currentQuestion === 1 && !empty($questionStock)) {
                Log::info('[STOCK CLEANUP] Clearing stale stock from previous round', [
                    'round' => $currentRound,
                    'stale_stock_size' => count($questionStock)
                ]);
                $questionStock = [];
                session([$stockKey => []]);
            }
            
            // Piocher la question dans le stock (index = current_question_number - 1)
            $questionIndex = $currentQuestion - 1;
            if (!empty($questionStock) && isset($questionStock[$questionIndex])) {
                $question = $questionStock[$questionIndex];
                Log::info('[PROGRESSIVE STOCK] Using question from progressive stock', [
                    'round' => $currentRound,
                    'question_number' => $currentQuestion,
                    'total_in_stock' => count($questionStock),
                    'remaining' => count($questionStock) - $currentQuestion
                ]);
            } else {
                // Fallback : générer à la demande si le stock est vide (CORRIGÉ : ajouter au stock !)
                $language = $this->getUserLanguage();
                $question = $questionService->generateQuestion($theme, $niveau, $currentQuestion, $usedQuestionIds, [], $sessionUsedAnswers, $sessionUsedQuestionTexts, $opponentAge, $isBoss, $language);
                
                // CRITIQUE : Ajouter la question générée au stock pour éviter régénération
                $questionStock[$questionIndex] = $question;
                session([$stockKey => $questionStock]);
                
                Log::info('[FALLBACK] Generated question on-demand and added to stock', [
                    'round' => $currentRound,
                    'question_number' => $currentQuestion,
                    'stock_size_before' => count($questionStock) - 1,
                    'stock_size_after' => count($questionStock)
                ]);
            }
            
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
            
            // Ajouter le texte de la question aux textes utilisés dans cette partie (évite doublons)
            if (isset($question['text'])) {
                $sessionUsedQuestionTexts[] = $question['text'];
                session(['session_used_question_texts' => $sessionUsedQuestionTexts]);
            }
            
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
        
        $answerIndex = $request->input('answer_index', -1);
        $answerIndex = ($answerIndex === null || $answerIndex === '') ? -1 : (int) $answerIndex;
        $question = session('current_question');
        $niveau = session('niveau_selectionne', 1);
        
        // Vérifier si le joueur a buzzé
        $playerBuzzed = session('buzzed', false);
        
        // Récupérer le temps de buzz et le temps du chrono
        $buzzTime = session('buzz_time', 0);
        $chronoTime = session('chrono_time', 8);
        
        // Vérifier la réponse du joueur (BUG #2 FIX: -1 = aucun choix)
        $isCorrect = ($answerIndex >= 0) ? $questionService->checkAnswer($question, $answerIndex) : false;
        
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
        
        // Calculer les points du joueur selon les nouvelles règles (BUG #2 FIX)
        $playerPoints = 0;
        
        if ($playerBuzzed) {
            // Le joueur a buzzé
            if ($answerIndex === -1) {
                // Aucun choix de réponse = -2 pts (BUG #2 FIX)
                $playerPoints = -2;
            } elseif ($isCorrect) {
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
        
        Log::info("Computing global stats from " . count($globalStats) . " entries, nb_questions=" . session('nb_questions', 'N/A'));
        
        foreach ($globalStats as $index => $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans les statistiques globales
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                Log::info("  [{$index}] SKIPPED: bonus question");
                continue;
            }
            
            $totalQuestionsPlayed++;
            
            // Log chaque question pour déboguer
            Log::info("  [{$index}] Q#{$totalQuestionsPlayed}: buzzed=" . ($stat['player_buzzed'] ? 'yes' : 'no') . ", correct=" . ($stat['is_correct'] ? 'yes' : 'no') . ", points=" . ($stat['player_points'] ?? 'N/A') . ", skill_adjusted=" . (isset($stat['skill_adjusted']) && $stat['skill_adjusted'] ? 'yes' : 'no'));
            
            if (!$stat['player_buzzed']) {
                $totalUnanswered++;
            } elseif ($stat['is_correct']) {
                $totalCorrect++;
            } else {
                $totalIncorrect++;
            }
        }
        
        Log::info("Final tally: correct={$totalCorrect}, incorrect={$totalIncorrect}, unanswered={$totalUnanswered}, total={$totalQuestionsPlayed}");
        
        // Calculer l'efficacité basée sur les points
        $globalEfficiency = $this->calculateEfficiency($globalStats);

        // Récupérer le nom de l'adversaire pour l'affichage
        $opponentInfo = $this->getOpponentInfo($niveau);
        
        // Récupérer l'explication "Le saviez-vous" depuis la question ou générer si absente
        $didYouKnow = $question['explanation'] ?? $this->generateDidYouKnow($question, $isCorrect);
        
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
            'opponent_name' => $opponentInfo['name'] ?? 'Adversaire',
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
            'did_you_know' => $didYouKnow,
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
        // GARDE DE RÉENTRANCE : Empêcher les appels concurrents pendant la génération de question
        $answeredCount = count(session('answered_questions', []));
        $currentQuestion = (int) session('current_question_number', 1);
        $isGenerating = session('question_generation_pending', false);
        
        // Si une question est en cours de génération OU si on est en avance sur les questions répondues
        if ($isGenerating || $currentQuestion > $answeredCount + 1) {
            \Log::warning('[REENTRANCY GUARD] nextQuestion() bloqué - génération déjà en cours', [
                'current_question' => $currentQuestion,
                'answered_count' => $answeredCount,
                'is_generating' => $isGenerating
            ]);
            
            // Rediriger immédiatement vers la question en cours au lieu de sauter
            return redirect()->route('solo.game');
        }
        
        // Activer le flag de génération pour bloquer les appels concurrents
        session(['question_generation_pending' => true]);
        
        // BEST OF 3 : 10 questions par manche (pas 30 total !)
        $questionsPerRound = 10;
        
        // DEBUG: Log pour diagnostiquer le problème des 11 questions au lieu de 10
        \Log::info('[BUG#3 DEBUG] nextQuestion() appelé:', [
            'current_question_number' => $currentQuestion,
            'questions_per_round' => $questionsPerRound,
            'will_end_round' => ($currentQuestion >= $questionsPerRound),
            'global_stats_count' => count(session('global_stats', [])),
            'answered_questions_count' => $answeredCount
        ]);
        
        // SYSTÈME BEST OF 3 : Vérifier si la manche est terminée (10 questions par manche)
        if ($currentQuestion >= $questionsPerRound) {
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
                    
                    // Désactiver le flag de génération avant de quitter le flux
                    session(['question_generation_pending' => false]);
                    
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
                    
                    // Désactiver le flag de génération avant de quitter le flux
                    session(['question_generation_pending' => false]);
                    
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
                'bonus_points_total' => 0,        // Réinitialiser les points bonus
                'answered_questions' => [],
                // NE PAS réinitialiser used_question_ids (historique permanent + questions de la partie)
                // NE PAS réinitialiser session_used_answers (doublons réponses interdits dans toute la partie)
            ]);
            
            // BUG FIX #7: Sauvegarder le texte de la question actuelle AVANT de la nettoyer
            $currentQuestionData = session('current_question');
            if ($currentQuestionData && isset($currentQuestionData['text'])) {
                $sessionUsedQuestionTexts = session('session_used_question_texts', []);
                if (!in_array($currentQuestionData['text'], $sessionUsedQuestionTexts)) {
                    $sessionUsedQuestionTexts[] = $currentQuestionData['text'];
                    session(['session_used_question_texts' => $sessionUsedQuestionTexts]);
                }
            }
            
            // Nettoyer la question actuelle pour éviter qu'elle réapparaisse dans la nouvelle manche
            session()->forget('current_question');
            session()->forget('question_start_time');
            session()->forget('chrono_time');
            session()->forget('buzzed');
            session()->forget('buzz_time');
            
            // Désactiver le flag de génération avant de quitter le flux
            session(['question_generation_pending' => false]);
            
            // Rediriger vers une page de transition de manche
            return redirect()->route('solo.round-result');
        }
        
        // Continuer dans la manche actuelle
        session(['current_question_number' => $currentQuestion + 1]);
        
        // Sauvegarder le texte de la question actuelle AVANT de la nettoyer
        $currentQuestionData = session('current_question');
        if ($currentQuestionData && isset($currentQuestionData['text'])) {
            $sessionUsedQuestionTexts = session('session_used_question_texts', []);
            if (!in_array($currentQuestionData['text'], $sessionUsedQuestionTexts)) {
                $sessionUsedQuestionTexts[] = $currentQuestionData['text'];
                session(['session_used_question_texts' => $sessionUsedQuestionTexts]);
            }
        }
        
        // Nettoyer la question actuelle pour forcer une nouvelle génération
        session()->forget('current_question');
        session()->forget('question_start_time');
        session()->forget('chrono_time');
        session()->forget('buzzed');
        session()->forget('buzz_time');
        
        // Désactiver le flag de génération AVANT de rediriger (même si game() le fait aussi)
        // Cela garantit que le flag est désactivé même si la redirection est annulée
        session(['question_generation_pending' => false]);
        
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
        
        foreach ($globalStats as $index => $stat) {
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
        
        // Calculer l'efficacité globale basée sur les points (utilise calculateEfficiency qui fonctionne correctement)
        $globalEfficiency = $this->calculateEfficiency($globalStats);
        
        // Calculer les statistiques de la manche qui vient de se terminer
        $roundNumber = $currentRound - 1; // La manche qui vient de se terminer
        $completedRoundStats = $this->calculateRoundStatistics($roundNumber);
        
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
        
        // TODO: VÉRIFIER ÉGALITÉ EN MANCHE 3 (Jeu Décisif) - DÉSACTIVÉ TEMPORAIREMENT
        // Système en cours de développement - nécessite implémentation complète avant activation
        // if ($roundNumber == 3 && $playerRoundsWon == 1 && $opponentRoundsWon == 1) {
        //     $playerTotalPoints = session('player_total_points', 0);
        //     $opponentTotalPoints = session('opponent_total_points', 0);
        //     
        //     if ($playerTotalPoints == $opponentTotalPoints) {
        //         Log::info("⚔️ ÉGALITÉ DÉTECTÉE EN MANCHE 3: {$playerTotalPoints}-{$opponentTotalPoints} → Jeu Décisif");
        //         return redirect()->route('solo.tiebreaker-choice');
        //     }
        // }
        
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
        
        // Sinon, afficher le résultat de la manche et continuer
        $params = [
            'round_number' => $roundNumber,        // La manche qui vient de se terminer
            'next_round' => $currentRound,         // La prochaine manche
            'player_rounds_won' => $playerRoundsWon,
            'opponent_rounds_won' => $opponentRoundsWon,
            'nb_questions' => session('nb_questions', 30),
            'niveau_adversaire' => $niveau,        // Niveau de l'adversaire
            'vies_restantes' => $viesRestantes,    // Vies restantes
            'round_efficiency' => $completedRoundStats['efficiency'], // % efficacité de LA MANCHE (CORRIGÉ!)
            'player_score' => $playerScore,        // Score joueur manche
            'opponent_score' => $opponentScore,    // Score adversaire manche
            'theme' => $theme,                     // Thème joué
            'avatar' => $avatar,                   // Avatar stratégique
            // Statistiques de LA MANCHE complétée (NOUVEAU!)
            'round_stats' => $completedRoundStats,
            // Statistiques globales (toutes manches confondues)
            'total_correct' => $totalCorrect,
            'total_incorrect' => $totalIncorrect,
            'total_unanswered' => $totalUnanswered,
            'total_questions_played' => $totalQuestionsPlayed,
            'global_efficiency' => $globalEfficiency,
            'party_efficiency' => $globalEfficiency,  // Utilise le calcul qui fonctionne
            // Métriques supplémentaires
            'efficiency_max_possible' => $efficiencyMaxPossible,
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
        
        foreach ($globalStats as $index => $stat) {
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
        
        // Calculer les points gagnés et points possibles CUMULÉS de toutes les manches
        $totalPointsEarned = 0;
        $totalPointsPossible = 0;
        foreach ($roundSummaries as $roundStats) {
            $totalPointsEarned += $roundStats['points_earned'] ?? 0;
            $totalPointsPossible += $roundStats['points_possible'] ?? 0;
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
            'party_efficiency' => $partyEfficiency,
            'next_opponent_name' => $nextOpponentName,
            'stats_metrics' => $statsMetrics,
            // Stats par manche (toutes les manches de la partie)
            'round_summaries' => $roundSummaries,
            // Points cumulés de toutes les manches
            'total_points_earned' => $totalPointsEarned,
            'total_points_possible' => $totalPointsPossible,
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
        
        foreach ($globalStats as $index => $stat) {
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
        
        // DEBUG : Log des efficacités pour comprendre le problème -30%
        Log::info("EFFICACITÉ DEBUG (Defeat):", [
            'round_efficiencies' => $roundEfficiencies,
            'party_efficiency_calculated' => $partyEfficiency,
            'global_efficiency' => $globalEfficiency,
            'total_correct' => $totalCorrect,
            'total_incorrect' => $totalIncorrect,
            'total_unanswered' => $totalUnanswered,
            'round_summaries' => $roundSummaries,
        ]);
        
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
        $bonusPoints = 0;  // NOUVEAU : Points bonus séparés
        
        foreach ($roundStats as $stat) {
            // QUESTIONS BONUS : les compter séparément
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                if (isset($stat['player_points'])) {
                    $bonusPoints += $stat['player_points'];
                }
                continue;  // Sauter pour le comptage des questions normales
            }
            
            $questions++;
            
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
        
        // FORMULE SIMPLIFIÉE : toujours 2 points max par question
        // Efficacité = (points gagnés / (questions × 2)) × 100
        $pointsPossible = $questions * 2; // 2 points max par question
        
        $efficiency = 0; // Défaut si pas de questions
        if ($pointsPossible > 0) {
            // Efficacité = (points gagnés / max possible) × 100
            $rawEfficiency = ($pointsEarned / $pointsPossible) * 100;
            // Limiter à 100% maximum, mais permettre valeurs négatives
            $rawEfficiency = min(100, $rawEfficiency);
            $efficiency = round($rawEfficiency, 1);
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
            'bonus_points' => $bonusPoints,  // NOUVEAU : Points bonus
        ];
    }

    /**
     * Calcule l'efficacité basée sur les points RÉELS selon la formule SIMPLIFIÉE :
     * Efficacité = (Points gagnés / (Questions × 2)) × 100
     * Toujours 2 points max par question pour simplifier le calcul
     */
    private function calculateEfficiency(array $stats): float
    {
        $pointsEarned = 0;
        $questionsCount = 0;
        $bonusQuestionsSkipped = 0;
        
        Log::info("🔍 DÉBUT CALCUL EFFICACITÉ - Total stats: " . count($stats));
        
        foreach ($stats as $index => $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans le calcul d'efficacité
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                $bonusQuestionsSkipped++;
                Log::info("  Q#{$index} BONUS SKIPPED: pts=" . ($stat['player_points'] ?? 'N/A'));
                continue;  // Sauter les questions bonus
            }
            
            $questionsCount++;
            
            // Utiliser les points RÉELS si disponibles, sinon fallback sur l'ancienne logique
            if (isset($stat['player_points'])) {
                $pointsBefore = $pointsEarned;
                $pointsEarned += $stat['player_points'];
                Log::info("  Q#{$questionsCount}: pts={$stat['player_points']}, buzzed=" . ($stat['player_buzzed'] ? '1' : '0') . ", correct=" . ($stat['is_correct'] ? '1' : '0') . " | Total: {$pointsBefore} → {$pointsEarned}");
            } else {
                // Fallback pour compatibilité avec anciennes données
                if ($stat['player_buzzed']) {
                    if ($stat['is_correct']) {
                        $pointsEarned += 2;
                        Log::info("  Q#{$questionsCount}: FALLBACK +2 (correct)");
                    } else {
                        $pointsEarned -= 2;
                        Log::info("  Q#{$questionsCount}: FALLBACK -2 (incorrect)");
                    }
                } else {
                    Log::info("  Q#{$questionsCount}: FALLBACK 0 (no buzz)");
                }
            }
        }
        
        // FORMULE SIMPLIFIÉE : toujours 2 points max par question
        $pointsPossible = $questionsCount * 2;
        
        Log::info("📊 RÉSULTAT CALCUL:");
        Log::info("  - Questions normales: {$questionsCount}");
        Log::info("  - Questions bonus skipped: {$bonusQuestionsSkipped}");
        Log::info("  - Points gagnés: {$pointsEarned}");
        Log::info("  - Points possibles: {$pointsPossible}");
        
        if ($pointsPossible > 0) {
            $rawEfficiency = ($pointsEarned / $pointsPossible) * 100;
            // Limiter à 100% maximum, mais permettre valeurs négatives
            $rawEfficiency = min(100, $rawEfficiency);
            $finalEfficiency = round($rawEfficiency, 1);
            Log::info("  - Efficacité RAW: {$rawEfficiency}%");
            Log::info("  - Efficacité FINALE: {$finalEfficiency}%");
            return $finalEfficiency;
        }
        
        Log::info("  - Efficacité: 0% (aucune question)");
        return 0; // 0% si aucune question
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
        $newScore = $currentScore + $pointsToRecover;
        session(['score' => $newScore]);
        
        // DEBUG BUG #4: Log AVANT modifications
        \Log::info('[BUG#4 DEBUG] cancelError() AVANT:', [
            'score_avant' => $currentScore,
            'points_to_recover' => $pointsToRecover,
            'new_score' => $newScore,
            'last_stat_buzzed' => $lastStat['player_buzzed'],
            'last_stat_correct' => $lastStat['is_correct'],
            'last_stat_points' => $lastStat['player_points'],
        ]);
        
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
        
        // DEBUG BUG #4: Log APRÈS modifications
        \Log::info('[BUG#4 DEBUG] cancelError() APRÈS:', [
            'new_score_in_session' => session('score'),
            'modified_stat_buzzed' => $globalStats[$lastIndex]['player_buzzed'],
            'modified_stat_correct' => $globalStats[$lastIndex]['is_correct'],
            'modified_stat_points' => $globalStats[$lastIndex]['player_points'],
            'global_stats_count' => count($globalStats),
        ]);
        
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
        $sessionUsedAnswers = session('session_used_answers', []);
        $sessionUsedQuestionTexts = session('session_used_question_texts', []);
        
        // Récupérer l'info de l'adversaire pour adapter la difficulté de la question bonus
        $opponentInfo = $this->getOpponentInfo($niveau);
        $opponentAge = $opponentInfo['age'] ?? null;
        $isBoss = $opponentInfo['is_boss'] ?? false;
        
        $language = $this->getUserLanguage();
        $question = $questionService->generateQuestion($theme, $niveau, 999, $usedQuestionIds, [], $sessionUsedAnswers, $sessionUsedQuestionTexts, $opponentAge, $isBoss, $language);
        
        // Enregistrer la question bonus dans l'historique permanent
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user) {
            QuestionHistory::recordQuestion($user->id, $question);
            
            // Ajouter l'ID et la réponse aux listes d'exclusion
            $usedQuestionIds[] = $question['id'];
            session(['used_question_ids' => $usedQuestionIds]);
            
            // Ajouter le texte de la question bonus aux textes utilisés
            if (isset($question['text'])) {
                $sessionUsedQuestionTexts[] = $question['text'];
                session(['session_used_question_texts' => $sessionUsedQuestionTexts]);
            }
            
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
        
        // NOUVEAU : Tracker les points bonus séparément pour affichage "X +2 / 20"
        $bonusPointsTotal = session('bonus_points_total', 0);
        session(['bonus_points_total' => $bonusPointsTotal + $points]);
        
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
        
        // Rediriger vers solo.next pour passer à la question suivante
        return redirect()->route('solo.next')->with('bonus_result', [
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
        // La manche actuelle (vient de se terminer)
        $currentRound = session('current_round', 1);
        
        // Calculer les statistiques de la manche qui vient de se terminer
        $completedRoundStats = $this->calculateRoundStatistics($currentRound);
        
        // Stocker les stats de cette manche dans round_summaries
        $roundSummaries = session('round_summaries', []);
        $roundSummaries[$currentRound] = $completedRoundStats;
        session(['round_summaries' => $roundSummaries]);
        
        Log::info("Round {$currentRound} stats saved in round_summaries", [
            'round' => $currentRound,
            'stats' => $completedRoundStats,
            'all_summaries' => $roundSummaries
        ]);
    }

    /**
     * NOUVEAU : Génère un bloc de questions (2 ou 3) progressivement
     * Appelé via AJAX pendant le countdown et le gameplay
     * Architecture progressive : bloc 1 (2q) → bloc 2-3-4 (3q chacun)
     */
    public function generateBlock(Request $request)
    {
        try {
            $count = (int) $request->input('count', 2); // 2 ou 3 questions
            $roundNumber = $request->input('round', 1);
            $blockId = $request->input('block_id', 1); // ID du bloc (1, 2, 3, 4)
            
            $questionService = new \App\Services\QuestionService();
            
            // Récupérer les paramètres de session
            $theme = session('theme', 'general');
            $niveau = session('niveau_selectionne', 1);
            $usedQuestionIds = session('used_question_ids', []);
            $sessionUsedAnswers = session('session_used_answers', []);
            $sessionUsedQuestionTexts = session('session_used_question_texts', []);
            
            // Récupérer l'info de l'adversaire pour adapter la difficulté des questions du bloc
            $opponentInfo = $this->getOpponentInfo($niveau);
            $opponentAge = $opponentInfo['age'] ?? null;
            $isBoss = $opponentInfo['is_boss'] ?? false;
            
            // Récupérer le stock progressif actuel
            $stockKey = "question_stock_round_{$roundNumber}";
            $questionStock = session($stockKey, []);
            $currentStockSize = count($questionStock);
            
            $questions = [];
            $tempUsedIds = $usedQuestionIds;
            $tempSessionUsedAnswers = $sessionUsedAnswers;
            $tempSessionUsedTexts = $sessionUsedQuestionTexts;
            
            // Ajouter les réponses déjà dans le stock pour éviter duplications
            foreach ($questionStock as $existingQ) {
                $tempUsedIds[] = $existingQ['id'];
                if (isset($existingQ['text'])) {
                    $tempSessionUsedTexts[] = $existingQ['text'];
                }
                $correctAnswer = $existingQ['answers'][$existingQ['correct_index']] ?? null;
                if ($correctAnswer) {
                    $tempSessionUsedAnswers[] = AnswerNormalizationService::normalize($correctAnswer);
                }
            }
            
            // Générer les questions du bloc
            $language = $this->getUserLanguage();
            for ($i = 0; $i < $count; $i++) {
                $questionNumber = $currentStockSize + $i + 1;
                
                $question = $questionService->generateQuestion(
                    $theme, 
                    $niveau, 
                    $questionNumber, 
                    $tempUsedIds, 
                    [],  // Pas d'historique permanent
                    $tempSessionUsedAnswers,
                    $tempSessionUsedTexts,
                    $opponentAge,
                    $isBoss,
                    $language
                );
                
                $questions[] = $question;
                
                // Mettre à jour les listes temporaires pour éviter doublons dans le bloc
                $tempUsedIds[] = $question['id'];
                if (isset($question['text'])) {
                    $tempSessionUsedTexts[] = $question['text'];
                }
                $correctAnswer = $question['answers'][$question['correct_index']] ?? null;
                if ($correctAnswer) {
                    $tempSessionUsedAnswers[] = AnswerNormalizationService::normalize($correctAnswer);
                }
            }
            
            // Ajouter au stock progressif
            $questionStock = array_merge($questionStock, $questions);
            session([$stockKey => $questionStock]);
            
            Log::info("Block generation complete", [
                'round' => $roundNumber,
                'block_id' => $blockId,
                'block_count' => count($questions),
                'total_stock' => count($questionStock),
                'session_key' => $stockKey
            ]);
            
            return response()->json([
                'success' => true,
                'count' => count($questions),
                'total_stock' => count($questionStock),
                'round' => $roundNumber,
                'block_id' => $blockId
            ]);
            
        } catch (\Exception $e) {
            Log::error("Block generation failed", [
                'error' => $e->getMessage(),
                'round' => $request->input('round', 1),
                'block_id' => $request->input('block_id', 1)
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère un batch de questions en avance pour éliminer les délais d'attente
     * Appelé via AJAX au début du countdown et entre les manches
     */
    public function generateBatch(Request $request)
    {
        try {
            $roundNumber = $request->input('round', 1);
            $questionService = new \App\Services\QuestionService();
            
            // Récupérer les paramètres de session
            $theme = session('theme', 'general');
            $niveau = session('niveau_selectionne', 1);
            $avatar = session('avatar', 'Aucun');
            $nbQuestions = session('nb_questions', 10);
            $usedQuestionIds = session('used_question_ids', []);
            $usedAnswers = session('used_answers', []);
            $sessionUsedAnswers = session('session_used_answers', []);
            $sessionUsedQuestionTexts = session('session_used_question_texts', []);
            
            // Récupérer l'info de l'adversaire pour adapter la difficulté du batch
            $opponentInfo = $this->getOpponentInfo($niveau);
            $opponentAge = $opponentInfo['age'] ?? null;
            $isBoss = $opponentInfo['is_boss'] ?? false;
            
            // Déterminer le nombre de questions à générer
            // Si avatar Magicienne, générer 11 questions (10 + 1 bonus)
            $questionsToGenerate = ($avatar === 'Magicienne') ? $nbQuestions + 1 : $nbQuestions;
            
            $questions = [];
            $tempUsedIds = $usedQuestionIds;
            $tempSessionUsedAnswers = $sessionUsedAnswers;
            $tempSessionUsedTexts = $sessionUsedQuestionTexts;
            
            // Générer toutes les questions en séquence
            $language = $this->getUserLanguage();
            for ($i = 1; $i <= $questionsToGenerate; $i++) {
                $question = $questionService->generateQuestion(
                    $theme, 
                    $niveau, 
                    $i, 
                    $tempUsedIds, 
                    [],  // Ne pas utiliser l'historique permanent pour éviter trop de conflits
                    $tempSessionUsedAnswers,
                    $tempSessionUsedTexts,
                    $opponentAge,
                    $isBoss,
                    $language
                );
                
                $questions[] = $question;
                
                // Mettre à jour les IDs temporaires pour éviter les doublons dans le batch
                $tempUsedIds[] = $question['id'];
                
                if (isset($question['text'])) {
                    $tempSessionUsedTexts[] = $question['text'];
                }
                
                $correctAnswer = $question['answers'][$question['correct_index']] ?? null;
                if ($correctAnswer) {
                    $normalizedAnswer = AnswerNormalizationService::normalize($correctAnswer);
                    $tempSessionUsedAnswers[] = $normalizedAnswer;
                }
            }
            
            // Stocker les questions pré-générées en session
            $key = "pregenerated_questions_round_{$roundNumber}";
            session([$key => $questions]);
            
            Log::info("Batch generation complete", [
                'round' => $roundNumber,
                'count' => count($questions),
                'avatar' => $avatar,
                'session_key' => $key
            ]);
            
            return response()->json([
                'success' => true,
                'count' => count($questions),
                'round' => $roundNumber
            ]);
            
        } catch (\Exception $e) {
            Log::error("Batch generation failed", [
                'error' => $e->getMessage(),
                'round' => $request->input('round', 1)
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * NOUVEAU SYSTÈME DE QUEUE : Génère les questions progressivement pendant le countdown
     * Les questions sont stockées dans une queue et le jeu pioche la première disponible
     * Appelé via AJAX au début du countdown pour démarrer la génération en arrière-plan
     */
    public function generateQueue(Request $request)
    {
        try {
            $roundNumber = $request->input('round', 1);
            
            // Récupérer les paramètres de session
            $theme = session('theme', 'general');
            $niveau = session('niveau_selectionne', 1);
            $avatar = session('avatar', 'Aucun');
            
            Log::info("Queue generation started via Node.js API", [
                'round' => $roundNumber,
                'theme' => $theme,
                'niveau' => $niveau,
                'avatar' => $avatar
            ]);
            
            // Appeler l'API Node.js pour générer les questions progressivement
            $response = Http::post('http://localhost:3000/generate-queue', [
                'theme' => $theme,
                'niveau' => $niveau,
                'avatar' => $avatar,
                'roundNumber' => $roundNumber
            ]);
            
            if (!$response->successful()) {
                throw new \Exception('Queue generation API failed: ' . $response->body());
            }
            
            $data = $response->json();
            $questions = $data['questions'] ?? [];
            
            // Stocker les questions générées dans la queue de session
            $queueKey = "question_queue_round_{$roundNumber}";
            session([$queueKey => $questions]);
            
            Log::info("Queue generation complete", [
                'round' => $roundNumber,
                'total' => $data['total'] ?? 0,
                'generated' => $data['generated'] ?? 0,
                'failed' => $data['failed'] ?? 0,
                'session_key' => $queueKey
            ]);
            
            return response()->json([
                'success' => true,
                'total' => $data['total'] ?? 0,
                'generated' => $data['generated'] ?? 0,
                'failed' => $data['failed'] ?? 0,
                'round' => $roundNumber
            ]);
            
        } catch (\Exception $e) {
            Log::error("Queue generation failed", [
                'error' => $e->getMessage(),
                'round' => $request->input('round', 1)
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Retourne le profil de compétences d'un boss selon son niveau
     * Profil = pourcentages de maîtrise dans les 9 thèmes
     */
    private function getBossProfile($niveau)
    {
        // Les 9 thèmes : Général, Cinéma, Science, Géo, Histoire, Art, Culture, Sport, Cuisine
        $profiles = [
            // Niveau 10 : Le Stratège - Tacticien, fort en logique et stratégie
            10 => [
                'name' => 'Le Stratège',
                'description' => 'Tacticien méthodique maîtrisant l\'art de la stratégie et de l\'analyse.',
                'skills' => [
                    'Général' => 75,
                    'Cinéma' => 40,
                    'Science' => 70,
                    'Géo' => 55,
                    'Histoire' => 65,
                    'Art' => 45,
                    'Culture' => 50,
                    'Sport' => 35,
                    'Cuisine' => 30
                ]
            ],
            
            // Niveau 20 : La Prodige - Intellect pur, brillante académique
            20 => [
                'name' => 'La Prodige',
                'description' => 'Brillante stratège maîtrisant l\'art de l\'analyse et de la logique pure.',
                'skills' => [
                    'Général' => 85,
                    'Cinéma' => 50,
                    'Science' => 80,
                    'Géo' => 60,
                    'Histoire' => 75,
                    'Art' => 55,
                    'Culture' => 70,
                    'Sport' => 25,
                    'Cuisine' => 30
                ]
            ],
            
            // Niveau 30 : Le Maître - Équilibré, maître de plusieurs domaines
            30 => [
                'name' => 'Le Maître',
                'description' => 'Maître équilibré possédant une connaissance approfondie dans de multiples domaines.',
                'skills' => [
                    'Général' => 80,
                    'Cinéma' => 65,
                    'Science' => 75,
                    'Géo' => 70,
                    'Histoire' => 80,
                    'Art' => 65,
                    'Culture' => 75,
                    'Sport' => 55,
                    'Cuisine' => 60
                ]
            ],
            
            // Niveau 40 : Le Sage - Sagesse ancestrale, fort en culture et histoire
            40 => [
                'name' => 'Le Sage',
                'description' => 'Sage possédant une connaissance ancestrale de l\'histoire et de la culture.',
                'skills' => [
                    'Général' => 85,
                    'Cinéma' => 60,
                    'Science' => 70,
                    'Géo' => 75,
                    'Histoire' => 90,
                    'Art' => 80,
                    'Culture' => 90,
                    'Sport' => 40,
                    'Cuisine' => 65
                ]
            ],
            
            // Niveau 50 : La Championne - Athlète d'élite, forte en sport et nutrition
            50 => [
                'name' => 'La Championne',
                'description' => 'Championne olympique alliant performance sportive et excellence nutritionnelle.',
                'skills' => [
                    'Général' => 70,
                    'Cinéma' => 45,
                    'Science' => 60,
                    'Géo' => 80,
                    'Histoire' => 50,
                    'Art' => 40,
                    'Culture' => 50,
                    'Sport' => 95,
                    'Cuisine' => 85
                ]
            ],
            
            // Niveau 60 : La Légendaire - Légende vivante, très équilibrée et puissante
            60 => [
                'name' => 'La Légendaire',
                'description' => 'Légende vivante dont la réputation dépasse les frontières de la connaissance.',
                'skills' => [
                    'Général' => 90,
                    'Cinéma' => 80,
                    'Science' => 85,
                    'Géo' => 85,
                    'Histoire' => 85,
                    'Art' => 80,
                    'Culture' => 85,
                    'Sport' => 75,
                    'Cuisine' => 75
                ]
            ],
            
            // Niveau 70 : Le Titan - Force brute intellectuelle, puissant partout
            70 => [
                'name' => 'Le Titan',
                'description' => 'Titan de la connaissance possédant une force intellectuelle impressionnante.',
                'skills' => [
                    'Général' => 92,
                    'Cinéma' => 70,
                    'Science' => 90,
                    'Géo' => 88,
                    'Histoire' => 88,
                    'Art' => 65,
                    'Culture' => 75,
                    'Sport' => 85,
                    'Cuisine' => 70
                ]
            ],
            
            // Niveau 80 : La Virtuose - Artiste d'exception, maîtresse des arts
            80 => [
                'name' => 'La Virtuose',
                'description' => 'Virtuose d\'exception maîtrisant tous les arts avec une élégance parfaite.',
                'skills' => [
                    'Général' => 85,
                    'Cinéma' => 95,
                    'Science' => 75,
                    'Géo' => 80,
                    'Histoire' => 85,
                    'Art' => 98,
                    'Culture' => 95,
                    'Sport' => 60,
                    'Cuisine' => 90
                ]
            ],
            
            // Niveau 90 : Le Génie - Scientifique brillant, esprit rationnel supérieur
            90 => [
                'name' => 'Le Génie',
                'description' => 'Génie scientifique dont l\'esprit rationnel repousse les limites de la connaissance.',
                'skills' => [
                    'Général' => 95,
                    'Cinéma' => 80,
                    'Science' => 98,
                    'Géo' => 90,
                    'Histoire' => 88,
                    'Art' => 75,
                    'Culture' => 85,
                    'Sport' => 65,
                    'Cuisine' => 80
                ]
            ],
            
            // Niveau 100 : L'Intelligence Ultime - Perfection absolue dans tous les domaines
            100 => [
                'name' => 'L\'Intelligence Ultime',
                'description' => 'Incarnation parfaite de l\'intelligence absolue, maîtrisant tous les domaines de la connaissance.',
                'skills' => [
                    'Général' => 100,
                    'Cinéma' => 98,
                    'Science' => 100,
                    'Géo' => 98,
                    'Histoire' => 98,
                    'Art' => 95,
                    'Culture' => 98,
                    'Sport' => 90,
                    'Cuisine' => 95
                ]
            ],
        ];
        
        // Si le niveau correspond à un boss, retourner son profil
        if (isset($profiles[$niveau])) {
            return $profiles[$niveau];
        }
        
        // Pour les adversaires normaux (non-boss), retourner null
        return null;
    }
    
    /**
     * Génère un fait intéressant "Le saviez-vous" basé sur la question avec OpenAI
     */
    private function generateDidYouKnow($question, $isCorrect)
    {
        try {
            $correctAnswer = $question['answers'][$question['correct_index']] ?? '';
            $questionText = $question['text'] ?? '';
            
            $prompt = "Basé sur cette question de quiz : \"{$questionText}\" avec la bonne réponse \"{$correctAnswer}\", explique POURQUOI cette réponse est correcte ou donne le contexte qui permet de comprendre la réponse. Maximum 2 phrases (120 caractères). Exemple : si la question est 'Les flamants roses naissent-ils gris ?' et la réponse 'Oui', explique : 'C'est l'ingestion de pigments caroténoïdes qui leur donne cette couleur rose caractéristique.'";
            
            $client = \OpenAI::client(config('openai.api_key'));
            
            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu es un expert en culture générale qui génère des faits intéressants courts et captivants.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 80,
                'temperature' => 0.7,
            ]);
            
            $didYouKnow = $response->choices[0]->message->content ?? 'Continuez à apprendre et vous découvrirez encore plus de choses fascinantes !';
            
            return trim($didYouKnow);
            
        } catch (\Exception $e) {
            \Log::error('Erreur génération "Le saviez-vous": ' . $e->getMessage());
            return 'Chaque question est une opportunité d\'apprendre quelque chose de nouveau !';
        }
    }

    /**
     * ============================================
     * SYSTÈME JEU DÉCISIF (TIEBREAKER)
     * ============================================
     */

    /**
     * Affiche la page de choix du mode de départage
     */
    public function tiebreakerChoice()
    {
        $params = [
            'is_multiplayer' => false, // TODO: déterminer si multijoueur
            'game_mode' => 'solo',
        ];
        
        return view('tiebreaker_choice', compact('params'));
    }

    /**
     * Option B : Départage par efficacité globale
     */
    public function tiebreakerEfficiency()
    {
        $globalStats = session('global_stats', []);
        
        // Calculer l'efficacité du joueur
        $playerEfficiency = $this->calculateEfficiency($globalStats);
        
        // Calculer les points totaux
        $playerTotalPoints = 0;
        foreach ($globalStats as $stat) {
            if (isset($stat['is_bonus']) && $stat['is_bonus']) continue;
            if (isset($stat['player_points'])) {
                $playerTotalPoints += $stat['player_points'];
            }
        }
        
        // TODO: Calculer efficacité adversaire (pour l'instant, simulation)
        $opponentEfficiency = rand(40, 80);
        $opponentTotalPoints = session('opponent_total_points', 0);
        
        // Déterminer le gagnant
        if ($playerEfficiency > $opponentEfficiency) {
            $winner = 'player';
        } elseif ($playerEfficiency < $opponentEfficiency) {
            $winner = 'opponent';
        } else {
            // Égalité d'efficacité → tiebreaker sur points totaux
            if ($playerTotalPoints > $opponentTotalPoints) {
                $winner = 'player';
            } else {
                $winner = 'opponent';
            }
        }
        
        // Rediriger vers victoire ou défaite
        if ($winner == 'player') {
            return redirect()->route('solo.victory');
        } else {
            return redirect()->route('solo.defeat');
        }
    }

    /**
     * Option A : Question Bonus décisive
     */
    public function tiebreakerBonus()
    {
        // Générer une question bonus
        $theme = session('theme', 'Général');
        $niveau = session('niveau_selectionne', 1);
        
        // TODO: Générer question via API
        $question = [
            'id' => 'tiebreaker_bonus',
            'text' => 'Question de départage',
            'answers' => ['A', 'B', 'C', 'D'],
            'correct_index' => 0,
        ];
        
        session(['tiebreaker_question' => $question]);
        
        return view('tiebreaker_bonus', [
            'question' => $question,
            'theme' => $theme,
        ]);
    }

    /**
     * Traite la réponse à la question bonus
     */
    public function tiebreakerBonusAnswer(Request $request)
    {
        $answerIndex = $request->input('answer_index', -1);
        $question = session('tiebreaker_question');
        
        $isCorrect = ($answerIndex == $question['correct_index']);
        
        // TODO: Logique des 4 scénarios
        // Pour l'instant, victoire si bonne réponse
        if ($isCorrect) {
            return redirect()->route('solo.victory');
        } else {
            return redirect()->route('solo.defeat');
        }
    }

    /**
     * Option C : Sudden Death
     */
    public function tiebreakerSuddenDeath()
    {
        // Initialiser le mode Sudden Death
        session(['sudden_death_active' => true, 'sudden_death_question_number' => 1]);
        
        // Générer première question
        $theme = session('theme', 'Général');
        
        return view('tiebreaker_sudden_death', [
            'question_number' => 1,
            'theme' => $theme,
        ]);
    }

    /**
     * Traite les réponses en Sudden Death
     */
    public function tiebreakerSuddenDeathAnswer(Request $request)
    {
        $answerIndex = $request->input('answer_index', -1);
        $question = session('sudden_death_question');
        
        $isCorrect = ($answerIndex == $question['correct_index']);
        
        // Première erreur = défaite
        if (!$isCorrect) {
            return redirect()->route('solo.defeat');
        }
        
        // TODO: Vérifier si adversaire a fait une erreur
        // Pour l'instant, continuer avec une nouvelle question
        $questionNumber = session('sudden_death_question_number', 1) + 1;
        session(['sudden_death_question_number' => $questionNumber]);
        
        return redirect()->route('solo.tiebreaker-sudden-death');
    }
}
