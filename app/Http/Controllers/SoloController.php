<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\QuestService;
use App\Services\StatisticsService;
use App\Services\AnswerNormalizationService;
use App\Services\ProfileStatsService;
use App\Services\CoinLedgerService;
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
        
        // Skill Stratège: Coéquipier - récupérer TOUS les avatars rares avec leur état
        $rareAvatarsData = [];
        $isStratege = in_array(strtolower($avatar), ['stratège', 'stratege']);
        
        if ($isStratege) {
            $user = auth()->user();
            $unlockedAvatars = [];
            if ($user) {
                $settings = (array) ($user->profile_settings ?? []);
                $unlockedAvatars = $settings['unlocked_avatars'] ?? [];
            }
            
            // Récupérer TOUS les avatars rares avec leur état (débloqué ou non)
            $avatarCatalog = \App\Services\AvatarCatalog::getStrategiques();
            foreach ($avatarCatalog as $slug => $avatarData) {
                if (($avatarData['tier'] ?? '') === 'Rare') {
                    $rareAvatarsData[$slug] = array_merge($avatarData, [
                        'slug' => $slug,
                        'unlocked' => in_array($slug, $unlockedAvatars)
                    ]);
                }
            }
        }
        
        $selectedTeammate = session('stratege_teammate', null);

        return view('solo', [
            'choix_niveau'       => $choix_niveau,
            'niveau_selectionne' => $niveau_selectionne,
            'avatar_stratégique'      => $avatar,
            'nb_questions'       => $nb_questions,
            'is_stratege'        => $isStratege,
            'rare_avatars_data'  => $rareAvatarsData,
            'selected_teammate'  => $selectedTeammate,
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

    /**
     * Skill Stratège: Sauvegarder le coéquipier sélectionné
     */
    public function setTeammate(Request $request)
    {
        $teammate = $request->input('teammate', '');
        
        // Valider que c'est un avatar rare valide ET débloqué (si non vide)
        if (!empty($teammate)) {
            $avatarCatalog = \App\Services\AvatarCatalog::getStrategiques();
            if (!isset($avatarCatalog[$teammate]) || ($avatarCatalog[$teammate]['tier'] ?? '') !== 'Rare') {
                return response()->json(['success' => false, 'message' => 'Avatar invalide']);
            }
            
            // Vérifier que l'avatar est débloqué pour cet utilisateur
            $user = auth()->user();
            if ($user) {
                $settings = (array) ($user->profile_settings ?? []);
                $unlockedAvatars = $settings['unlocked_avatars'] ?? [];
                if (!in_array($teammate, $unlockedAvatars)) {
                    return response()->json(['success' => false, 'message' => 'Avatar verrouillé']);
                }
            }
        }
        
        session(['stratege_teammate' => $teammate ?: null]);
        
        return response()->json(['success' => true, 'teammate' => $teammate]);
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
            'game_mode'          => 'solo',  // IMPORTANT: Définir le mode de jeu Solo
            'niveau_selectionne' => $niveau,
            'nb_questions'       => $nbQuestions,
            'theme'              => $theme,
            'match_uuid'         => uniqid('match_', true), // Identifiant unique de match pour isolation sessionStorage
            'current_question_number' => 1,
            'current_round' => 1,              // Manche actuelle (1, 2 ou 3)
            'player_rounds_won' => 0,          // Manches gagnées par le joueur
            'opponent_rounds_won' => 0,        // Manches gagnées par l'adversaire
            'score' => 0,                      // Score de la manche actuelle
            'opponent_score' => 0,             // Score adversaire de la manche actuelle
            'answered_questions' => [],
            'used_question_ids' => $permanentUsedQuestionIds,  // HISTORIQUE PERMANENT (DB ou session)
            'used_answers' => $permanentUsedAnswers,           // RÉPONSES PERMANENTES (DB ou session)
            'session_used_answers' => [],      // Réponses CORRECTES utilisées dans cette partie seulement (réinitialisé chaque partie)
            'session_used_all_answers' => [],  // TOUTES les réponses (correctes + distracteurs) pour éviter redondance complète
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

        $avatarSkillsData = $this->getAvatarSkills($avatar);
        $params = [
            'theme'           => $theme,
            'theme_icon'      => $themeIcons[$theme] ?? '❓',
            'avatar'          => $avatar,
            'avatar_skills'   => $this->getAvatarSkillsSimple($avatar),
            'avatar_skills_full' => $avatarSkillsData,
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
            // Données du Visionnaire pour preview des questions
            'visionnaire_next_question' => session('visionnaire_next_question'),
            'visionnaire_previews_remaining' => session('visionnaire_previews_remaining', 5),
            // Coéquipier Stratège
            'teammate_name' => $this->getTeammateName(),
            'teammate_skill_icon' => $this->getTeammateSkillIcon(),
        ];

        return view('resume', compact('params'));
    }

    public function resume()
    {
        // IMPORTANT : Définir le mode de jeu comme Solo pour éviter les éléments multijoueur
        session(['game_mode' => 'solo']);
        
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
        
        $avatarSkillsData = $this->getAvatarSkills($avatar);
        $params = [
            'theme'           => $theme,
            'theme_icon'      => $themeIcons[$theme] ?? '❓',
            'avatar'          => $avatar,
            'avatar_image'    => $strategicAvatarPath,
            'avatar_skills'   => $this->getAvatarSkillsSimple($avatar),
            'avatar_skills_full' => $avatarSkillsData,
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
            // Données du Visionnaire pour preview des questions
            'visionnaire_next_question' => session('visionnaire_next_question'),
            'visionnaire_previews_remaining' => session('visionnaire_previews_remaining', 5),
            // Coéquipier Stratège
            'teammate_name' => $this->getTeammateName(),
            'teammate_skill_icon' => $this->getTeammateSkillIcon(),
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
        // IMPORTANT : Définir le mode de jeu comme Solo pour éviter les éléments multijoueur
        session(['game_mode' => 'solo']);
        
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
        $sessionUsedAnswers = session('session_used_answers', []); // Réponses CORRECTES de cette partie
        $sessionUsedAllAnswers = session('session_used_all_answers', []); // TOUTES les réponses (correctes + distracteurs)
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
                $question = $questionService->generateQuestion($theme, $niveau, $currentQuestion, $usedQuestionIds, [], $sessionUsedAllAnswers, $sessionUsedQuestionTexts, $opponentAge, $isBoss, $language);
                
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
            
            // Ajouter TOUTES les réponses (correctes + distracteurs) pour éviter toute redondance
            if (isset($question['answers']) && is_array($question['answers'])) {
                $sessionUsedAllAnswers = session('session_used_all_answers', []);
                foreach ($question['answers'] as $answer) {
                    if ($answer && trim($answer) !== '') {
                        $normalized = AnswerNormalizationService::normalize($answer);
                        if (!in_array($normalized, $sessionUsedAllAnswers)) {
                            $sessionUsedAllAnswers[] = $normalized;
                        }
                    }
                }
                session(['session_used_all_answers' => $sessionUsedAllAnswers]);
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
            'avatar_skills_full' => $this->getAvatarSkills($avatar),
            'theme' => $theme,
            'niveau' => $niveau,
            'current_round' => session('current_round', 1),
            'total_rounds' => session('total_rounds', 5),
            'opponent_info' => $opponentInfo,
            'mode' => 'solo',
        ];
        
        session(['game_state' => [
            'mode' => 'solo',
            'theme' => $theme,
            'total_questions' => $nbQuestions,
            'niveau' => $niveau,
            'current_question' => $currentQuestion,
            'current_round' => session('current_round', 1),
            'player_score' => session('score', 0),
            'opponent_score' => session('opponent_score', 0),
            'player_rounds_won' => session('player_rounds_won', 0),
            'opponent_rounds_won' => session('opponent_rounds_won', 0),
            'opponent_info' => $opponentInfo,
        ]]);
        session(['unified_current_question' => $question]);
        session(['unified_question_number' => $currentQuestion]);
        
        return view('game_question', compact('params'));
    }

    public function buzz(Request $request)
    {
        // Utiliser le buzz_time envoyé par le client (plus précis)
        $buzzTime = $request->input('buzz_time', 0);
        session(['buzz_time' => $buzzTime]);
        session(['buzzed' => true]);
        
        // Retourner du JSON - le client gère la redirection avec son propre buzz_time
        return response()->json([
            'success' => true
        ]);
    }
    
    public function useSkill(Request $request)
    {
        $skillId = $request->input('skill_id');
        $avatar = session('avatar', 'Aucun');
        $avatarSkills = $this->getAvatarSkills($avatar);
        
        // Vérifier que le skill existe pour cet avatar
        $skillData = null;
        foreach ($avatarSkills['skills'] ?? [] as $skill) {
            if ($skill['id'] === $skillId) {
                $skillData = $skill;
                break;
            }
        }
        
        if (!$skillData) {
            return response()->json(['success' => false, 'error' => 'Skill non trouvé']);
        }
        
        // Vérifier si le skill est déjà utilisé (pour les skills à usage unique)
        $usedSkills = session('used_skills', []);
        $maxUses = $skillData['uses_per_match'] ?? 1;
        
        if ($maxUses > 0) {
            $usesCount = count(array_filter($usedSkills, fn($s) => strpos($s, $skillId) === 0));
            if ($usesCount >= $maxUses) {
                return response()->json(['success' => false, 'error' => 'Skill déjà utilisé']);
            }
        }
        
        // Traiter le skill selon son type
        $result = $this->processSkillActivation($skillId, $skillData);
        
        // Marquer le skill comme utilisé
        $usedSkills[] = $skillId;
        session(['used_skills' => $usedSkills]);
        
        return response()->json([
            'success' => true, 
            'skill_id' => $skillId,
            'result' => $result,
            'used_skills' => $usedSkills
        ]);
    }
    
    private function processSkillActivation($skillId, $skillData)
    {
        $question = session('current_question');
        $result = ['type' => $skillData['type']];
        
        // Vérifier que la question existe et a les données nécessaires
        if (!$question || !isset($question['answers']) || !is_array($question['answers'])) {
            \Log::warning('[SKILL] No valid question in session for skill activation', ['skill_id' => $skillId]);
            $result['effect'] = 'no_question';
            $result['message'] = 'Question non disponible';
            return $result;
        }
        
        // Récupérer l'index correct de manière sécurisée
        $correctIndex = $question['correct_index'] ?? 0;
        $answerCount = count($question['answers']);
        
        // Sécurité: s'assurer que correct_index est valide
        if ($correctIndex < 0 || $correctIndex >= $answerCount) {
            \Log::warning('[SKILL] Invalid correct_index', [
                'skill_id' => $skillId,
                'correct_index' => $correctIndex,
                'answer_count' => $answerCount
            ]);
            $correctIndex = 0;
        }
        
        switch ($skillId) {
            // 🔵 RARE SKILLS
            case 'illuminate_numbers':
                // Mathématicien: Illumine la bonne réponse si elle contient un chiffre
                $correctAnswer = $question['answers'][$correctIndex] ?? '';
                $hasNumber = preg_match('/\d/', $correctAnswer);
                
                if ($hasNumber) {
                    $result['illuminate_index'] = $correctIndex;
                    $result['effect'] = 'highlight';
                } else {
                    $result['illuminate_index'] = -1;
                    $result['effect'] = 'no_number';
                    $result['message'] = 'Aucun chiffre dans la bonne réponse';
                }
                break;
                
            case 'acidify_error':
                // Scientifique: Marque 2 mauvaises réponses en rouge (après avoir buzzé)
                // Vérifier que le joueur a buzzé (validation côté serveur)
                $hasBuzzed = session('player_has_buzzed', false);
                if (!$hasBuzzed) {
                    $result['effect'] = 'requires_buzz';
                    $result['message'] = 'Vous devez buzzer avant d\'utiliser ce skill!';
                    break;
                }
                
                $wrongIndices = [];
                for ($i = 0; $i < $answerCount; $i++) {
                    if ($i !== $correctIndex) {
                        $wrongIndices[] = $i;
                    }
                }
                // Choisir 2 mauvaises réponses aléatoires à acidifier
                if (count($wrongIndices) >= 2) {
                    shuffle($wrongIndices);
                    $acidifiedIndices = array_slice($wrongIndices, 0, 2);
                    $result['acidify_indices'] = $acidifiedIndices;
                    $result['acidify_index'] = $acidifiedIndices[0]; // Compatibilité rétroactive
                    $result['effect'] = 'acidify';
                } elseif (!empty($wrongIndices)) {
                    $result['acidify_indices'] = $wrongIndices;
                    $result['acidify_index'] = $wrongIndices[0]; // Compatibilité rétroactive
                    $result['effect'] = 'acidify';
                }
                break;
                
            case 'see_opponent_choice':
                // Explorateur: Montre la réponse la plus choisie par l'adversaire
                // En mode Solo, on simule avec la bonne réponse 60% du temps
                $showCorrect = (rand(1, 100) <= 60);
                
                if ($showCorrect) {
                    $result['popular_index'] = $correctIndex;
                } else {
                    // Choisir une mauvaise réponse
                    $wrongIndices = [];
                    for ($i = 0; $i < $answerCount; $i++) {
                        if ($i !== $correctIndex) {
                            $wrongIndices[] = $i;
                        }
                    }
                    $result['popular_index'] = !empty($wrongIndices) ? $wrongIndices[array_rand($wrongIndices)] : 0;
                }
                $result['effect'] = 'popular';
                break;
                
            case 'block_attack':
                // Défenseur: Passif - bloque automatiquement
                $result['effect'] = 'shield_ready';
                session(['shield_active' => true]);
                break;
                
            // 🟣 ÉPIQUE SKILLS
            case 'history_corrects':
                // Historien: L'histoire corrige - +2 points si mauvaise réponse
                $result['effect'] = 'history_corrects';
                $result['points_bonus'] = 2;
                $result['message'] = 'L\'histoire corrige! +2 points';
                break;
                
            case 'knowledge_without_time':
                // Historien: Le savoir sans temps - +1 point si bonne réponse sans buzz
                $result['effect'] = 'knowledge_without_time';
                $result['points_bonus'] = 1;
                $result['message'] = 'Le savoir sans temps! +1 point';
                break;
                
            case 'invert_answers':
                // Comédien: Inverse visuellement une bonne et mauvaise réponse (trompeur pour adversaire)
                $wrongIndices = [];
                for ($i = 0; $i < $answerCount; $i++) {
                    if ($i !== $correctIndex) {
                        $wrongIndices[] = $i;
                    }
                }
                $result['invert_correct'] = $correctIndex;
                $result['invert_wrong'] = !empty($wrongIndices) ? $wrongIndices[array_rand($wrongIndices)] : 0;
                $result['effect'] = 'invert';
                break;
                
            case 'shuffle_answers':
                // Challenger: Les réponses changent de position
                $result['effect'] = 'shuffle';
                $result['interval'] = 1000; // 1 seconde
                break;
                
            case 'reduce_time':
                // Challenger: Réduit le chrono des adversaires
                $result['effect'] = 'reduce_time';
                $result['reduction'] = 2; // -2 secondes pour les adversaires
                break;
                
            // 🟡 LÉGENDAIRE SKILLS
            case 'ai_suggestion':
                // IA Junior: 90% de chance d'illuminer la bonne réponse
                $isCorrect = (rand(1, 100) <= 90);
                
                if ($isCorrect) {
                    $result['suggestion_index'] = $correctIndex;
                } else {
                    // 20% de chance d'illuminer une mauvaise réponse
                    $wrongIndices = [];
                    for ($i = 0; $i < $answerCount; $i++) {
                        if ($i !== $correctIndex) {
                            $wrongIndices[] = $i;
                        }
                    }
                    $result['suggestion_index'] = !empty($wrongIndices) ? $wrongIndices[array_rand($wrongIndices)] : 0;
                }
                $result['effect'] = 'ai_suggest';
                break;
                
            case 'eliminate_two':
                // IA Junior: Élimine 2 mauvaises réponses
                $wrongIndices = [];
                for ($i = 0; $i < $answerCount; $i++) {
                    if ($i !== $correctIndex) {
                        $wrongIndices[] = $i;
                    }
                }
                shuffle($wrongIndices);
                $result['eliminated_indices'] = array_slice($wrongIndices, 0, min(2, count($wrongIndices)));
                $result['effect'] = 'eliminate';
                break;
                
            case 'extra_reflection':
                // Sprinteur: +3 secondes de réflexion
                $result['extra_seconds'] = 3;
                $result['effect'] = 'time_bonus';
                break;
                
            case 'premonition':
            case 'preview_questions':
                // Visionnaire: Voir un résumé thématique de la question suivante (👁️ 5/5 → 4/5 → ...)
                $currentRound = session('current_round', 1);
                $stockKey = "progressive_question_stock_round_{$currentRound}";
                $questionStock = session($stockKey, []);
                $currentQuestionNumber = session('current_question_number', 1);
                $questionsPerRound = 10; // 10 questions par manche standard
                
                // Récupérer le compteur de previews restantes (5 max par match)
                $previewsRemaining = session('visionnaire_previews_remaining', 5);
                
                if ($previewsRemaining <= 0) {
                    $result['effect'] = 'no_previews';
                    $result['message'] = 'Plus de previews disponibles!';
                    break;
                }
                
                // Condition: Ne peut pas voir la Q1 de la manche suivante (Q10 = dernière question)
                // Sauf en manche Ultime où il peut voir les questions restantes
                $isUltimateRound = ($currentRound >= 10); // Manche Ultime
                $isLastQuestionOfRound = ($currentQuestionNumber >= $questionsPerRound);
                
                if ($isLastQuestionOfRound && !$isUltimateRound) {
                    $result['effect'] = 'no_question';
                    $result['message'] = 'Pas de vision disponible pour la dernière question de la manche';
                    $result['is_disabled'] = true;
                    break;
                }
                
                // Extraire LA PROCHAINE question seulement
                $nextQuestionIndex = $currentQuestionNumber; // Index de la prochaine question (0-based)
                $previewQuestions = array_slice($questionStock, $nextQuestionIndex, 1);
                
                if (empty($previewQuestions)) {
                    $result['effect'] = 'no_question';
                    $result['message'] = 'Aucune question suivante disponible';
                    break;
                }
                
                $nextQuestion = $previewQuestions[0];
                $questionText = $nextQuestion['text'] ?? $nextQuestion['question_text'] ?? '';
                $theme = $nextQuestion['theme'] ?? '';
                
                // Générer un résumé thématique au lieu de la question complète
                $thematicHint = $this->generateThematicHint($questionText, $theme);
                
                // Stocker en session pour la page resume
                session([
                    'visionnaire_next_question' => [
                        'hint' => $thematicHint,
                        'theme' => $theme
                    ],
                    'visionnaire_previews_remaining' => $previewsRemaining - 1
                ]);
                
                $result['preview'] = [
                    'hint' => $thematicHint,
                    'text' => $thematicHint, // Compatibilité rétroactive
                    'theme' => $theme
                ];
                $result['previews_remaining'] = $previewsRemaining - 1;
                $result['effect'] = 'preview';
                break;
                
            case 'secure_answer':
            case 'lock_correct':
                // Visionnaire: Sur 2 pts, bonne réponse seule cliquable avec surbrillance
                $playerScore = session('score', 0);
                $result['effect'] = 'secure_answer';
                
                // Vérifie si le joueur a exactement 2 points
                if ($playerScore == 2) {
                    $result['lock_index'] = $correctIndex;
                    $result['highlight_all'] = true;
                    $result['fade_on_wrong_click'] = true;
                    $result['message'] = 'Seule la bonne réponse est cliquable';
                } else {
                    $result['lock_index'] = -1;
                    $result['message'] = 'Nécessite exactement 2 points';
                }
                break;
                
            // 🟡 STRATÈGE SKILLS
            case 'coin_bonus':
                // Stratège: PASSIF - +25% pièces d'intelligence et de compétence (géré dans CoinLedgerService)
                $result['effect'] = 'passive_active';
                $result['message'] = 'Bonus +25% pièces actif sur victoire';
                break;
                
            case 'create_team':
                // Stratège: Permet d'ajouter un Avatar rare comme coéquipier
                $result['effect'] = 'create_team';
                $result['available_avatars'] = $this->getAvailableRareAvatars();
                $result['message'] = 'Sélectionnez un Avatar rare comme coéquipier';
                break;
                
            case 'avatar_discount':
                // Stratège: PASSIF - Réductions par tier (géré dans boutique)
                $result['effect'] = 'passive_active';
                $result['discount'] = ['Rare' => 40, 'Épique' => 30, 'Légendaire' => 20];
                $result['message'] = 'Réduction avatars: Rare -40%, Épique -30%, Légendaire -20%';
                break;
                
            // 🟡 SPRINTEUR SKILLS  
            case 'faster_buzz':
                // Sprinteur: Les 5 premières questions affichent le buzzer à 0.75s du vrai temps (PASSIF)
                $result['effect'] = 'passive_active';
                $result['display_time'] = 0.75; // secondes
                $result['questions_affected'] = 5;
                $result['message'] = 'Buzzer affiché à 0.75s du vrai temps (5 premières questions)';
                break;
            
            case 'time_bonus':
                // Sprinteur: +3 secondes de réflexion (1x par manche)
                $result['effect'] = 'time_bonus';
                $result['extra_seconds'] = 3;
                $result['message'] = '+3 secondes de réflexion';
                break;
                
            case 'skill_recharge':
                // Sprinteur: Réactive tous les skills après chaque manche (PASSIF)
                $result['effect'] = 'passive_active';
                $result['message'] = 'Skills réactivés automatiquement après chaque manche';
                break;
                
            // 🟣 MAGICIENNE SKILLS
            case 'cancel_error':
                // Magicienne: Annule une erreur
                $result['effect'] = 'cancel_error';
                session(['cancel_error_available' => true]);
                $result['message'] = 'Erreur annulée! Score préservé';
                break;
                
            case 'bonus_question':
                // Magicienne: Question bonus (géré par redirection)
                $result['effect'] = 'redirect';
                $result['redirect_to'] = route('solo.bonus-question');
                break;
                
            // 🎭 COMÉDIEN SKILLS
            case 'fake_score':
                // Comédien: Affiche un score inférieur aux autres (mode Maître)
                $result['effect'] = 'fake_score';
                // Réduire visuellement le score de 1-3 points aléatoirement
                $fakeReduction = rand(1, 3);
                $realScore = session('player_score', 0);
                $fakeScore = max(0, $realScore - $fakeReduction);
                session(['fake_score_active' => true, 'fake_score_value' => $fakeScore]);
                $result['fake_score'] = $fakeScore;
                $result['real_score'] = $realScore;
                $result['message'] = 'Score trompeur activé! Les autres voient ' . $fakeScore . ' pts';
                break;
                
            // 🤖 IA JUNIOR - Skill manquant
            case 'replay':
                // IA Junior: Rejouer une réponse une fois
                $result['effect'] = 'replay';
                session(['replay_available' => true]);
                $result['message'] = 'Vous pouvez rejouer une mauvaise réponse!';
                break;
                
            // 🌟 VISIONNAIRE - Forteresse  
            case 'fortress':
            case 'counter_challenger':
                // Visionnaire: Immunité contre les attaques du Challenger
                $result['effect'] = 'fortress';
                session(['shuffle_immunity' => true]);
                session(['reduce_time_immunity' => true]);
                $result['message'] = '🏰 Forteresse activée - Immunité contre Challenger';
                break;
                
            default:
                $result['effect'] = 'unknown';
        }
        
        return $result;
    }
    
    private function generateThematicHint(string $questionText, string $theme): string
    {
        $questionLower = mb_strtolower($questionText);
        
        if (str_contains($questionLower, 'lumière') || str_contains($questionLower, 'soleil') || str_contains($questionLower, 'distance')) {
            if (str_contains($questionLower, 'espace') || str_contains($questionLower, 'planète') || str_contains($questionLower, 'soleil')) {
                return "l'espace et la vitesse de la lumière.";
            }
        }
        
        if (str_contains($questionLower, 'métal') || str_contains($questionLower, 'électricité') || str_contains($questionLower, 'conduit')) {
            return "propriétés électriques des métaux.";
        }
        
        if (str_contains($questionLower, 'fleuve') && str_contains($questionLower, 'europe')) {
            return "géographie européenne et aux fleuves.";
        }
        
        if (str_contains($questionLower, 'soleil') && (str_contains($questionLower, 'lève') || str_contains($questionLower, 'endroit'))) {
            return "idée reçue liée à l'astronomie et aux saisons.";
        }
        
        if (str_contains($questionLower, 'îles') || str_contains($questionLower, 'île')) {
            if (str_contains($questionLower, 'europe') || str_contains($questionLower, 'pays')) {
                return "pays européen fortement lié aux îles.";
            }
        }
        
        if (str_contains($questionLower, 'os') || str_contains($questionLower, 'corps') || str_contains($questionLower, 'naissance')) {
            return "structure humaine et son évolution avec l'âge.";
        }
        
        if (str_contains($questionLower, 'jean') || str_contains($questionLower, 'vêtement') || str_contains($questionLower, 'inventé')) {
            return "l'histoire d'un vêtement devenu universel.";
        }
        
        if (!empty($theme)) {
            $themeHints = [
                'Science' => "un concept scientifique.",
                'Géographie' => "géographie et localisation.",
                'Histoire' => "un fait historique.",
                'Sport' => "le monde du sport.",
                'Art' => "art et culture.",
                'Cinéma' => "le monde du cinéma.",
                'Musique' => "le monde de la musique.",
                'Littérature' => "le monde littéraire.",
                'Nature' => "la nature et l'environnement.",
                'Technologie' => "la technologie.",
            ];
            
            return $themeHints[$theme] ?? "thème : {$theme}.";
        }
        
        return "question de culture générale.";
    }
    
    /**
     * Récupérer le coéquipier effectif du Stratège (avec auto-sélection si nécessaire)
     * Si aucun teammate n'est sélectionné mais qu'un avatar Rare est débloqué,
     * le premier Rare débloqué devient automatiquement le coéquipier
     */
    private function getEffectiveTeammate(): ?string
    {
        // Vérifier si un teammate est déjà sélectionné en session
        $teammate = session('stratege_teammate');
        if ($teammate) {
            return $teammate;
        }
        
        // Sinon, chercher le premier avatar Rare débloqué
        $user = auth()->user();
        if (!$user) {
            return null;
        }
        
        $settings = (array) ($user->profile_settings ?? []);
        $unlockedAvatars = $settings['unlocked_avatars'] ?? [];
        
        // Liste ordonnée des avatars Rares (ordre de priorité pour auto-sélection)
        $rareAvatars = ['mathematicien', 'scientifique', 'explorateur', 'defenseur'];
        
        foreach ($rareAvatars as $rareSlug) {
            if (in_array($rareSlug, $unlockedAvatars)) {
                // Auto-sélectionner ce Rare comme coéquipier
                session(['stratege_teammate' => $rareSlug]);
                return $rareSlug;
            }
        }
        
        // Aucun avatar Rare débloqué
        return null;
    }
    
    /**
     * Vérifier si l'utilisateur a au moins un avatar Rare débloqué
     */
    private function hasUnlockedRareAvatar(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        
        $settings = (array) ($user->profile_settings ?? []);
        $unlockedAvatars = $settings['unlocked_avatars'] ?? [];
        
        $rareAvatars = ['mathematicien', 'scientifique', 'explorateur', 'defenseur'];
        
        foreach ($rareAvatars as $rareSlug) {
            if (in_array($rareSlug, $unlockedAvatars)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Récupérer le nom complet du coéquipier Stratège depuis la session
     */
    private function getTeammateName(): string
    {
        $teammate = $this->getEffectiveTeammate();
        if (!$teammate) {
            return 'Aucun';
        }
        
        // Mapping des slugs vers les noms complets
        $slugToName = [
            'mathematicien' => 'Mathématicien',
            'scientifique' => 'Scientifique',
            'explorateur' => 'Explorateur',
            'defenseur' => 'Défenseur',
        ];
        
        return $slugToName[strtolower($teammate)] ?? ucfirst($teammate);
    }
    
    /**
     * Récupérer l'icône du skill du coéquipier Stratège
     */
    private function getTeammateSkillIcon(): string
    {
        $teammate = $this->getEffectiveTeammate();
        if (!$teammate) {
            return '👥';
        }
        
        // Mapping des slugs vers les icônes des skills principaux
        $slugToSkillIcon = [
            'mathematicien' => '🔢',  // illuminate_numbers
            'scientifique' => '🧪',   // acidify_error
            'explorateur' => '👁️',    // see_opponent_choice
            'defenseur' => '🛡️',      // shield
        ];
        
        return $slugToSkillIcon[strtolower($teammate)] ?? '👥';
    }
    
    private function getAvailableRareAvatars()
    {
        // Liste des avatars rares disponibles pour le mode équipe
        $allAvatars = $this->getAvatarSkills();
        $rareAvatars = [];
        
        foreach ($allAvatars as $name => $data) {
            if (($data['rarity'] ?? '') === 'rare') {
                $rareAvatars[] = [
                    'name' => $name,
                    'icon' => $data['icon'] ?? '👤',
                    'skills' => array_map(function($skill) {
                        return [
                            'id' => $skill['id'],
                            'name' => $skill['name'],
                            'icon' => $skill['icon'],
                            'description' => $skill['description']
                        ];
                    }, $data['skills'] ?? [])
                ];
            }
        }
        
        return $rareAvatars;
    }
    
    private function generateQuestionHint($question, $correctIndex = null)
    {
        // Générer un indice basé sur la question
        if ($correctIndex === null) {
            $correctIndex = $question['correct_index'] ?? 0;
        }
        $correctAnswer = $question['answers'][$correctIndex] ?? '';
        
        // Créer un indice simple (première lettre, longueur, etc.)
        $hints = [];
        
        if (strlen($correctAnswer) > 0) {
            $hints[] = "La réponse commence par \"" . mb_substr($correctAnswer, 0, 1) . "\"";
        }
        
        $wordCount = str_word_count($correctAnswer);
        if ($wordCount > 1) {
            $hints[] = "La réponse contient {$wordCount} mots";
        }
        
        $length = mb_strlen($correctAnswer);
        $hints[] = "La réponse contient {$length} caractères";
        
        return $hints[array_rand($hints)];
    }
    
    private function renderAnswerView($playerBuzzed, $buzzTime = null, $featherUsed = false)
    {
        // Récupérer la question actuelle
        $question = session('current_question');
        $currentQuestion = session('current_question_number');
        $nbQuestions = session('nb_questions', 30);
        $avatar = session('avatar', 'Aucun');
        $niveau = session('niveau_selectionne', 1);
        
        // Calculer temps pour répondre (10 secondes de base)
        $answerTime = 10;
        
        // NOUVEAU : Calculer potential_points en simulant le comportement de l'adversaire
        // Si Plume utilisée (featherUsed), le joueur peut gagner +1 point max
        $potentialPoints = $featherUsed ? 1 : 0;  // Par défaut : 0 si pas buzzé, 1 si Plume
        
        if ($playerBuzzed) {
            // Simuler le comportement de l'adversaire pour déterminer qui est le plus rapide
            $questionService = new \App\Services\QuestionService();
            $playerScore = session('score', 0);
            $opponentScore = session('opponent_score', 0);
            $chronoTime = session('chrono_time', 8);
            
            // Skill Challenger: Chrono Réduit - réduire le chrono de l'adversaire de 2 sec
            $reduceTimeActive = session('reduce_time_active', false);
            if ($reduceTimeActive) {
                $reduction = session('reduce_time_reduction', 2);
                $chronoTime = max(3, $chronoTime - $reduction); // Minimum 3 sec pour l'IA
            }
            
            $opponentBehavior = $questionService->simulateOpponentBehavior(
                $niveau,
                $question,
                $playerBuzzed,
                $buzzTime,
                $chronoTime,
                $playerScore,
                $opponentScore,
                $currentQuestion
            );
            
            // Calculer les points potentiels (si le joueur répond correctement)
            // +2 si le joueur est premier, +1 si le joueur est deuxième
            $potentialPoints = $opponentBehavior['is_faster'] ? 1 : 2;
        }
        
        // Mathématicien: illuminate_numbers - le skill est disponible si pas encore utilisé
        $usedSkills = session('used_skills', []);
        $illuminateSkillAvailable = false;
        
        // Récupérer le coéquipier du Stratège
        $teammate = session('stratege_teammate');
        $isStratege = in_array(strtolower($avatar), ['stratège', 'stratege']);
        $teammateIsMathematicien = $teammate && in_array(strtolower($teammate), ['mathematicien', 'mathématicien']);
        $teammateIsExplorateur = $teammate && strtolower($teammate) === 'explorateur';
        
        // Vérifier si l'avatar principal OU le coéquipier est Mathématicien
        $hasMathSkill = ($avatar === 'Mathématicien') || ($isStratege && $teammateIsMathematicien);
        
        if ($hasMathSkill && !in_array('illuminate_numbers', $usedSkills)) {
            $correctIndex = $question['correct_index'] ?? 0;
            $correctAnswer = $question['answers'][$correctIndex] ?? '';
            // Vérifie si la bonne réponse contient un chiffre
            if (preg_match('/\d/', $correctAnswer)) {
                $illuminateSkillAvailable = true;  // Skill disponible, mais pas encore activé
            }
        }
        
        // Explorateur: see_opponent_choice - stocker le choix de l'adversaire et vérifier si skill disponible
        $opponentAnswerChoice = $opponentBehavior['answer_choice'] ?? null;
        session(['opponent_answer_choice' => $opponentAnswerChoice]);
        
        // Vérifier si l'avatar principal OU le coéquipier est Explorateur
        $hasExplorateurSkill = ($avatar === 'Explorateur') || ($isStratege && $teammateIsExplorateur);
        
        $seeOpponentSkillAvailable = false;
        if ($hasExplorateurSkill && !in_array('see_opponent_choice', $usedSkills)) {
            $seeOpponentSkillAvailable = true;
        }
        
        $params = [
            'question' => $question,
            'current_question' => $currentQuestion,
            'total_questions' => $nbQuestions,
            'score' => session('score', 0),
            'opponent_score' => session('opponent_score', 0),
            'answer_time' => $answerTime,
            'buzz_time' => $buzzTime,
            'player_buzzed' => $playerBuzzed,
            'potential_points' => $potentialPoints,  // NOUVEAU : Points potentiels
            'current_round' => session('current_round', 1),
            'total_rounds' => session('total_rounds', 5),
            'avatar' => $avatar,  // Avatar stratégique pour les skills
            'avatar_skills' => $this->getAvatarSkillsSimple($avatar),  // Skills de l'avatar (descriptions)
            'avatar_skills_full' => $this->getAvatarSkills($avatar),  // Structure complète des skills
            'used_skills' => session('used_skills', []),  // Skills déjà utilisés dans la partie
            'correct_index' => $question['correct_index'] ?? -1,  // Index de la bonne réponse pour les sons
            'illuminate_skill_available' => $illuminateSkillAvailable,  // Mathématicien skill: disponible mais pas activé
            'see_opponent_skill_available' => $seeOpponentSkillAvailable,  // Explorateur skill: disponible
            'opponent_answer_choice' => $opponentAnswerChoice,  // Choix de l'adversaire (pour skill Explorateur)
        ];
        
        return view('game_answer', compact('params'));
    }

    public function answer(Request $request)
    {
        // Si c'est un GET, afficher la page de réponse
        if ($request->isMethod('get')) {
            $buzzTime = $request->query('buzz_time', session('buzz_time', 0));
            
            // Vérifier si le skill Plume (feather) est utilisé (no_buzz + feather=1)
            $featherUsed = $request->query('feather') === '1' && $request->query('no_buzz') === '1';
            
            // Si feather est utilisé, le joueur n'a pas buzzé mais peut répondre
            if ($featherUsed) {
                $playerBuzzed = false;
            } else {
                $playerBuzzed = session('buzzed', false) || $request->query('buzz_winner') === 'player';
            }
            
            return $this->renderAnswerView($playerBuzzed, $buzzTime, $featherUsed);
        }
        
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
        
        // Skill Challenger: Chrono Réduit - réduire le chrono de l'adversaire de 2 sec
        $reduceTimeActive = session('reduce_time_active', false);
        $effectiveChronoTime = $chronoTime;
        if ($reduceTimeActive) {
            $reduction = session('reduce_time_reduction', 2);
            $effectiveChronoTime = max(3, $chronoTime - $reduction); // Minimum 3 sec pour l'IA
            
            // Décrémenter le compteur de questions restantes
            $questionsLeft = session('reduce_time_questions_left', 0);
            $questionsLeft--;
            session(['reduce_time_questions_left' => $questionsLeft]);
            
            // Désactiver le skill si plus de questions restantes
            if ($questionsLeft <= 0) {
                session(['reduce_time_active' => false]);
                \Log::info('[CHALLENGER] Skill reduce_time épuisé');
            }
        }
        
        // Skill Challenger: Shuffle Answers - décrémenter le compteur
        $shuffleAnswersActive = session('shuffle_answers_active', false);
        if ($shuffleAnswersActive) {
            $shuffleQuestionsLeft = session('shuffle_answers_questions_left', 0);
            $shuffleQuestionsLeft--;
            session(['shuffle_answers_questions_left' => $shuffleQuestionsLeft]);
            
            // Désactiver le skill si plus de questions restantes
            if ($shuffleQuestionsLeft <= 0) {
                session(['shuffle_answers_active' => false]);
                \Log::info('[CHALLENGER] Skill shuffle_answers épuisé');
            }
        }
        
        // Simuler le comportement complet de l'adversaire IA (passer timing du buzz)
        $opponentBehavior = $questionService->simulateOpponentBehavior(
            $niveau, 
            $question, 
            $playerBuzzed, 
            $buzzTime, 
            $effectiveChronoTime,
            $playerScore,
            $opponentScore,
            $questionNumber
        );
        
        // Calculer les points du joueur selon les nouvelles règles (BUG #2 FIX)
        $playerPoints = 0;
        
        // Vérifier si le skill Plume (answer_without_buzz) a été utilisé
        $featherSkillUsed = $request->input('feather_skill_used', '0') === '1';
        
        // Vérifier si le skill Illumine (illuminate_numbers) a été utilisé
        $illuminateSkillUsed = $request->input('illuminate_skill_used', '0') === '1';
        if ($illuminateSkillUsed) {
            $usedSkills = session('used_skills', []);
            if (!in_array('illuminate_numbers', $usedSkills)) {
                $usedSkills[] = 'illuminate_numbers';
                session(['used_skills' => $usedSkills]);
            }
        }
        
        // Vérifier si le skill Acidifie (acidify_error) a été utilisé
        $acidifySkillUsed = $request->input('acidify_skill_used', '0') === '1';
        if ($acidifySkillUsed) {
            $usedSkills = session('used_skills', []);
            if (!in_array('acidify_error', $usedSkills)) {
                $usedSkills[] = 'acidify_error';
                session(['used_skills' => $usedSkills]);
            }
        }
        
        // Vérifier si le skill Voir choix (see_opponent_choice) a été utilisé
        $seeOpponentSkillUsed = $request->input('see_opponent_skill_used', '0') === '1';
        if ($seeOpponentSkillUsed) {
            $usedSkills = session('used_skills', []);
            if (!in_array('see_opponent_choice', $usedSkills)) {
                $usedSkills[] = 'see_opponent_choice';
                session(['used_skills' => $usedSkills]);
            }
        }
        
        // Vérifier si le skill Rejouer (replay) a été utilisé - IA Junior
        $replaySkillUsed = $request->input('replay_skill_used', '0') === '1';
        if ($replaySkillUsed) {
            $usedSkills = session('used_skills', []);
            if (!in_array('replay', $usedSkills)) {
                $usedSkills[] = 'replay';
                session(['used_skills' => $usedSkills]);
            }
        }
        
        // Vérifier si le skill Suggestion IA (ai_suggestion) a été utilisé - IA Junior
        $aiSuggestionSkillUsed = $request->input('ai_suggestion_skill_used', '0') === '1';
        if ($aiSuggestionSkillUsed) {
            $usedSkills = session('used_skills', []);
            if (!in_array('ai_suggestion', $usedSkills)) {
                $usedSkills[] = 'ai_suggestion';
                session(['used_skills' => $usedSkills]);
            }
        }
        
        // Vérifier si le skill Élimination (eliminate_two) a été utilisé - IA Junior
        $eliminateTwoSkillUsed = $request->input('eliminate_two_skill_used', '0') === '1';
        if ($eliminateTwoSkillUsed) {
            $usedSkills = session('used_skills', []);
            if (!in_array('eliminate_two', $usedSkills)) {
                $usedSkills[] = 'eliminate_two';
                session(['used_skills' => $usedSkills]);
            }
        }
        
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
        } elseif ($featherSkillUsed) {
            // Skill Plume (Historien): +1 si correct, 0 si incorrect (pas de pénalité)
            $playerPoints = $isCorrect ? 1 : 0;
            
            // Marquer le skill comme utilisé
            $usedSkills = session('used_skills', []);
            if (!in_array('knowledge_without_time', $usedSkills)) {
                $usedSkills[] = 'knowledge_without_time';
                session(['used_skills' => $usedSkills]);
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

        // Tracker le pire différentiel de score pour comeback_0_5
        $newScore         = $currentScore + $playerPoints;
        $newOpponentScore = $currentOpponentScore + $opponentBehavior['points'];
        $scoreDiff        = $newScore - $newOpponentScore;
        $prevMinDiff      = (int) session('min_score_differential', 0);
        if ($scoreDiff < $prevMinDiff) {
            session(['min_score_differential' => $scoreDiff]);
        }

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
            $questService      = app(\App\Services\QuestService::class);
            $dailyQuestService = app(\App\Services\DailyQuestService::class);
            $theme = session('theme', 'Général');

            // Compétence utilisée dans cette réponse → skills_used_50 / skill_usage / unique_skills / all_skills
            $anySkillUsed = $featherSkillUsed
                || ($illuminateSkillUsed ?? false)
                || ($acidifySkillUsed ?? false)
                || ($seeOpponentSkillUsed ?? false)
                || ($replaySkillUsed ?? false)
                || ($aiSuggestionSkillUsed ?? false)
                || ($eliminateTwoSkillUsed ?? false);
            if ($anySkillUsed) {
                $questService->checkAndCompleteQuests($user, 'skills_used_50', ['skill_used' => true]);
                $questService->checkAndCompleteQuests($user, 'skill_used', ['skill_used' => true]);
                $questService->checkAndCompleteQuests($user, 'skill_usage', ['skill_used' => true]);
                // Identifier le slug de la compétence utilisée pour unique_skills_used et all_skills_used
                $usedSlug = null;
                if ($featherSkillUsed)                     $usedSlug = 'feather';
                elseif ($illuminateSkillUsed ?? false)     $usedSlug = 'illuminate_numbers';
                elseif ($acidifySkillUsed ?? false)        $usedSlug = 'acidify_error';
                elseif ($seeOpponentSkillUsed ?? false)    $usedSlug = 'see_opponent_choice';
                elseif ($replaySkillUsed ?? false)         $usedSlug = 'replay';
                elseif ($aiSuggestionSkillUsed ?? false)   $usedSlug = 'ai_suggestion';
                elseif ($eliminateTwoSkillUsed ?? false)   $usedSlug = 'eliminate_two';
                if ($usedSlug) {
                    $questService->checkAndCompleteQuests($user, 'unique_skills_used', ['skill_slug' => $usedSlug]);
                    $questService->checkAndCompleteQuests($user, 'all_skills_used', ['skill_slug' => $usedSlug]);
                }
            }

            // Buzz rapides : premier à buzzer
            $playerWasFirst = $playerBuzzed && (!$opponentBehavior['buzzes'] || $opponentBehavior['is_faster'] === false);
            if ($playerWasFirst) {
                $questService->checkAndCompleteQuests($user, 'first_buzz_10', [
                    'first_buzz' => true,
                ]);
                $questService->checkAndCompleteQuests($user, 'first_buzz_total', [
                    'first_buzz' => true,
                ]);
                $questService->checkAndCompleteQuests($user, 'first_buzz_total_legendaire', [
                    'first_buzz' => true,
                ]);
                // Buzz ultra-rapide (< 1 s)
                if ($buzzTime < 1) {
                    $questService->checkAndCompleteQuests($user, 'ultra_fast_buzz_20', [
                        'buzz_time'  => $buzzTime,
                        'is_correct' => $isCorrect,
                    ]);
                }
            }

            // Réponses correctes
            if ($isCorrect) {
                // Streak correct (cross-sessions) — quêtes existantes + nouvelles variantes
                $correctContext = ['answer_correct' => true, 'answer_time' => $buzzTime];
                foreach (['correct_streak_25', 'correct_streak_50', 'consecutive_correct', 'perfect_accuracy_epique', 'perfect_accuracy_legendaire'] as $code) {
                    $questService->checkAndCompleteQuests($user, $code, $correctContext);
                }
                // Réponse rapide (< 2 s)
                if ($buzzTime < 2) {
                    $questService->checkAndCompleteQuests($user, 'fast_answers_10', [
                        'answer_time' => $buzzTime,
                        'is_correct'  => true,
                    ]);
                    $questService->checkAndCompleteQuests($user, 'fast_answers', [
                        'answer_time' => $buzzTime,
                        'is_correct'  => true,
                    ]);
                }
                // Réponse ultra-rapide (< 1 s)
                if ($buzzTime < 1) {
                    $questService->checkAndCompleteQuests($user, 'ultra_fast_answers_10', [
                        'answer_time' => $buzzTime,
                        'is_correct'  => true,
                    ]);
                    $questService->checkAndCompleteQuests($user, 'ultra_fast_answers_epique', [
                        'answer_time' => $buzzTime,
                        'is_correct'  => true,
                    ]);
                }
                // Réponse ultra-rapide (< 0,5 s) — Légendaire
                if ($buzzTime < 0.5) {
                    $questService->checkAndCompleteQuests($user, 'ultra_fast_answers_legendaire', [
                        'answer_time' => $buzzTime,
                        'is_correct'  => true,
                    ]);
                }
                // Math streak
                $questService->checkAndCompleteQuests($user, 'math_streak', [
                    'theme'          => $theme,
                    'answer_correct' => true,
                ]);
                // Monuments (approximation par thème)
                $questService->checkAndCompleteQuests($user, 'monuments_10', [
                    'theme'          => $theme,
                    'answer_correct' => true,
                ]);
                // Océans (approximation par thème)
                $questService->checkAndCompleteQuests($user, 'oceans_3', [
                    'theme'          => $theme,
                    'answer_correct' => true,
                ]);
                // Question piège (nécessite tagging futur — infrastructure prête)
                $currentQuestion = session('current_question_data', []);
                $isTrickQuestion = ($currentQuestion['type'] ?? $currentQuestion['category'] ?? '') === 'trick';
                if ($isTrickQuestion) {
                    $questService->checkAndCompleteQuests($user, 'trick_question_1', [
                        'is_trick_question' => true,
                        'answer_correct'    => true,
                    ]);
                }
                // Correct sans avoir buzzé (réponse après mauvais buzz adverse)
                if (!$playerBuzzed) {
                    $questService->checkAndCompleteQuests($user, 'correct_no_buzz', [
                        'answer_correct' => true,
                        'player_buzzed'  => false,
                    ]);
                }
            } else {
                // Réponse incorrecte : réinitialiser les streaks correct
                $wrongContext = ['answer_wrong' => true];
                foreach (['correct_streak_25', 'correct_streak_50', 'consecutive_correct', 'perfect_accuracy_epique', 'perfect_accuracy_legendaire'] as $code) {
                    $questService->checkAndCompleteQuests($user, $code, $wrongContext);
                }
                $questService->checkAndCompleteQuests($user, 'math_streak', [
                    'theme'        => $theme,
                    'answer_wrong' => true,
                ]);
            }

            // ── Quêtes quotidiennes — événements par réponse ──────────────
            try {
                $dailyCtx = [
                    'first_buzz'  => $playerWasFirst,
                    'is_correct'  => $isCorrect,
                    'answer_time' => $buzzTime,
                    'skill_used'  => $anySkillUsed,
                    'theme'       => strtolower($theme),
                ];
                $dailyQuestService->fireDailyQuestChecks($user, $dailyCtx);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Daily quest hook (per-answer) error: ' . $e->getMessage());
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
        
        // Calculer le total basé sur le nombre configuré et le nombre de manches COMPLÉTÉES
        $roundSummaries = session('round_summaries', []);
        $roundsCompleted = count($roundSummaries);
        $questionsPerRound = session('nb_questions', 12);
        $totalQuestionsPlayed = $roundsCompleted * $questionsPerRound;
        
        Log::info("Computing global stats from " . count($globalStats) . " entries, nb_questions=" . $questionsPerRound . ", rounds completed=" . $roundsCompleted . ", total=" . $totalQuestionsPlayed);
        
        foreach ($globalStats as $index => $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans les statistiques globales
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                Log::info("  [{$index}] SKIPPED: bonus question");
                continue;
            }
            
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
            'player_buzzed' => $playerBuzzed,
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
        
        // Vérifier si le skill Plume (answer_without_buzz) est disponible
        $avatar = session('avatar', 'Aucun');
        $usedSkills = session('used_skills', []);
        $featherAvailable = false;
        
        if ($avatar === 'Historien') {
            $avatarSkills = $this->getAvatarSkills($avatar);
            foreach ($avatarSkills['skills'] ?? [] as $skill) {
                if (($skill['id'] ?? '') === 'knowledge_without_time' && !in_array('knowledge_without_time', $usedSkills)) {
                    $featherAvailable = true;
                    break;
                }
            }
        }
        
        // Afficher la page de réponse avec la Plume si disponible
        return $this->renderAnswerView(false, null, $featherAvailable);
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
        
        // BEST OF 3 : Utiliser le nombre de questions configuré par l'utilisateur
        $questionsPerRound = session('nb_questions', 10);
        
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
            } elseif ($opponentScore > $playerScore) {
                $opponentRoundsWon++;
            } else {
                // ÉGALITÉ - aller en tiebreaker pour cette manche
                session([
                    'tiebreaker_round' => session('current_round', 1),
                    'tiebreaker_player_score' => $playerScore,
                    'tiebreaker_opponent_score' => $opponentScore,
                ]);
                return redirect()->route('solo.tiebreaker-choice');
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
        
        // Calculer le total basé sur le nombre configuré et le nombre de manches COMPLÉTÉES
        $roundSummaries = session('round_summaries', []);
        $roundsCompleted = count($roundSummaries);
        $questionsPerRound = session('nb_questions', 12);
        $totalQuestionsPlayed = $roundsCompleted * $questionsPerRound;
        
        foreach ($globalStats as $index => $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans les statistiques globales
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                continue;
            }
            
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
        
        // Calculer le total basé sur le nombre configuré et le nombre de manches COMPLÉTÉES
        $roundSummaries = session('round_summaries', []);
        $roundsCompleted = count($roundSummaries);
        $questionsPerRound = session('nb_questions', 12);
        $totalQuestionsPlayed = $roundsCompleted * $questionsPerRound;
        
        foreach ($globalStats as $index => $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans les statistiques globales
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                continue;
            }
            
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
        
        // Vérifier et compléter les quêtes (victoire Solo)
        $user = auth()->user();
        if ($user) {
            $questService  = new QuestService();
            $bossDefeated  = $this->getBossForLevel($currentLevel) !== null;
            $livesRemaining = (int) session('vies_restantes', $user->lives ?? 3);
            $skillsRestants = (int) session('skills_restants', 3);
            $skillsUsed     = max(0, 3 - $skillsRestants);

            // Scores globaux du match (somme des manches)
            $totalPlayerScore   = 0;
            $totalOpponentScore = 0;
            foreach (session('round_summaries', []) as $rs) {
                $totalPlayerScore   += $rs['player_score']   ?? $rs['points_earned'] ?? 0;
                $totalOpponentScore += $rs['opponent_score'] ?? 0;
            }

            // Contexte unifié envoyé à fireMatchEndQuests
            $maxDeficitRecovered = abs(min(0, (int) session('min_score_differential', 0)));
            $questContext = [
                'match_completed'       => true,
                'won'                   => true,
                'total_questions'       => $totalQuestionsPlayed,
                'user_correct'          => $totalCorrect,
                'player_score'          => $totalPlayerScore,
                'opponent_score'        => $totalOpponentScore,
                'theme'                 => $theme,
                'skills_used'           => $skillsUsed,
                'lives_remaining'       => $livesRemaining,
                'had_timeout'           => $totalUnanswered > 0,
                'boss_defeated'         => $bossDefeated,
                'sound_disabled'        => (bool) session('sound_disabled', false),
                'max_deficit_recovered' => $maxDeficitRecovered,
                'user_level'            => $user->level ?? 0,
                'user_coins'            => $user->competence_coins ?? 0,
                'division'              => 'bronze',
            ];

            $questService->fireMatchEndQuests($user, 'solo', $questContext);

            // ── Quêtes quotidiennes — fin de match (victoire Solo) ────────
            try {
                $dailyQuestService = app(\App\Services\DailyQuestService::class);

                // Détection comeback : 2 premières manches perdues puis victoire
                $roundSummariesSnap = session('round_summaries', []);
                $isComeback = count($roundSummariesSnap) >= 3
                    && ($roundSummariesSnap[0]['won'] ?? true) === false
                    && ($roundSummariesSnap[1]['won'] ?? true) === false;

                // Nombre de thèmes joués dans cette session
                $sessionThemes = array_unique(array_filter(array_column($roundSummariesSnap, 'theme')));
                $themesCount   = count($sessionThemes);

                $dailyMatchCtx = array_merge($questContext, [
                    'match_completed' => true,
                    'won'             => true,
                    'mode'            => 'solo',
                    'match_hour'      => (int) now()->format('G'),
                    'perfect_score'   => $totalCorrect > 0 && $totalUnanswered === 0 && ($totalIncorrect ?? 0) === 0,
                    'total_buzzes'    => $totalCorrect + ($totalIncorrect ?? 0),
                    'themes_count'    => $themesCount,
                    'comeback_win'    => $isComeback,
                    'theme'           => strtolower($questContext['theme'] ?? ''),
                ]);
                $dailyQuestService->fireDailyQuestChecks($user, $dailyMatchCtx);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Daily quest hook (match-end win) error: ' . $e->getMessage());
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
        
        // RÉCOMPENSE EN PIÈCES D'INTELLIGENCE SUR VICTOIRE
        $coinsEarned = 0;
        $coinsBonus = 0;
        $hasStrategeBonus = false;
        
        if ($user) {
            // Nouveau système de calcul des pièces par paliers
            $coinsEarned = $this->calculateCoinsForLevel($currentLevel);
            
            // Bonus Stratège: +25% si l'avatar est "Stratège"
            $avatar = session('avatar', 'Aucun');
            if ($avatar === 'Stratège') {
                $hasStrategeBonus = true;
                $coinsBonus = (int) ceil($coinsEarned * 0.25);
                $coinsEarned += $coinsBonus;
            }
            
            // Créditer les pièces de Compétence via le CoinLedgerService (Solo = Compétence coins)
            $coinService = new CoinLedgerService();
            $coinService->credit(
                $user,
                $coinsEarned,
                "Victoire Solo niveau {$currentLevel}" . ($hasStrategeBonus ? " (+25% Stratège)" : ""),
                'solo_victory',
                $currentLevel,
                'competence'
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
        
        // Détecter le déblocage du Duo complet (Boss niveau 10 battu = passage au niveau 11)
        $duoFullUnlocked = ($currentLevel == 10 && $newLevel >= 11);
        
        // Récupérer les stats des 10 dernières parties Solo
        $last10Stats = [];
        if ($user) {
            $last10Stats = \App\Models\MatchPerformance::getLast10Stats($user->id, 'solo');
            
            // Si Duo débloqué, inscrire le joueur en Bronze Duo avec son efficacité Solo
            if ($duoFullUnlocked) {
                $soloEfficiency = $last10Stats['global_efficiency'] ?? 0;
                $divisionService = new \App\Services\DivisionService();
                $duoDivision = $divisionService->getOrCreateDivision($user, 'duo', $soloEfficiency);
                
                \Log::info("Joueur inscrit en Bronze Duo", [
                    'user_id' => $user->id,
                    'solo_efficiency' => $soloEfficiency,
                    'division_id' => $duoDivision->id,
                ]);
            }
        }
        
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
            // Pièces d'intelligence gagnées
            'coins_earned' => $coinsEarned,
            'coins_bonus' => $coinsBonus,
            'has_stratege_bonus' => $hasStrategeBonus,
            // Flag de déblocage Duo complet
            'duo_full_unlocked' => $duoFullUnlocked,
            // Stats des 10 dernières parties (efficacité moyenne + ratio V/D)
            'last_10_stats' => $last10Stats,
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
        
        // Calculer le total basé sur le nombre configuré et le nombre de manches COMPLÉTÉES
        $roundSummaries = session('round_summaries', []);
        $roundsCompleted = count($roundSummaries);
        $questionsPerRound = session('nb_questions', 12);
        $totalQuestionsPlayed = $roundsCompleted * $questionsPerRound;
        
        foreach ($globalStats as $index => $stat) {
            // FILTRER LES QUESTIONS BONUS : ne pas les compter dans les statistiques globales
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                continue;
            }
            
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

        // Quêtes défaite : réinitialiser win streaks + incrémenter compteur de défaites consécutives
        if ($user) {
            $questService = new QuestService();
            $questService->fireMatchEndQuests($user, 'solo', [
                'match_completed' => true,
                'won'             => false,
                'total_questions' => $totalQuestionsPlayed,
                'user_correct'    => $totalCorrect,
                'theme'           => $theme,
                'skills_used'     => 0,
                'lives_remaining' => $user->lives ?? 0,
                'had_timeout'     => $totalUnanswered > 0,
                'boss_defeated'   => false,
                'user_level'      => $user->level ?? 0,
                'user_coins'      => $user->competence_coins ?? 0,
                'division'        => 'bronze',
            ]);
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
        
        // Récupérer les stats des 10 dernières parties Solo
        $last10Stats = [];
        if ($user) {
            $last10Stats = \App\Models\MatchPerformance::getLast10Stats($user->id, 'solo');
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
            // Stats des 10 dernières parties (efficacité moyenne + ratio V/D)
            'last_10_stats' => $last10Stats,
        ];
        
        return view('defeat', compact('params'));
    }

    /**
     * Calcule les pièces d'intelligence gagnées selon le niveau
     * - Niveaux 1-9 : 10 pièces
     * - Niveaux 11-19 : 20 pièces, etc. (+10 par palier de 10)
     * - Boss (niveaux multiples de 10) : récompenses spéciales
     */
    private function calculateCoinsForLevel(int $level): int
    {
        // Boss levels have special rewards
        $bossRewards = [
            10 => 50,
            20 => 50,
            30 => 75,
            40 => 75,
            50 => 100,
            60 => 100,
            70 => 125,
            80 => 125,
            90 => 150,
            100 => 250,
        ];
        
        // Check if it's a boss level
        if (isset($bossRewards[$level])) {
            return $bossRewards[$level];
        }
        
        // Regular levels: 10 coins per tier (1-9 = 10, 11-19 = 20, etc.)
        $tier = (int) ceil($level / 10);
        return $tier * 10;
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
            'Aucun' => [
                'rarity' => null,
                'skills' => []
            ],
            
            // 🔵 RARE - 1 compétence chacun
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
            
            // 🟣 ÉPIQUE - 2 compétences chacun
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
                        'description' => 'Fait changer la position des réponses toutes les 2 secondes',
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
                        'description' => 'Diminue le compte à rebours des autres joueurs',
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
                        'description' => 'Répondre sans buzzer (+1 max)',
                        'type' => 'bonus',
                        'trigger' => 'answer',
                        'uses_per_match' => 1,
                        'auto' => false
                    ],
                    [
                        'id' => 'history_corrects',
                        'name' => "L'histoire corrige",
                        'icon' => '📜',
                        'description' => 'Récupérer les points après erreur',
                        'type' => 'correction',
                        'trigger' => 'result',
                        'uses_per_match' => 1,
                        'auto' => false
                    ]
                ]
            ],
            
            // 🟡 LÉGENDAIRE - 3 compétences chacun
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
        
        // Normalisation des noms d'avatar (slug -> nom complet)
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
            'stratege' => 'Stratège',
            'sprinteur' => 'Sprinteur',
            'visionnaire' => 'Visionnaire',
        ];
        
        // Si l'avatar est un slug, le convertir en nom complet
        $normalizedAvatar = $slugToName[strtolower($avatar)] ?? $avatar;
        
        $result = $skills[$normalizedAvatar] ?? ['rarity' => null, 'skills' => []];
        
        // Skill Stratège: Ajouter les skills du coéquipier (avatar rare)
        if (in_array(strtolower($normalizedAvatar), ['stratège', 'stratege'])) {
            // Utiliser getEffectiveTeammate() pour auto-sélectionner si nécessaire
            $teammate = $this->getEffectiveTeammate();
            if ($teammate) {
                // Convertir le slug du coéquipier en nom complet
                $teammateFullName = $slugToName[strtolower($teammate)] ?? $teammate;
                $teammateData = $skills[$teammateFullName] ?? null;
                
                if ($teammateData && !empty($teammateData['skills'])) {
                    // Ajouter les skills du coéquipier aux skills du Stratège
                    $result['skills'] = array_merge($result['skills'], $teammateData['skills']);
                    $result['teammate'] = [
                        'name' => $teammateFullName,
                        'slug' => $teammate,
                        'rarity' => $teammateData['rarity'] ?? 'rare',
                        'icon' => $teammateData['icon'] ?? '🎯'
                    ];
                }
            }
            
            // Indiquer si aucun avatar Rare n'est débloqué
            $result['has_unlocked_rare'] = $this->hasUnlockedRareAvatar();
        }
        
        return $result;
    }
    
    private function getAvatarSkillsSimple($avatar)
    {
        $fullData = $this->getAvatarSkills($avatar);
        if (empty($fullData['skills'])) {
            return [];
        }
        return array_map(function($skill) {
            return $skill['description'];
        }, $fullData['skills']);
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
                } else {
                    $wrongAnswers++;
                }
            }
            
            // Utiliser les points RÉELS stockés (+2, +1, 0, -2)
            if (isset($stat['player_points'])) {
                $pointsEarned += $stat['player_points'];
            } else {
                // Fallback pour compatibilité anciennes données
                if ($stat['player_buzzed']) {
                    $pointsEarned += $stat['is_correct'] ? 2 : -2;
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
        
        // Utiliser le nombre de questions CONFIGURÉ pour l'affichage
        $questionsPerRound = session('nb_questions', 12);
        
        // Agrégation des statistiques
        $buzzed = 0;
        $correct = 0;
        $wrong = 0;
        $unanswered = 0;
        $pointsEarned = 0;
        $bonusPoints = 0;  // Points bonus séparés
        
        $questionsPlayed = 0;  // Compter les questions réellement jouées pour le calcul
        
        foreach ($roundStats as $stat) {
            // QUESTIONS BONUS : les compter séparément
            if (isset($stat['is_bonus']) && $stat['is_bonus']) {
                if (isset($stat['player_points'])) {
                    $bonusPoints += $stat['player_points'];
                }
                continue;  // Sauter pour le comptage des questions normales
            }
            
            $questionsPlayed++;
            
            // Utiliser les points RÉELS si disponibles
            if (isset($stat['player_points'])) {
                $pointsEarned += $stat['player_points'];
                Log::info("Manche {$roundNumber} - Q#{$questionsPlayed}: pts={$stat['player_points']}, buzzed=" . ($stat['player_buzzed'] ? '1' : '0') . ", correct=" . ($stat['is_correct'] ? '1' : '0') . ", skill=" . (isset($stat['skill_adjusted']) ? '1' : '0') . " | Total cumulé: {$pointsEarned}");
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
        // Utiliser le nombre configuré pour le calcul des points possibles
        $pointsPossible = $questionsPerRound * 2; // 2 points max par question configurée
        
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
            'questions' => $questionsPerRound,  // Nombre configuré (10, 20, 30, 40, 50)
            'buzzed' => $buzzed,
            'correct' => $correct,
            'wrong' => $wrong,
            'unanswered' => $unanswered,
            'points_earned' => $pointsEarned,
            'points_possible' => $pointsPossible,
            'efficiency' => $efficiency,
            'bonus_points' => $bonusPoints,
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
    
    /**
     * Skill Challenger - Chrono Réduit
     * Réduit le chrono de l'adversaire de 2 sec pendant plusieurs questions
     * Manche 1-2: 5 questions, Manche 3: 3 questions, Manche Ultime: 1 question
     */
    public function reduceTime(Request $request)
    {
        $avatar = session('avatar', 'Aucun');
        
        if ($avatar !== 'Challenger') {
            return response()->json(['success' => false, 'message' => 'Skill non disponible pour cet avatar'], 403);
        }
        
        $usedSkills = session('used_skills', []);
        if (in_array('reduce_time', $usedSkills)) {
            return response()->json(['success' => false, 'message' => 'Skill déjà utilisé'], 403);
        }
        
        // Déterminer le nombre de questions affectées selon la manche
        $currentRound = session('current_round', 1);
        $questionsAffected = 5; // Par défaut Manche 1-2
        
        if ($currentRound === 3) {
            $questionsAffected = 3; // Manche 3 (5 questions)
        } elseif ($currentRound >= 4) {
            $questionsAffected = 1; // Manche Ultime (3 questions)
        }
        
        // Activer le skill
        session(['reduce_time_active' => true]);
        session(['reduce_time_questions_left' => $questionsAffected]);
        session(['reduce_time_reduction' => 2]); // -2 secondes
        
        // Marquer le skill comme utilisé
        $usedSkills[] = 'reduce_time';
        session(['used_skills' => $usedSkills]);
        
        \Log::info('[CHALLENGER] Skill reduce_time activé', [
            'current_round' => $currentRound,
            'questions_affected' => $questionsAffected,
        ]);
        
        return response()->json([
            'success' => true, 
            'message' => 'Chrono Réduit activé ! -2 sec pour l\'adversaire pendant ' . $questionsAffected . ' questions',
            'questions_affected' => $questionsAffected,
            'used_skills' => $usedSkills
        ]);
    }
    
    /**
     * Skill Challenger - Mélange Réponses
     * Les réponses se déplacent toutes les 1.5 sec sur la page réponse
     * Manche 1-2: 5 questions, Manche 3: 3 questions, Manche Ultime: 1 question
     */
    public function shuffleAnswers(Request $request)
    {
        $avatar = session('avatar', 'Aucun');
        
        if ($avatar !== 'Challenger') {
            return response()->json(['success' => false, 'message' => 'Skill non disponible pour cet avatar'], 403);
        }
        
        $usedSkills = session('used_skills', []);
        if (in_array('shuffle_answers', $usedSkills)) {
            return response()->json(['success' => false, 'message' => 'Skill déjà utilisé'], 403);
        }
        
        // Déterminer le nombre de questions affectées selon la manche
        $currentRound = session('current_round', 1);
        $questionsAffected = 5; // Par défaut Manche 1-2
        
        if ($currentRound === 3) {
            $questionsAffected = 3; // Manche 3 (5 questions)
        } elseif ($currentRound >= 4) {
            $questionsAffected = 1; // Manche Ultime (3 questions)
        }
        
        // Activer le skill
        session(['shuffle_answers_active' => true]);
        session(['shuffle_answers_questions_left' => $questionsAffected]);
        
        // Marquer le skill comme utilisé
        $usedSkills[] = 'shuffle_answers';
        session(['used_skills' => $usedSkills]);
        
        \Log::info('[CHALLENGER] Skill shuffle_answers activé', [
            'current_round' => $currentRound,
            'questions_affected' => $questionsAffected,
        ]);
        
        return response()->json([
            'success' => true, 
            'message' => 'Mélange Réponses activé ! Les réponses bougent pendant ' . $questionsAffected . ' questions',
            'questions_affected' => $questionsAffected,
            'used_skills' => $usedSkills
        ]);
    }
    
    /**
     * Skill Historien - Parchemin (L'histoire corrige)
     * Permet de récupérer les points après une mauvaise réponse en cliquant sur la bonne réponse
     */
    public function useScrollSkill(Request $request)
    {
        $avatar = session('avatar', 'Aucun');
        
        if ($avatar !== 'Historien') {
            return response()->json(['success' => false, 'message' => __('Skill non disponible pour cet avatar')], 403);
        }
        
        $usedSkills = session('used_skills', []);
        if (in_array('history_corrects', $usedSkills)) {
            return response()->json(['success' => false, 'message' => __('Skill déjà utilisé')], 403);
        }
        
        $globalStats = session('global_stats', []);
        if (empty($globalStats)) {
            return response()->json(['success' => false, 'message' => __('Aucune question à corriger')], 403);
        }
        
        $lastIndex = count($globalStats) - 1;
        $lastStat = $globalStats[$lastIndex];
        
        // Le skill ne fonctionne que si le joueur a fait une erreur
        if ($lastStat['is_correct']) {
            return response()->json(['success' => false, 'message' => __('La dernière réponse était correcte')], 403);
        }
        
        // Points que le joueur aurait gagnés selon l'ordre de buzz
        // opponent_faster = true → joueur était 2ème → 1 point
        // opponent_faster = false → joueur était 1er → 2 points
        $opponentFaster = $lastStat['opponent_faster'] ?? false;
        $pointsWouldHaveWon = $opponentFaster ? 1 : 2;
        
        // Le Parchemin fait un calcul CUMULATIF:
        // 1. Annule le -2 pts de l'erreur = +2
        // 2. PUIS ajoute les points que le joueur jouait pour (+2 si 1er, +1 si 2ème)
        // Résultat final: 1er buzz → +2 pts, 2ème buzz → +1 pt
        $totalPointsToAdd = 2 + $pointsWouldHaveWon;
        
        // Mettre à jour le score
        $currentScore = session('score', 0);
        $newScore = $currentScore + $totalPointsToAdd;
        session(['score' => $newScore]);
        
        // Mettre à jour les statistiques - GARDER is_correct = false (l'erreur reste une erreur)
        // MAIS modifier player_points pour refléter le résultat final (+1 ou +2 au lieu de -2)
        $answeredQuestions = session('answered_questions', []);
        $answeredLastIndex = count($answeredQuestions) - 1;
        if ($answeredLastIndex >= 0) {
            // L'erreur reste une erreur dans les stats, mais les points sont corrigés
            $answeredQuestions[$answeredLastIndex]['skill_adjusted'] = true;
            $answeredQuestions[$answeredLastIndex]['skill_points_added'] = $totalPointsToAdd;
            $answeredQuestions[$answeredLastIndex]['player_points'] = $pointsWouldHaveWon; // +1 ou +2 (résultat final)
            session(['answered_questions' => $answeredQuestions]);
        }
        
        // Mettre à jour global_stats - GARDER is_correct = false, MAIS corriger player_points
        $globalStats[$lastIndex]['skill_adjusted'] = true;
        $globalStats[$lastIndex]['skill_points_added'] = $totalPointsToAdd;
        $globalStats[$lastIndex]['player_points'] = $pointsWouldHaveWon; // +1 ou +2 (résultat final)
        session(['global_stats' => $globalStats]);
        
        // Marquer le skill comme utilisé
        $usedSkills[] = 'history_corrects';
        session(['used_skills' => $usedSkills]);
        
        \Log::info('[Historien] Parchemin utilisé: -2 annulé, +' . $pointsWouldHaveWon . ' pts gagnés (total ajouté: ' . $totalPointsToAdd . ')');
        
        return response()->json([
            'success' => true,
            'message' => __("L'histoire corrige") . ' ! -2 ' . __('annulé') . ', +' . $pointsWouldHaveWon . ' ' . __('pts'),
            'new_score' => $newScore,
            'points_recovered' => $totalPointsToAdd,
            'points_final' => $pointsWouldHaveWon,
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
        $sessionUsedAllAnswers = session('session_used_all_answers', []);
        $sessionUsedQuestionTexts = session('session_used_question_texts', []);
        
        // Récupérer l'info de l'adversaire pour adapter la difficulté de la question bonus
        $opponentInfo = $this->getOpponentInfo($niveau);
        $opponentAge = $opponentInfo['age'] ?? null;
        $isBoss = $opponentInfo['is_boss'] ?? false;
        
        $language = $this->getUserLanguage();
        $question = $questionService->generateQuestion($theme, $niveau, 999, $usedQuestionIds, [], $sessionUsedAllAnswers, $sessionUsedQuestionTexts, $opponentAge, $isBoss, $language);
        
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
            $sessionUsedAllAnswers = session('session_used_all_answers', []); // TOUTES les réponses (correctes + distracteurs)
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
            $tempSessionUsedAllAnswers = $sessionUsedAllAnswers;
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
                // Ajouter TOUTES les réponses du stock
                if (isset($existingQ['answers']) && is_array($existingQ['answers'])) {
                    foreach ($existingQ['answers'] as $ans) {
                        if ($ans && trim($ans) !== '') {
                            $tempSessionUsedAllAnswers[] = AnswerNormalizationService::normalize($ans);
                        }
                    }
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
                    $tempSessionUsedAllAnswers,  // Toutes les réponses (correctes + distracteurs)
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
                // Ajouter TOUTES les réponses de cette question
                if (isset($question['answers']) && is_array($question['answers'])) {
                    foreach ($question['answers'] as $ans) {
                        if ($ans && trim($ans) !== '') {
                            $tempSessionUsedAllAnswers[] = AnswerNormalizationService::normalize($ans);
                        }
                    }
                }
            }
            
            // Ajouter au stock progressif
            $questionStock = array_merge($questionStock, $questions);
            session([$stockKey => $questionStock]);
            
            // CRITIQUE : Sauvegarder la liste complète des réponses utilisées pour les prochains blocs
            session(['session_used_all_answers' => $tempSessionUsedAllAnswers]);
            
            Log::info("Block generation complete", [
                'round' => $roundNumber,
                'block_id' => $blockId,
                'block_count' => count($questions),
                'total_stock' => count($questionStock),
                'session_key' => $stockKey,
                'total_answers_tracked' => count($tempSessionUsedAllAnswers)
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
            $sessionUsedAllAnswers = session('session_used_all_answers', []);
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
            $tempSessionUsedAllAnswers = $sessionUsedAllAnswers;
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
                    $tempSessionUsedAllAnswers,  // Toutes les réponses (correctes + distracteurs)
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
                
                // Ajouter TOUTES les réponses de cette question
                if (isset($question['answers']) && is_array($question['answers'])) {
                    foreach ($question['answers'] as $ans) {
                        if ($ans && trim($ans) !== '') {
                            $tempSessionUsedAllAnswers[] = AnswerNormalizationService::normalize($ans);
                        }
                    }
                }
            }
            
            // Stocker les questions pré-générées en session
            $key = "pregenerated_questions_round_{$roundNumber}";
            session([$key => $questions]);
            
            // CRITIQUE : Sauvegarder la liste complète des réponses utilisées pour les prochaines générations
            session(['session_used_all_answers' => $tempSessionUsedAllAnswers]);
            
            Log::info("Batch generation complete", [
                'round' => $roundNumber,
                'count' => count($questions),
                'avatar' => $avatar,
                'session_key' => $key,
                'total_answers_tracked' => count($tempSessionUsedAllAnswers)
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
            $response = Http::post(env('QUESTION_API_URL', 'http://localhost:3000') . '/generate-queue', [
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
     * Génère un fait intéressant "Le saviez-vous" basé sur la question via l'API Node (Gemini)
     */
    private function generateDidYouKnow($question, $isCorrect)
    {
        try {
            $correctAnswer = $question['answers'][$question['correct_index']] ?? '';
            $questionText = $question['text'] ?? '';
            $language = $this->getUserLanguage();

            $apiUrl = env('QUESTION_API_URL', 'http://localhost:3000') . '/generate-fun-fact';

            $response = \Illuminate\Support\Facades\Http::timeout(15)->post($apiUrl, [
                'questionText' => $questionText,
                'correctAnswer' => $correctAnswer,
                'language' => $language,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['factText'])) {
                    return trim($data['factText']);
                }
            }

            \Log::warning('Fun fact: réponse API invalide', ['status' => $response->status()]);
            return 'Chaque question est une opportunité d\'apprendre quelque chose de nouveau !';

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

    /**
     * API: Fetch question for SPA mode (GameplayEngine LocalProvider)
     * Returns question data as JSON without correct_index
     */
    public function fetchQuestionApi(Request $request)
    {
        $questionService = new \App\Services\QuestionService();
        $user = auth()->user();
        
        $theme = session('theme', 'general');
        $nbQuestions = session('nb_questions', 30);
        $niveau = session('niveau_selectionne', 1);
        $currentQuestion = session('current_question_number', 1);
        $usedQuestionIds = session('used_question_ids', []);
        $sessionUsedAllAnswers = session('session_used_all_answers', []);
        $sessionUsedQuestionTexts = session('session_used_question_texts', []);
        
        $opponentInfo = $this->getOpponentInfo($niveau);
        $opponentAge = $opponentInfo['age'] ?? null;
        $isBoss = $opponentInfo['is_boss'] ?? false;
        
        $currentRound = session('current_round', 1);
        $stockKey = "question_stock_round_{$currentRound}";
        $questionStock = session($stockKey, []);
        
        $questionIndex = $currentQuestion - 1;
        if (!empty($questionStock) && isset($questionStock[$questionIndex])) {
            $question = $questionStock[$questionIndex];
        } else {
            $language = $this->getUserLanguage();
            $question = $questionService->generateQuestion(
                $theme, $niveau, $currentQuestion, $usedQuestionIds, [], 
                $sessionUsedAllAnswers, $sessionUsedQuestionTexts, 
                $opponentAge, $isBoss, $language
            );
            
            $questionStock[$questionIndex] = $question;
            session([$stockKey => $questionStock]);
        }
        
        session(['current_question' => $question]);
        session(['question_start_time' => time()]);
        
        $usedQuestionIds[] = $question['id'];
        session(['used_question_ids' => $usedQuestionIds]);
        
        $baseTime = max(4, 8 - floor($niveau / 10));
        
        $safeAnswers = array_map(function($answer, $index) {
            return ['index' => $index, 'text' => $answer];
        }, $question['answers'], array_keys($question['answers']));
        
        return response()->json([
            'success' => true,
            'question' => [
                'id' => $question['id'],
                'question_text' => $question['text'],
                'answers' => $safeAnswers,
                'theme' => $question['theme'] ?? $theme,
                'sub_theme' => $question['sub_theme'] ?? '',
                'niveau' => $niveau,
            ],
            'question_number' => $currentQuestion,
            'total_questions' => $nbQuestions,
            'chrono_time' => $baseTime,
            'current_round' => $currentRound,
            'player_score' => session('score', 0),
            'opponent_score' => session('opponent_score', 0),
        ]);
    }

    /**
     * API: Submit answer for SPA mode (GameplayEngine LocalProvider)
     * Validates answer and returns result as JSON
     */
    public function submitAnswerApi(Request $request)
    {
        $validated = $request->validate([
            'answer_index' => 'required|integer|min:0|max:3',
            'buzz_time' => 'nullable|numeric',
        ]);
        
        $answerIndex = $validated['answer_index'];
        $buzzTime = $validated['buzz_time'] ?? 0;
        
        $question = session('current_question');
        if (!$question) {
            return response()->json(['success' => false, 'error' => 'No active question'], 400);
        }
        
        $correctIndex = $question['correct_index'];
        $isCorrect = ($answerIndex == $correctIndex);
        
        $niveau = session('niveau_selectionne', 1);
        $points = 0;
        
        if ($isCorrect) {
            $basePoints = 10;
            $levelBonus = floor($niveau / 10);
            $speedBonus = max(0, floor((8 - $buzzTime) * 2));
            $points = $basePoints + $levelBonus + $speedBonus;
            
            $currentScore = session('score', 0);
            session(['score' => $currentScore + $points]);
        }
        
        $opponentAnswered = $this->simulateOpponentAnswer($niveau, $isCorrect);
        if ($opponentAnswered['is_correct']) {
            $opponentScore = session('opponent_score', 0);
            session(['opponent_score' => $opponentScore + $opponentAnswered['points']]);
        }
        
        $currentQuestion = session('current_question_number', 1);
        $nbQuestions = session('nb_questions', 30);
        $isRoundComplete = ($currentQuestion >= $nbQuestions);
        
        session(['current_question_number' => $currentQuestion + 1]);
        session(['current_question' => null]);
        
        $playerRoundsWon = session('player_rounds_won', 0);
        $opponentRoundsWon = session('opponent_rounds_won', 0);
        $gameOver = false;
        $matchResult = null;
        
        if ($isRoundComplete) {
            $playerScore = session('score', 0);
            $opponentScoreFinal = session('opponent_score', 0);
            
            if ($playerScore > $opponentScoreFinal) {
                $playerRoundsWon++;
                session(['player_rounds_won' => $playerRoundsWon]);
            } else {
                $opponentRoundsWon++;
                session(['opponent_rounds_won' => $opponentRoundsWon]);
            }
            
            if ($playerRoundsWon >= 2 || $opponentRoundsWon >= 2) {
                $gameOver = true;
                $matchResult = $playerRoundsWon >= 2 ? 'victory' : 'defeat';
            }
        }
        
        return response()->json([
            'success' => true,
            'is_correct' => $isCorrect,
            'correct_index' => $correctIndex,
            'selected_index' => $answerIndex,
            'correct_answer' => $question['answers'][$correctIndex],
            'points' => $points,
            'player_score' => session('score', 0),
            'opponent_score' => session('opponent_score', 0),
            'opponent_answered' => $opponentAnswered,
            'question_number' => $currentQuestion,
            'next_question_number' => $currentQuestion + 1,
            'is_round_complete' => $isRoundComplete,
            'game_over' => $gameOver,
            'match_result' => $matchResult,
            'player_rounds_won' => $playerRoundsWon,
            'opponent_rounds_won' => $opponentRoundsWon,
            'redirect_url' => $gameOver 
                ? ($matchResult === 'victory' ? route('solo.victory') : route('solo.defeat'))
                : ($isRoundComplete ? route('solo.round-result') : null),
        ]);
    }

    /**
     * Simulate opponent answer for Solo mode
     */
    private function simulateOpponentAnswer(int $niveau, bool $playerCorrect): array
    {
        $opponentInfo = $this->getOpponentInfo($niveau);
        $successRate = $opponentInfo['success_rate'] ?? 0.5;
        
        $opponentCorrect = (mt_rand(1, 100) / 100) <= $successRate;
        
        $points = 0;
        if ($opponentCorrect) {
            $basePoints = 10;
            $levelBonus = floor($niveau / 10);
            $speedBonus = mt_rand(0, 6);
            $points = $basePoints + $levelBonus + $speedBonus;
        }
        
        return [
            'is_correct' => $opponentCorrect,
            'points' => $points,
            'buzz_time' => mt_rand(15, 70) / 10,
        ];
    }
}
