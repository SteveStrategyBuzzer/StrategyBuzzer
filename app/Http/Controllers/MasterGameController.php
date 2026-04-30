<?php

namespace App\Http\Controllers;

use App\Models\MasterGame;
use App\Models\MasterGameCode;
use App\Models\MasterGameQuestion;
use App\Models\MasterGamePlayer;
use App\Models\MasterGameTeam;
use App\Models\MasterGameInvitation;
use App\Services\MasterFirestoreService;
use App\Services\GameServerService;
use App\Services\ImageGenerationService;
use App\Services\PlayerContactService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MasterGameController extends Controller
{
    private MasterFirestoreService $firestoreService;
    private GameServerService $gameServerService;
    
    public function __construct(MasterFirestoreService $firestoreService, GameServerService $gameServerService)
    {
        $this->firestoreService = $firestoreService;
        $this->gameServerService = $gameServerService;
    }
    // Page 1: Accueil Maître du Jeu avec image
    public function index()
    {
        return view('master.index');
    }

    // Page 2: Créer un Quiz (formulaire)
    public function create()
    {
        return view('master.create');
    }

    // POST: Créer une partie
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'language' => 'required|string|in:FR,EN,ES,DE',
            'participants_expected' => 'required|integer|min:2|max:40',
            'mode' => 'required|in:face_to_face,one_vs_all,podium,groups',
            'total_questions' => 'required|in:10,20,30,40',
            'question_types' => 'required|array',
            'domain_type' => 'required|in:theme,scolaire',
            'theme' => 'nullable|string',
            'school_country' => 'nullable|string',
            'school_level' => 'nullable|string',
            'school_grade' => 'nullable|string',
            'school_subject' => 'nullable|string',
            'creation_mode' => 'required|in:automatique,personnalise',
            'ai_images_count' => 'nullable|integer|min:0|max:3',
            'tiebreaker_mode' => 'nullable|string|in:bonus,efficiency,sudden_death',
            'gameplay_ambiance_enabled' => 'nullable',
            'ambiance_music_choice' => 'nullable|string|in:master,player',
            'ambiance_music_id' => 'nullable|string',
            'buzzer_sound_choice' => 'nullable|string|in:master,player',
            'buzzer_sound_id' => 'nullable|string',
            'strategic_avatars_enabled' => 'nullable',
            'strategic_avatars_tiers' => 'nullable|array',
        ]);

        // Générer un code unique
        $gameCode = $this->generateUniqueGameCode();

        $game = MasterGame::create([
            'game_code' => $gameCode,
            'host_user_id' => Auth::id(),
            'name' => $validated['name'],
            'languages' => [$validated['language']], // Store as array for compatibility
            'participants_expected' => $validated['participants_expected'],
            'mode' => $validated['mode'],
            'total_questions' => $validated['total_questions'],
            'question_types' => $validated['question_types'],
            'domain_type' => $validated['domain_type'],
            'theme' => $validated['theme'] ?? null,
            'school_country' => $validated['school_country'] ?? null,
            'school_level' => $validated['school_level'] ?? null,
            'school_grade' => $validated['school_grade'] ?? null,
            'school_subject' => $validated['school_subject'] ?? null,
            'creation_mode' => $validated['creation_mode'],
            'ai_images_count' => $validated['ai_images_count'] ?? 0,
            'tiebreaker_mode' => $validated['tiebreaker_mode'] ?? 'bonus',
            'gameplay_ambiance_enabled' => isset($validated['gameplay_ambiance_enabled']),
            'ambiance_music_choice' => $validated['ambiance_music_choice'] ?? 'master',
            'ambiance_music_id' => $validated['ambiance_music_id'] ?? 'strategybuzzer',
            'buzzer_sound_choice' => $validated['buzzer_sound_choice'] ?? 'master',
            'buzzer_sound_id' => $validated['buzzer_sound_id'] ?? 'default',
            'strategic_avatars_enabled' => isset($validated['strategic_avatars_enabled']),
            'strategic_avatars_tiers' => $validated['strategic_avatars_tiers'] ?? [],
            'status' => 'draft'
        ]);

        // Mode Automatique : générer d'abord la Manche 1 puis ouvrir la composition
        if ($validated['creation_mode'] === 'automatique') {
            $this->generateInitialQuizStructure($game);
            return redirect()->route('master.compose', ['gameId' => $game->id, 'manche' => 1]);
        }

        // Mode Personnalisé : Rediriger vers la page de composition pour édition manuelle
        return redirect()->route('master.compose', $game->id);
    }

    // POST: Rejoindre une partie avec un code
    public function join(Request $request)
    {
        $validated = $request->validate([
            'game_code' => 'required|string|size:6'
        ]);

        $game = MasterGame::where('game_code', strtoupper($validated['game_code']))->first();

        if (!$game) {
            return back()->with('error', 'Code invalide. Vérifiez et réessayez.');
        }

        // Rediriger vers le lobby de la partie
        return redirect()->route('master.lobby', $game->id);
    }

    // Page 3: Composer le Quiz
    public function compose(Request $request, $gameId)
    {
        $game = MasterGame::with('questions')->findOrFail($gameId);
        
        // Vérifier que c'est bien l'hôte
        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }

        // Get manche parameter (1-5, where 4-5 = Manche Ultime slots)
        $manche = (int) $request->query('manche', 1);
        $manche = max(1, min(5, $manche)); // Clamp between 1 and 5

        return view('master.compose', compact('game', 'manche'));
    }

    // Page 4: Éditer une question
    public function editQuestion($gameId, $questionNumber)
    {
        $game = MasterGame::findOrFail($gameId);
        
        // Vérifier que c'est bien l'hôte
        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }

        // Récupérer ou créer la question
        $question = MasterGameQuestion::where('master_game_id', $gameId)
            ->where('question_number', $questionNumber)
            ->first();

        return view('master.edit-question', compact('game', 'questionNumber', 'question'));
    }

    // Sauvegarder une question
    public function saveQuestion(Request $request, $gameId, $questionNumber)
    {
        $game = MasterGame::findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }

        $validated = $request->validate([
            'question_text' => 'nullable|string|max:500',
            'question_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'answers' => 'required|array|min:2|max:4',
            'correct_answer' => 'required|integer|min:0|max:3',
        ]);

        // Upload de l'image si présente
        $imagePath = null;
        if ($request->hasFile('question_image')) {
            $imagePath = $request->file('question_image')->store('questions', 'public');
        }

        // Déterminer le type de question
        $questionType = 'multiple_choice';
        if (count($validated['answers']) == 2 && 
            (strtolower($validated['answers'][0]) == 'vrai' || strtolower($validated['answers'][0]) == 'true')) {
            $questionType = 'true_false';
        }
        
        // Créer ou mettre à jour la question
        MasterGameQuestion::updateOrCreate(
            [
                'master_game_id' => $gameId,
                'question_number' => $questionNumber,
            ],
            [
                'type' => $questionType,
                'text' => $validated['question_text'],
                'media_url' => $imagePath,
                'choices' => $validated['answers'],
                'correct_indexes' => [$validated['correct_answer']],
            ]
        );

        // Quête quotidienne : créer une question IA (via édition manuelle)
        try {
            app(\App\Services\DailyQuestService::class)->checkAndCompleteDailyQuest(
                Auth::user(), 'daily_create_ai_question', ['ai_question_created' => true]
            );
        } catch (\Throwable $e) {
            Log::warning('DailyQuest ai_question_created (saveQuestion) failed: ' . $e->getMessage());
        }

        return redirect()->route('master.compose', $gameId)
            ->with('success', 'Question sauvegardée !');
    }

    // Régénérer une question avec IA
    public function regenerateQuestion(Request $request, $gameId, $questionNumber)
    {
        $game = MasterGame::findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }

        // #88 hard-gate: this endpoint hits /generate-master-question on the
        // question-api (the only remaining provider-touching path). It MUST
        // never be reachable once the match has started — composition only.
        if ($game->started_at !== null) {
            abort(403, 'Régénération IA interdite après le démarrage de la partie (#88).');
        }

        // Récupérer les questions déjà créées pour éviter les doublons
        $existingQuestions = MasterGameQuestion::where('master_game_id', $gameId)
            ->where('question_number', '!=', $questionNumber)
            ->get()
            ->pluck('question_text')
            ->filter()
            ->toArray();

        // Générer des sous-thèmes variés pour forcer la diversité
        $subTheme = $this->generateSubTheme($game, $questionNumber, $existingQuestions);

        // Déterminer le type de question pour ce numéro (distribution équilibrée)
        $questionType = $this->getQuestionTypeForNumber($game, $questionNumber);
        $isImageQuestion = ($questionType === 'image');
        
        $language = strtolower($game->language ?? $game->languages[0] ?? 'fr');

        if ($game->domain_type === 'theme') {
            $theme = $game->theme ?? 'Culture générale';
        } else {
            $theme = ($game->school_subject ?? 'Culture générale') . ' - ' . ($game->school_level ?? 'Général');
        }

        $previousQuestions = array_values($existingQuestions);

        try {
            $apiUrl = env('QUESTION_API_URL', 'http://localhost:3000') . '/generate-master-question';

            // #88: composition endpoints are admin-locked. Send the shared secret
            // so the question-api accepts the request from this trusted backend.
            $response = Http::timeout(30)
                ->withHeaders(['X-Admin-Token' => (string) env('MASTER_API_ADMIN_TOKEN', '')])
                ->post($apiUrl, [
                'theme' => $theme,
                'language' => $language,
                'questionType' => $isImageQuestion ? 'multiple_choice' : $questionType,
                'questionNumber' => (int) $questionNumber,
                'previousQuestions' => $previousQuestions,
                'gameSeed' => $game->id,
                'domainType' => $game->domain_type,
                'schoolLevel' => $game->school_level,
                'schoolGrade' => $game->school_grade,
                'schoolSubject' => $game->school_subject,
                'schoolCountry' => $game->school_country,
                'mode' => $game->mode,
                'totalQuestions' => $game->total_questions,
            ]);

            if (!$response->successful()) {
                Log::warning('Master regenerate: API Node erreur HTTP', ['status' => $response->status()]);
                throw new \Exception('API Node erreur HTTP ' . $response->status());
            }

            $data = $response->json();

            if (!$data || !($data['success'] ?? false) || !isset($data['question'])) {
                throw new \Exception('Format de réponse API invalide');
            }

            $q = $data['question'];

            // Quête quotidienne : créer/régénérer une question IA
            try {
                app(\App\Services\DailyQuestService::class)->checkAndCompleteDailyQuest(
                    Auth::user(), 'daily_create_ai_question', ['ai_question_created' => true]
                );
            } catch (\Throwable $qe) {
                Log::warning('DailyQuest ai_question_created (regenerate) failed: ' . $qe->getMessage());
            }

            return response()->json([
                'question_text' => $q['text'] ?? 'Question générée',
                'answers' => $q['answers'] ?? ['Réponse A', 'Réponse B', 'Réponse C', 'Réponse D'],
                'correct_answer' => $q['correct_index'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('Master regenerate: Exception', ['error' => $e->getMessage(), 'game_id' => $gameId]);
            return response()->json([
                'question_text' => 'Question générée automatiquement',
                'answers' => ['Réponse A', 'Réponse B', 'Réponse C', 'Réponse D'],
                'correct_answer' => 0,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Page: Sélection de la structure de jeu
    public function showStructure($gameId)
    {
        $game = MasterGame::with('questions')->findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }
        
        // Vérifier que le quiz est validé
        if (!$game->quiz_validated) {
            return redirect()->route('master.compose', $gameId)
                ->with('error', 'Veuillez d\'abord valider votre quiz');
        }
        
        return view('master.structure', compact('game'));
    }

    // POST: Sauvegarder la structure de jeu
    public function saveStructure(Request $request, $gameId)
    {
        $game = MasterGame::findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }
        
        $validated = $request->validate([
            'structure_type' => 'required|in:free_for_all,team_open_skills,team_buzzer_only,multi_team',
            'team_count' => 'nullable|integer|min:2|max:8',
            'team_size_cap' => 'nullable|integer|min:5|max:20',
        ]);
        
        // Déterminer les paramètres selon la structure
        $skillPolicy = 'all_players';
        $buzzRule = 'first_buzz_locks';
        $teamCount = null;
        $teamSizeCap = 20;
        
        switch ($validated['structure_type']) {
            case 'free_for_all':
                $teamCount = null;
                $teamSizeCap = 40;
                break;
            case 'team_open_skills':
                $teamCount = 2;
                $teamSizeCap = $validated['team_size_cap'] ?? 20;
                $skillPolicy = 'all_players';
                break;
            case 'team_buzzer_only':
                $teamCount = 2;
                $teamSizeCap = $validated['team_size_cap'] ?? 20;
                $skillPolicy = 'buzzer_only';
                break;
            case 'multi_team':
                $teamCount = $validated['team_count'] ?? 4;
                $teamSizeCap = $validated['team_size_cap'] ?? 10;
                break;
        }
        
        $game->update([
            'structure_type' => $validated['structure_type'],
            'team_count' => $teamCount,
            'team_size_cap' => $teamSizeCap,
            'skill_policy' => $skillPolicy,
            'buzz_rule' => $buzzRule,
        ]);
        
        // Pour les modes équipe, rediriger vers la config des équipes
        if (in_array($validated['structure_type'], ['team_open_skills', 'team_buzzer_only', 'multi_team'])) {
            return redirect()->route('master.teams', $gameId);
        }
        
        // Pour free_for_all, aller directement au lobby
        return redirect()->route('master.lobby', $gameId);
    }

    // Page: Configuration des équipes
    public function showTeams($gameId)
    {
        $game = MasterGame::with('teams')->findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }
        
        // Créer les équipes par défaut si elles n'existent pas
        if ($game->teams->isEmpty()) {
            $this->createDefaultTeams($game);
            $game->load('teams');
        }
        
        return view('master.teams', compact('game'));
    }

    // POST: Sauvegarder la configuration des équipes
    public function saveTeams(Request $request, $gameId)
    {
        $game = MasterGame::findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }
        
        $validated = $request->validate([
            'teams' => 'required|array',
            'teams.*.id' => 'required|exists:master_game_teams,id',
            'teams.*.name' => 'required|string|max:100',
            'teams.*.color' => 'nullable|string|max:20',
        ]);
        
        foreach ($validated['teams'] as $teamData) {
            MasterGameTeam::where('id', $teamData['id'])
                ->where('master_game_id', $gameId)
                ->update([
                    'name' => $teamData['name'],
                    'color' => $teamData['color'] ?? null,
                ]);
        }
        
        return redirect()->route('master.lobby', $gameId);
    }

    // Helper: Créer les équipes par défaut
    private function createDefaultTeams($game)
    {
        $teamCount = $game->team_count ?? 2;
        $defaultColors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DFE6E9', '#E17055', '#00B894'];
        $defaultNames = ['Équipe Rouge', 'Équipe Bleue', 'Équipe Verte', 'Équipe Jaune', 'Équipe Orange', 'Équipe Violette', 'Équipe Rose', 'Équipe Cyan'];
        
        for ($i = 0; $i < $teamCount; $i++) {
            MasterGameTeam::create([
                'master_game_id' => $game->id,
                'name' => $defaultNames[$i] ?? 'Équipe ' . ($i + 1),
                'color' => $defaultColors[$i] ?? '#CCCCCC',
                'team_order' => $i,
                'max_players' => $game->team_size_cap ?? 10,
            ]);
        }
    }

    // Construire le prompt pour l'IA
    private function buildQuestionPrompt($game, $questionType, $isImageQuestion, $existingQuestions = [], $questionNumber = 1, $subTheme = null)
    {
        $theme = $game->theme ?? $game->school_subject ?? 'culture générale';
        $language = $game->languages[0] ?? 'FR';
        $totalQuestions = $game->questions_count ?? 20;
        
        // Calculer le niveau de difficulté (1-100) basé sur le numéro de question
        // Question 1 = niveau 1, dernière question = niveau 100
        $difficultyLevel = (int) min(100, max(1, ($questionNumber / $totalQuestions) * 100));
        
        // Déterminer le label de difficulté
        $difficultyLabel = $this->getDifficultyLabel($difficultyLevel);
        $difficultyDescription = $this->getDifficultyDescription($difficultyLevel, $isImageQuestion);
        
        // Ajouter les questions existantes pour éviter les doublons
        $avoidDuplicates = "";
        if (!empty($existingQuestions)) {
            $avoidDuplicates = "\n\n⚠️ ATTENTION: NE GÉNÈRE PAS une question similaire ou identique aux questions suivantes déjà créées:\n";
            foreach ($existingQuestions as $index => $existingQ) {
                $avoidDuplicates .= "- " . $existingQ . "\n";
            }
            $avoidDuplicates .= "\nTa nouvelle question doit être TOTALEMENT DIFFÉRENTE et porter sur un autre aspect du thème.\n";
        }
        
        // Instruction de sous-thème pour forcer la variété avec randomisation
        $subThemeInstruction = "";
        if ($subTheme) {
            // Ajouter de la variabilité dans les instructions pour éviter les mêmes questions
            $angleVariations = [
                "Concentre-toi sur un aspect précis et unique de ce sous-thème.",
                "Trouve un angle original et inattendu dans ce sous-thème.",
                "Explore une facette peu connue de ce sous-thème.",
                "Aborde ce sous-thème sous un angle surprenant.",
                "Choisis un élément spécifique et rare dans ce sous-thème.",
                "Sélectionne un détail particulier et distinctif de ce sous-thème.",
                "Questionne sur un cas concret et précis de ce sous-thème.",
                "Invente une question inédite sur ce sous-thème."
            ];
            
            $randomAngle = $angleVariations[array_rand($angleVariations)];
            
            $subThemeInstruction = "\n🎯 SOUS-THÈME IMPOSÉ: {$subTheme}\n";
            $subThemeInstruction .= "⚠️ OBLIGATION: Ta question DOIT porter UNIQUEMENT sur ce sous-thème spécifique.\n";
            $subThemeInstruction .= "{$randomAngle}\n";
            $subThemeInstruction .= "Ne génère PAS de question sur d'autres aspects du thème principal.\n";
        }
        
        // Message de difficulté pour l'IA
        $difficultyInstruction = "\n📊 NIVEAU DE DIFFICULTÉ: {$difficultyLevel}/100 ({$difficultyLabel})\n";
        $difficultyInstruction .= "Question {$questionNumber}/{$totalQuestions}\n";
        $difficultyInstruction .= "{$difficultyDescription}\n";
        
        if ($isImageQuestion) {
            $prompt = "Génère une question de type observation d'image pour un quiz sur le thème: {$theme}.\n\n";
            $prompt .= $subThemeInstruction;
            $prompt .= $difficultyInstruction;
            $prompt .= "IMPORTANT: La question doit tester la capacité d'observation de détails dans une image.\n\n";
            $prompt .= $avoidDuplicates;
            $prompt .= "\nFormat attendu:\n";
            $prompt .= "- Une description d'image détaillée (ex: 'Une jeune fille à lunettes portant des bas blancs devant un sapin avec 2 cadeaux en dessous et une étoile comme ornement')\n";
            $prompt .= "- 4 affirmations sur l'image dont 3 FAUSSES et 1 VRAIE\n";
            $prompt .= "- Les affirmations doivent porter sur des détails observables (couleur, quantité, présence/absence d'éléments)\n\n";
            $prompt .= "Exemple de réponses:\n";
            $prompt .= "1. Elle porte des bas noirs (FAUX - détail incorrect)\n";
            $prompt .= "2. Il y a 3 cadeaux sous le sapin (FAUX - quantité incorrecte)\n";
            $prompt .= "3. Elle porte des lunettes (VRAI - détail correct)\n";
            $prompt .= "4. Une cloche orne le sapin (FAUX - ornement incorrect)\n\n";
            $prompt .= "Réponds UNIQUEMENT avec un JSON valide:\n";
            $prompt .= "{\n";
            $prompt .= '  "question_text": "Description de l\'image",' . "\n";
            $prompt .= '  "answers": ["Affirmation 1", "Affirmation 2", "Affirmation 3", "Affirmation 4"],' . "\n";
            $prompt .= '  "correct_answer": 2' . "\n";
            $prompt .= "}\n\n";
            $prompt .= "Langue: {$language}";
        } else if ($questionType === 'true_false') {
            $prompt = "Génère une question de type Vrai/Faux sur le thème: {$theme}.\n\n";
            $prompt .= $subThemeInstruction;
            $prompt .= $difficultyInstruction;
            $prompt .= $avoidDuplicates;
            $prompt .= "\nRéponds UNIQUEMENT avec un JSON valide:\n";
            $prompt .= "{\n";
            $prompt .= '  "question_text": "Ta question ici",' . "\n";
            $prompt .= '  "answers": ["Vrai", "Faux"],' . "\n";
            $prompt .= '  "correct_answer": 0' . "\n";
            $prompt .= "}\n\n";
            $prompt .= "Langue: {$language}";
        } else {
            $prompt = "Génère une question à choix multiples (QCM) sur le thème: {$theme}.\n\n";
            $prompt .= $subThemeInstruction;
            $prompt .= $difficultyInstruction;
            $prompt .= $avoidDuplicates;
            $prompt .= "\nRéponds UNIQUEMENT avec un JSON valide:\n";
            $prompt .= "{\n";
            $prompt .= '  "question_text": "Ta question ici",' . "\n";
            $prompt .= '  "answers": ["Réponse 1", "Réponse 2", "Réponse 3", "Réponse 4"],' . "\n";
            $prompt .= '  "correct_answer": 0' . "\n";
            $prompt .= "}\n\n";
            $prompt .= "Langue: {$language}";
        }
        
        return $prompt;
    }
    
    // Obtenir le label de difficulté
    private function getDifficultyLabel($level)
    {
        if ($level <= 20) return "Très Facile";
        if ($level <= 40) return "Facile";
        if ($level <= 60) return "Moyen";
        if ($level <= 80) return "Difficile";
        return "Très Difficile";
    }
    
    // Obtenir la description de difficulté pour guider l'IA
    private function getDifficultyDescription($level, $isImageQuestion)
    {
        if ($isImageQuestion) {
            if ($level <= 20) {
                return "Génère une description d'image SIMPLE avec des détails ÉVIDENTS et FACILES à observer (couleurs principales, objets clairement visibles, nombres petits).";
            } else if ($level <= 40) {
                return "Génère une description d'image avec des détails VISIBLES mais nécessitant une observation attentive (positions relatives, vêtements, objets secondaires).";
            } else if ($level <= 60) {
                return "Génère une description d'image avec des détails SUBTILS nécessitant concentration (motifs, textures, petits éléments, arrière-plan).";
            } else if ($level <= 80) {
                return "Génère une description d'image avec des détails COMPLEXES et peu évidents (ombres, reflets, détails cachés, éléments en arrière-plan).";
            } else {
                return "Génère une description d'image TRÈS COMPLEXE avec des détails EXTRÊMEMENT SUBTILS et difficiles à détecter (micro-détails, nuances, éléments partiellement cachés).";
            }
        } else {
            if ($level <= 20) {
                return "Génère une question TRÈS FACILE avec des connaissances de BASE que tout le monde connaît.";
            } else if ($level <= 40) {
                return "Génère une question FACILE avec des connaissances COURANTES accessibles.";
            } else if ($level <= 60) {
                return "Génère une question de difficulté MOYENNE nécessitant des connaissances PRÉCISES.";
            } else if ($level <= 80) {
                return "Génère une question DIFFICILE nécessitant des connaissances APPROFONDIES et SPÉCIALISÉES.";
            } else {
                return "Génère une question TRÈS DIFFICILE pour EXPERTS avec des connaissances POINTUES et RARES.";
            }
        }
    }
    
    // Déterminer le type de question pour un numéro donné (distribution équilibrée)
    private function getQuestionTypeForNumber($game, $questionNumber)
    {
        $questionTypes = $game->question_types ?? ['multiple_choice'];
        
        // Si un seul type de question, toujours utiliser celui-là
        if (count($questionTypes) === 1) {
            return $questionTypes[0];
        }
        
        $totalQuestions = $game->questions_count ?? 20;
        $numTypes = count($questionTypes);
        
        // Calculer combien de questions par type (distribution équilibrée)
        $questionsPerType = floor($totalQuestions / $numTypes);
        $remainder = $totalQuestions % $numTypes;
        
        // Créer un pattern de distribution
        $pattern = [];
        for ($i = 0; $i < $numTypes; $i++) {
            $count = $questionsPerType + ($i < $remainder ? 1 : 0);
            for ($j = 0; $j < $count; $j++) {
                $pattern[] = $questionTypes[$i];
            }
        }
        
        // Mélanger le pattern de façon unique basée sur le game_id
        mt_srand($game->id);
        shuffle($pattern);
        mt_srand(); // Restaurer
        
        // Retourner le type pour ce numéro de question (index 0-based)
        $index = ($questionNumber - 1) % count($pattern);
        return $pattern[$index];
    }
    
    // Générer un sous-thème varié pour forcer la diversité
    private function generateSubTheme($game, $questionNumber, $existingQuestions)
    {
        $theme = $game->theme ?? $game->school_subject ?? 'culture générale';
        
        // Utiliser le game_id comme seed pour randomiser de façon unique par quiz
        mt_srand($game->id);
        
        // Listes de sous-thèmes par thème principal (40+ pour supporter 10, 20, 30, 40 questions)
        $subThemes = [
            'géographie' => [
                'capitales de pays', 'fleuves et rivières', 'montagnes et sommets', 'océans et mers',
                'déserts et climats', 'îles et archipels', 'villes importantes', 'frontières et pays limitrophes',
                'population et démographie', 'langues parlées', 'drapeaux nationaux', 'monuments célèbres',
                'régions et départements', 'volcans', 'lacs', 'forêts', 'parcs naturels', 'continents',
                'pays et superficies', 'fuseaux horaires', 'zones maritimes', 'détroits et canaux',
                'plateaux et plaines', 'deltas et estuaires', 'péninsules', 'golfes et baies',
                'grottes et cavernes', 'cascades', 'glaciers', 'récifs coralliens',
                'zones protégées', 'métropoles mondiales', 'ports maritimes', 'aéroports internationaux',
                'autoroutes et routes', 'tunnels et ponts', 'ressources naturelles', 'industries régionales',
                'agriculture et cultures', 'élevage', 'pêche et aquaculture', 'tourisme'
            ],
            'histoire' => [
                'dates importantes', 'personnages historiques', 'guerres et conflits', 'révolutions',
                'découvertes scientifiques', 'dynasties et rois', 'inventions', 'traités et accords',
                'civilisations anciennes', 'batailles célèbres', 'mouvements sociaux', 'empire et colonies',
                'art et architecture', 'religion et croyances', 'économie historique',
                'constitutions et lois', 'abolitions et réformes', 'épidémies et catastrophes', 'explorations',
                'navigation et voyages', 'commerce et routes', 'monnaies anciennes', 'systèmes politiques',
                'alliances historiques', 'codes et chartes', 'insurrections', 'résistances',
                'exils et migrations', 'conquêtes territoriales', 'indépendances nationales', 'unifications',
                'scissions et séparations', 'référendums', 'couronnements', 'abdications',
                'exécutions célèbres', 'procès historiques', 'espionnage', 'diplomatie',
                'congrès et sommets', 'premiers historiques'
            ],
            'science' => [
                'biologie et animaux', 'physique et forces', 'chimie et éléments', 'astronomie et espace',
                'médecine et santé', 'technologie', 'mathématiques', 'environnement et écologie',
                'météorologie', 'géologie', 'génétique', 'évolution', 'corps humain', 'plantes',
                'bactéries et virus', 'cellules', 'ADN et ARN', 'système nerveux',
                'système cardiovasculaire', 'système digestif', 'système respiratoire', 'hormones',
                'vitamines et minéraux', 'maladies', 'vaccins', 'antibiotiques',
                'électricité', 'magnétisme', 'ondes', 'lumière et optique',
                'thermodynamique', 'mécanique', 'acoustique', 'radioactivité',
                'atomes et molécules', 'réactions chimiques', 'table périodique', 'énergie renouvelable',
                'robotique', 'intelligence artificielle', 'nanotechnologie', 'biotechnologie'
            ],
            'culture générale' => [
                'cinéma et films', 'musique et artistes', 'littérature et écrivains', 'sports et athlètes',
                'gastronomie', 'art et peinture', 'télévision', 'mode et tendances',
                'célébrités', 'jeux vidéo', 'traditions', 'fêtes', 'langues', 'religion',
                'philosophie', 'mythologie', 'contes et légendes', 'proverbes',
                'BD et comics', 'mangas et anime', 'séries télévisées', 'émissions cultes',
                'chansons populaires', 'albums musicaux', 'instruments de musique', 'genres musicaux',
                'romans célèbres', 'poésie', 'théâtre classique', 'prix littéraires',
                'recettes traditionnelles', 'vins et spiritueux', 'fromages', 'pâtisseries',
                'marques célèbres', 'logos', 'slogans publicitaires', 'inventions du quotidien',
                'expressions populaires', 'superstitions', 'zodiac et astrologie', 'symboles'
            ],
            'sport' => [
                'football', 'basketball', 'tennis', 'rugby', 'athlétisme', 'natation',
                'cyclisme', 'jeux olympiques', 'records sportifs', 'équipes célèbres',
                'stades et infrastructures', 'compétitions internationales', 'sports d\'hiver',
                'handball', 'volleyball', 'golf', 'formule 1', 'moto GP',
                'boxe', 'arts martiaux', 'judo', 'karaté', 'taekwondo',
                'escrime', 'aviron', 'voile', 'surf', 'plongée',
                'ski alpin', 'ski de fond', 'biathlon', 'patinage artistique', 'hockey sur glace',
                'baseball', 'cricket', 'football américain', 'basketball féminin', 'tennis de table',
                'badminton', 'squash', 'équitation', 'hippisme', 'sports extrêmes'
            ],
            'culture' => [
                'peinture et tableaux', 'sculpteurs', 'architectes', 'musées', 'opéra et théâtre',
                'danse', 'cinéma mondial', 'festivals', 'prix et récompenses', 'mouvements artistiques',
                'impressionnisme', 'cubisme', 'surréalisme', 'renaissance', 'baroque',
                'art moderne', 'art contemporain', 'street art', 'photographie', 'design',
                'calligraphie', 'gravure', 'mosaïque', 'vitraux', 'tapisserie',
                'céramique', 'poterie', 'bijouterie', 'orfèvrerie', 'horlogerie',
                'couture haute gamme', 'parfumerie', 'ballets célèbres', 'compositeurs classiques', 'symphonies',
                'opéras célèbres', 'cathédrales', 'châteaux et palais', 'monuments antiques', 'art africain',
                'art asiatique', 'art précolombien', 'patrimoine UNESCO', 'expositions internationales'
            ]
        ];
        
        // Trouver la liste de sous-thèmes appropriée
        $themeLower = strtolower($theme);
        $availableSubThemes = [];
        
        foreach ($subThemes as $key => $values) {
            if (stripos($themeLower, $key) !== false) {
                $availableSubThemes = $values;
                break;
            }
        }
        
        // Si aucun sous-thème prédéfini, générer des variations génériques
        if (empty($availableSubThemes)) {
            $availableSubThemes = [
                "aspect culturel de {$theme}", "dimension historique de {$theme}",
                "personnalités liées à {$theme}", "événements importants de {$theme}",
                "chiffres et données sur {$theme}", "lieux et géographie de {$theme}",
                "terminologie de {$theme}", "concepts clés de {$theme}",
                "évolution de {$theme}", "impact social de {$theme}",
                "origines de {$theme}", "développement de {$theme}",
                "influences de {$theme}", "techniques de {$theme}",
                "pratiques de {$theme}", "théories de {$theme}",
                "applications de {$theme}", "innovations dans {$theme}",
                "tendances de {$theme}", "défis de {$theme}",
                "réussites dans {$theme}", "échecs dans {$theme}",
                "controverses de {$theme}", "avenir de {$theme}",
                "légendes de {$theme}", "mythes de {$theme}",
                "vérités sur {$theme}", "mensonges sur {$theme}",
                "secrets de {$theme}", "mystères de {$theme}",
                "découvertes dans {$theme}", "révolutions dans {$theme}",
                "traditions de {$theme}", "modernisation de {$theme}",
                "globalisation de {$theme}", "localisation de {$theme}",
                "diversité dans {$theme}", "unité dans {$theme}",
                "conflits dans {$theme}", "harmonies dans {$theme}",
                "ruptures dans {$theme}", "continuités dans {$theme}"
            ];
        }
        
        // Mélanger les sous-thèmes de façon aléatoire mais consistante pour ce quiz
        shuffle($availableSubThemes);
        
        // Restaurer le générateur aléatoire à son état normal
        mt_srand();
        
        // Sélectionner un sous-thème basé sur le numéro de question (rotation dans l'ordre mélangé)
        $index = ($questionNumber - 1) % count($availableSubThemes);
        return $availableSubThemes[$index];
    }

    // Page 5: Générer les codes
    public function codes($gameId = null)
    {
        $userId = Auth::id();
        $game = null;

        if ($gameId) {
            $game = MasterGame::findOrFail($gameId);

            if ($game->host_user_id !== $userId) {
                abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
            }

            session(['master_selected_quiz_id' => $game->id]);
        } else {
            $sessionQuizId = session('master_selected_quiz_id');
            if ($sessionQuizId) {
                $game = MasterGame::where('id', $sessionQuizId)
                    ->where('host_user_id', $userId)
                    ->first();
            }

            if (!$game) {
                $game = MasterGame::where('host_user_id', $userId)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($game) {
                    session(['master_selected_quiz_id' => $game->id]);
                }
            }
        }

        $history = MasterGame::where('host_user_id', $userId)
            ->when($game, fn($q) => $q->where('id', '!=', $game->id))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('master.codes', compact('game', 'history'));
    }

    public function preview($gameId)
    {
        $game = MasterGame::with('questionsOrdered')->findOrFail($gameId);

        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }

        return view('master.preview', compact('game'));
    }

    public function selectQuiz($gameId)
    {
        $game = MasterGame::findOrFail($gameId);

        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }

        session(['master_selected_quiz_id' => $game->id]);

        return redirect()->route('master.codes')->with('success', __('Quiz sélectionné'));
    }

    public function destroyQuiz($gameId)
    {
        $game = MasterGame::findOrFail($gameId);

        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }

        if (session('master_selected_quiz_id') === $game->id) {
            session()->forget('master_selected_quiz_id');
        }

        $game->delete();

        return redirect()->route('master.codes')->with('success', __('Quiz supprimé'));
    }

    // Générer toutes les questions automatiquement (Mode Automatique)
    private function generateAllQuestions($game)
    {
        $totalQuestions = $game->total_questions;
        $aiImagesCount = $game->ai_images_count ?? 0;
        $aiImagesGenerated = 0;
        
        // Système anti-duplication : suivre les questions déjà générées
        $generatedQuestions = [];
        
        // Identifier les positions des questions image
        $imagePositions = [];
        for ($i = 1; $i <= $totalQuestions; $i++) {
            $questionType = $this->getQuestionTypeForNumber($game, $i);
            if ($questionType === 'image') {
                $imagePositions[] = $i;
            }
        }
        
        // Mélanger les positions pour distribuer aléatoirement les images IA
        shuffle($imagePositions);
        $aiImagePositions = array_slice($imagePositions, 0, $aiImagesCount);
        
        // Distribution égale des types de questions avec modulo
        for ($i = 1; $i <= $totalQuestions; $i++) {
            $questionType = $this->getQuestionTypeForNumber($game, $i);
            
            if ($questionType === 'image') {
                // Vérifier si cette position doit avoir une image IA
                if (in_array($i, $aiImagePositions) && $aiImagesGenerated < $aiImagesCount) {
                    // Générer une question image-mémoire via Imagen (Gemini API)
                    $success = $this->generateAIImageQuestion($game, $i);
                    if ($success) {
                        $aiImagesGenerated++;
                    } else {
                        // Fallback : créer un template vide si la génération échoue
                        $this->createEmptyImageQuestionTemplate($game, $i);
                    }
                } else {
                    // Pour les autres questions image : créer un template vide
                    $this->createEmptyImageQuestionTemplate($game, $i);
                }
            } else {
                // Pour les questions texte (MC ou True/False) : générer via API Node (Gemini)
                $questionText = $this->generateTextQuestionWithAI($game, $i, $questionType, $generatedQuestions);
                if ($questionText) {
                    $generatedQuestions[] = $questionText;
                }
            }
        }
        
        // Générer la question de départage (toujours en dernier)
        $this->generateTiebreakerQuestion($game, (($game->total_questions * 3) + 1), $generatedQuestions);
    }

    // Génération initiale complète du quiz (Manches 1-3 + Manche Ultime)
    private function generateInitialQuizStructure($game)
    {
        $perManche = $game->total_questions;
        $base = $perManche * 3;

        // Manches 1 à 3
        $this->generateQuestionsForManche($game, 1);
        $this->generateQuestionsForManche($game, 2);
        $this->generateQuestionsForManche($game, 3);

        // Manche Ultime
        if ($game->tiebreaker_mode === 'bonus') {
            $this->generateTiebreakerQuestion($game, $base + 1, []);
        }

        if ($game->tiebreaker_mode === 'sudden_death') {
            for ($i = 1; $i <= 5; $i++) {
                $this->generateTiebreakerQuestion($game, $base + $i, []);
            }
        }

        // Bonus Avatar Épique
        if ($game->strategic_avatars_enabled && in_array('Épique', $game->strategic_avatars_tiers ?? [])) {
            $this->generateTextQuestionWithAI($game, $base + 6, 'multiple_choice', []);
        }
    }

    // Génération par manche (nouveau flux MJ)
    private function generateQuestionsForManche($game, $manche)
    {
        $questionsPerManche = $game->total_questions;
        $start = ($manche - 1) * $questionsPerManche + 1;
        $end = $start + $questionsPerManche - 1;

        $generatedQuestions = [];

        for ($i = $start; $i <= $end; $i++) {
            $questionType = $this->getQuestionTypeForNumber($game, $i);

            if ($questionType === 'image') {
                $this->createEmptyImageQuestionTemplate($game, $i);
            } else {
                $questionText = $this->generateTextQuestionWithAI($game, $i, $questionType, $generatedQuestions);
                if ($questionText) {
                    $generatedQuestions[] = $questionText;
                }
            }
        }
    }

    // Générer la question de départage
    private function generateTiebreakerQuestion($game, $questionNumber, $previousQuestions = [])
    {
        // #88 hard-gate: AI composition is composition-only. Once the match
        // is live (`started_at` set), tiebreakers MUST come from the local
        // default pool — never from /generate-master-question.
        if ($game->started_at !== null) {
            Log::info('Master: live tiebreaker — bypassing AI per #88, using default', [
                'game_id' => $game->id, 'question_number' => $questionNumber,
            ]);
            $this->createDefaultTiebreakerQuestion($game, $questionNumber, strtolower($game->language ?? 'fr'));
            return;
        }
        try {
            $language = strtolower($game->language ?? 'fr');
            
            // Déterminer le thème
            if ($game->domain_type === 'theme') {
                $theme = $game->theme ?? 'Culture générale';
            } else {
                $theme = ($game->school_subject ?? 'Culture générale') . ' - ' . ($game->school_level ?? 'Général');
            }
            
            // Appeler l'API Node.js pour générer une question difficile
            // #88: composition endpoint is admin-locked, send shared secret header.
            $apiUrl = env('QUESTION_API_URL', 'http://localhost:3000') . '/generate-master-question';
            $adminToken = (string) env('MASTER_API_ADMIN_TOKEN', '');
            
            $response = Http::timeout(30)
                ->withHeaders(['X-Admin-Token' => $adminToken])
                ->post($apiUrl, [
                    'theme' => $theme . ' (question difficile de départage)',
                    'language' => $language,
                    'questionType' => 'multiple_choice',
                    'questionNumber' => $questionNumber,
                    'previousQuestions' => $previousQuestions,
                    'gameSeed' => $game->id,
                    'domainType' => $game->domain_type,
                    'schoolLevel' => $game->school_level,
                    'schoolGrade' => $game->school_grade,
                    'schoolSubject' => $game->school_subject,
                    'schoolCountry' => $game->school_country,
                    'mode' => $game->mode,
                    'totalQuestions' => $game->total_questions,
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data && isset($data['success']) && $data['success']) {
                    MasterGameQuestion::create([
                        'master_game_id' => $game->id,
                        'question_number' => $questionNumber,
                        'type' => 'multiple_choice',
                        'text' => $data['question']['text'] ?? 'Question de départage',
                        'choices' => $data['question']['answers'] ?? ['Réponse 1', 'Réponse 2', 'Réponse 3', 'Réponse 4'],
                        'correct_indexes' => [$data['question']['correct_index'] ?? 0],
                        'media_url' => null,
                        'is_tiebreaker' => true,
                    ]);
                    
                    Log::info('Master: Question de départage générée', [
                        'game_id' => $game->id,
                        'question_number' => $questionNumber
                    ]);
                    return;
                }
            }
            
            // Fallback : créer une question de départage par défaut
            $this->createDefaultTiebreakerQuestion($game, $questionNumber, $language);
            
        } catch (\Exception $e) {
            Log::error('Master: Exception génération question de départage', [
                'game_id' => $game->id,
                'error' => $e->getMessage()
            ]);
            $this->createDefaultTiebreakerQuestion($game, $questionNumber, strtolower($game->language ?? 'fr'));
        }
    }
    
    // Créer une question de départage par défaut
    private function createDefaultTiebreakerQuestion($game, $questionNumber, $language)
    {
        $tiebreakerQuestions = [
            'fr' => [
                'text' => 'Combien y a-t-il de secondes dans une journée ?',
                'choices' => ['86 400', '3 600', '24 000', '43 200'],
                'correct' => 0
            ],
            'en' => [
                'text' => 'How many seconds are there in a day?',
                'choices' => ['86,400', '3,600', '24,000', '43,200'],
                'correct' => 0
            ],
            'es' => [
                'text' => '¿Cuántos segundos hay en un día?',
                'choices' => ['86.400', '3.600', '24.000', '43.200'],
                'correct' => 0
            ],
            'it' => [
                'text' => 'Quanti secondi ci sono in un giorno?',
                'choices' => ['86.400', '3.600', '24.000', '43.200'],
                'correct' => 0
            ],
            'de' => [
                'text' => 'Wie viele Sekunden hat ein Tag?',
                'choices' => ['86.400', '3.600', '24.000', '43.200'],
                'correct' => 0
            ],
            'pt' => [
                'text' => 'Quantos segundos existem em um dia?',
                'choices' => ['86.400', '3.600', '24.000', '43.200'],
                'correct' => 0
            ],
            'ru' => [
                'text' => 'Сколько секунд в сутках?',
                'choices' => ['86 400', '3 600', '24 000', '43 200'],
                'correct' => 0
            ],
            'ar' => [
                'text' => 'كم عدد الثواني في اليوم؟',
                'choices' => ['86,400', '3,600', '24,000', '43,200'],
                'correct' => 0
            ],
            'zh' => [
                'text' => '一天有多少秒？',
                'choices' => ['86,400', '3,600', '24,000', '43,200'],
                'correct' => 0
            ],
            'el' => [
                'text' => 'Πόσα δευτερόλεπτα υπάρχουν σε μια μέρα;',
                'choices' => ['86.400', '3.600', '24.000', '43.200'],
                'correct' => 0
            ]
        ];
        
        $q = $tiebreakerQuestions[$language] ?? $tiebreakerQuestions['fr'];
        
        MasterGameQuestion::create([
            'master_game_id' => $game->id,
            'question_number' => $questionNumber,
            'type' => 'multiple_choice',
            'text' => $q['text'],
            'choices' => $q['choices'],
            'correct_indexes' => [$q['correct']],
            'media_url' => null,
            'is_tiebreaker' => true,
        ]);
    }
    
    // Générer une question image-mémoire via Imagen (Gemini API)
    private function generateAIImageQuestion($game, $questionNumber)
    {
        try {
            $imageService = new ImageGenerationService();
            $language = strtolower($game->languages[0] ?? 'fr');
            
            Log::info('Master: Génération image IA', [
                'game_id' => $game->id,
                'question_number' => $questionNumber,
                'language' => $language
            ]);
            
            $result = $imageService->generateImageQuestion($questionNumber, $language);
            
            if (!$result) {
                Log::warning('Master: Échec génération image IA', [
                    'game_id' => $game->id,
                    'question_number' => $questionNumber
                ]);
                return false;
            }
            
            // Créer la question avec l'image générée
            MasterGameQuestion::create([
                'master_game_id' => $game->id,
                'question_number' => $questionNumber,
                'type' => 'image',
                'text' => $result['question_text'],
                'choices' => $result['answers'],
                'correct_indexes' => [$result['correct_answer']],
                'media_url' => $result['question_image'],
            ]);
            
            Log::info('Master: Image IA générée avec succès', [
                'game_id' => $game->id,
                'question_number' => $questionNumber,
                'image_path' => $result['question_image']
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Master: Exception génération image IA', [
                'game_id' => $game->id,
                'question_number' => $questionNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    // Créer un template vide pour une question image
    private function createEmptyImageQuestionTemplate($game, $questionNumber)
    {
        $language = strtolower($game->language ?? 'fr');
        
        // Texte placeholder selon la langue
        $placeholderTexts = [
            'fr' => 'Quel élément était visible dans l\'image ?',
            'en' => 'Which element was visible in the image?',
            'es' => '¿Qué elemento era visible en la imagen?',
            'it' => 'Quale elemento era visibile nell\'immagine?',
            'de' => 'Welches Element war im Bild sichtbar?',
            'pt' => 'Qual elemento era visível na imagem?',
            'ru' => 'Какой элемент был виден на изображении?',
            'ar' => 'ما العنصر الذي كان مرئيًا في الصورة؟',
            'zh' => '图片中可见的是什么元素？',
            'el' => 'Ποιο στοιχείο ήταν ορατό στην εικόνα;'
        ];
        
        $placeholderText = $placeholderTexts[$language] ?? $placeholderTexts['fr'];
        
        MasterGameQuestion::create([
            'master_game_id' => $game->id,
            'question_number' => $questionNumber,
            'type' => 'image',
            'text' => $placeholderText,
            'choices' => ['Option 1', 'Option 2', 'Option 3', 'Option 4'],
            'correct_indexes' => [0],
            'media_url' => null,
        ]);
    }
    
    // Générer une question texte via l'API Node.js
    private function generateTextQuestionWithAI($game, $questionNumber, $questionType, $previousQuestions = [])
    {
        // #88 hard-gate: AI text generation is composition-only. Once the
        // match is live (`started_at` set), this method MUST NOT call
        // /generate-master-question — fall back to a placeholder so the
        // host has to fill it manually or the bank/seed pool can take over.
        if ($game->started_at !== null) {
            Log::info('Master: live text generation — bypassing AI per #88, placeholder', [
                'game_id' => $game->id, 'question_number' => $questionNumber,
            ]);
            $this->createPlaceholderQuestion($game, $questionNumber, $questionType);
            return null;
        }
        try {
            $language = strtolower($game->language ?? 'fr');
            
            // Déterminer le thème ou contexte
            if ($game->domain_type === 'theme') {
                $theme = $game->theme ?? 'Culture générale';
            } else {
                $theme = ($game->school_subject ?? 'Culture générale') . ' - ' . ($game->school_level ?? 'Général');
            }
            
            // Appeler l'API Node.js pour générer la question
            // #88: composition endpoint is admin-locked, send shared secret header.
            $apiUrl = env('QUESTION_API_URL', 'http://localhost:3000') . '/generate-master-question';
            $adminToken = (string) env('MASTER_API_ADMIN_TOKEN', '');
            
            $response = Http::timeout(30)
                ->withHeaders(['X-Admin-Token' => $adminToken])
                ->post($apiUrl, [
                    'theme' => $theme,
                    'language' => $language,
                    'questionType' => $questionType,
                    'questionNumber' => $questionNumber,
                    'previousQuestions' => $previousQuestions,
                    'gameSeed' => $game->id,
                    'domainType' => $game->domain_type,
                    'schoolLevel' => $game->school_level,
                    'schoolGrade' => $game->school_grade,
                    'schoolSubject' => $game->school_subject,
                    'schoolCountry' => $game->school_country,
                    'mode' => $game->mode,
                    'totalQuestions' => $game->total_questions,
                ]);
            
            if (!$response->successful()) {
                Log::warning('Master: API Node.js non accessible ou refusée', [
                    'game_id' => $game->id,
                    'question_number' => $questionNumber,
                    'status' => $response->status(),
                ]);
                $this->createPlaceholderQuestion($game, $questionNumber, $questionType);
                return null;
            }
            
            $data = $response->json();
            
            if (!$data || !isset($data['success']) || !$data['success']) {
                Log::warning('Master: Réponse API invalide', [
                    'game_id' => $game->id,
                    'question_number' => $questionNumber,
                    'response' => $response->body(),
                ]);
                $this->createPlaceholderQuestion($game, $questionNumber, $questionType);
                return null;
            }
            
            $questionText = $data['question']['text'] ?? 'Question générée';
            
            // Créer la question avec les données générées
            MasterGameQuestion::create([
                'master_game_id' => $game->id,
                'question_number' => $questionNumber,
                'type' => $questionType,
                'text' => $questionText,
                'choices' => $data['question']['answers'] ?? ['Réponse 1', 'Réponse 2', 'Réponse 3', 'Réponse 4'],
                'correct_indexes' => [$data['question']['correct_index'] ?? 0],
                'media_url' => null,
            ]);
            
            Log::info('Master: Question générée avec succès', [
                'game_id' => $game->id,
                'question_number' => $questionNumber,
                'type' => $questionType
            ]);
            
            return $questionText;
            
        } catch (\Exception $e) {
            Log::error('Master: Exception génération question', [
                'game_id' => $game->id,
                'question_number' => $questionNumber,
                'error' => $e->getMessage()
            ]);
            $this->createPlaceholderQuestion($game, $questionNumber, $questionType);
            return null;
        }
    }
    
    // Construire le prompt pour la génération de question (legacy - utilisé par parseAIResponse)
    private function buildPromptForQuestion($game, $questionType)
    {
        $language = $game->language ?? 'FR';
        $languageNames = ['FR' => 'français', 'EN' => 'anglais', 'ES' => 'espagnol', 'DE' => 'allemand'];
        $languageName = $languageNames[$language] ?? 'français';
        
        // Déterminer le contexte (thème ou scolaire)
        if ($game->domain_type === 'theme') {
            $context = "sur le thème : {$game->theme}";
        } else {
            $context = "pour le niveau scolaire : {$game->school_level}";
            if ($game->school_grade) {
                $context .= ", année {$game->school_grade}";
            }
            if ($game->school_subject) {
                $context .= ", matière : {$game->school_subject}";
            }
            if ($game->school_country) {
                $context .= " ({$game->school_country})";
            }
        }
        
        if ($questionType === 'true_false') {
            return "Génère une question Vrai/Faux {$context}. Réponds en {$languageName}.\n\n" .
                   "Format de réponse EXACTEMENT comme ceci:\n" .
                   "QUESTION: [ta question]\n" .
                   "REPONSE1: Vrai\n" .
                   "REPONSE2: Faux\n" .
                   "CORRECT: [0 ou 1]";
        } else {
            return "Génère une question à choix multiples avec 4 réponses {$context}. Réponds en {$languageName}.\n\n" .
                   "Format de réponse EXACTEMENT comme ceci:\n" .
                   "QUESTION: [ta question]\n" .
                   "REPONSE1: [réponse 1]\n" .
                   "REPONSE2: [réponse 2]\n" .
                   "REPONSE3: [réponse 3]\n" .
                   "REPONSE4: [réponse 4]\n" .
                   "CORRECT: [0, 1, 2 ou 3]";
        }
    }
    
    private function parseAIResponse($content, $questionType)
    {
        $lines = explode("\n", $content);
        $question = '';
        $answers = [];
        $correct = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'QUESTION:') === 0) {
                $question = trim(substr($line, 9));
            } elseif (preg_match('/^REPONSE(\d+):\s*(.+)$/', $line, $matches)) {
                $answers[] = trim($matches[2]);
            } elseif (strpos($line, 'CORRECT:') === 0) {
                $correct = (int) trim(substr($line, 8));
            }
        }
        
        // Validation et valeurs par défaut
        if (empty($question)) {
            $question = 'Question générée';
        }
        
        if ($questionType === 'true_false') {
            if (count($answers) < 2) {
                $answers = ['Vrai', 'Faux'];
            } else {
                $answers = array_slice($answers, 0, 2);
            }
        } else {
            if (count($answers) < 4) {
                while (count($answers) < 4) {
                    $answers[] = 'Réponse ' . (count($answers) + 1);
                }
            } else {
                $answers = array_slice($answers, 0, 4);
            }
        }
        
        return [
            'question' => $question,
            'answers' => $answers,
            'correct_answer' => max(0, min($correct, count($answers) - 1))
        ];
    }
    
    // Créer une question placeholder en cas d'erreur
    private function createPlaceholderQuestion($game, $questionNumber, $questionType)
    {
        if ($questionType === 'true_false') {
            MasterGameQuestion::create([
                'master_game_id' => $game->id,
                'question_number' => $questionNumber,
                'type' => 'true_false',
                'text' => 'Question à compléter',
                'choices' => ['Vrai', 'Faux'],
                'correct_indexes' => [0],
                'media_url' => null,
            ]);
        } else {
            MasterGameQuestion::create([
                'master_game_id' => $game->id,
                'question_number' => $questionNumber,
                'type' => 'multiple_choice',
                'text' => 'Question à compléter',
                'choices' => ['Réponse 1', 'Réponse 2', 'Réponse 3', 'Réponse 4'],
                'correct_indexes' => [0],
                'media_url' => null,
            ]);
        }
    }
    
    // Générer un code unique
    private function generateUniqueGameCode()
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (MasterGame::where('game_code', $code)->exists());

        return $code;
    }

    // Générer un code d'invitation unique
    private function generateUniqueInviteCode()
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (MasterGameCode::where('code', $code)->exists());

        return $code;
    }
    
    // ===== MÉTHODES DE JEU EN TEMPS RÉEL =====
    
    /**
     * Lobby: Affiche le lobby avec les participants qui rejoignent
     */
    public function lobby($gameId)
    {
        $game = MasterGame::with(['players.user', 'questions'])->findOrFail($gameId);
        
        return view('master.lobby', compact('game'));
    }
    
    /**
     * Rejoindre une partie (participant)
     */
    public function joinGame(Request $request, $gameId)
    {
        $user = Auth::user();
        $game = MasterGame::findOrFail($gameId);
        
        // Vérifier que le jeu est en lobby
        if ($game->status !== 'lobby') {
            return response()->json([
                'success' => false,
                'message' => 'Le jeu a déjà commencé ou est terminé'
            ], 400);
        }
        
        // Créer ou mettre à jour le joueur PostgreSQL
        $player = MasterGamePlayer::firstOrCreate(
            [
                'master_game_id' => $gameId,
                'user_id' => $user->id,
            ],
            [
                'score' => 0,
                'answered' => [],
                'status' => 'joined'
            ]
        );
        
        // Ajouter à la session Firestore
        $this->firestoreService->addParticipant($gameId, $user->id, $user->name);
        
        return response()->json([
            'success' => true,
            'message' => 'Vous avez rejoint la partie',
            'player' => $player
        ]);
    }
    
    /**
     * Quitter une partie (participant)
     */
    public function leaveGame(Request $request, $gameId)
    {
        $user = Auth::user();
        $game = MasterGame::findOrFail($gameId);
        
        // Supprimer le joueur PostgreSQL
        MasterGamePlayer::where('master_game_id', $gameId)
            ->where('user_id', $user->id)
            ->delete();
        
        // Retirer de la session Firestore
        $this->firestoreService->removeParticipant($gameId, $user->id);
        
        return response()->json([
            'success' => true,
            'message' => 'Vous avez quitté la partie'
        ]);
    }
    
    /**
     * Récupère l'état du jeu en temps réel (polling)
     */
    public function syncGameState(Request $request, $gameId)
    {
        $gameState = $this->firestoreService->syncGameState((int) $gameId);
        
        if (!$gameState) {
            return response()->json([
                'success' => false,
                'message' => 'Session non trouvée'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'gameState' => $gameState
        ]);
    }
    
    /**
     * Valide le quiz (marque toutes les questions comme finalisées)
     */
    public function validateQuiz(Request $request, $gameId)
    {
        $game = MasterGame::findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas l\'hôte de cette partie'
            ], 403);
        }
        
        $game->quiz_validated = true;
        $game->status = 'lobby';
        $game->save();
        
        // Créer la session Firestore pour le lobby
        $this->firestoreService->createGameSession($game->id, [
            'host_id' => $game->host_user_id,
            'host_name' => $game->host->name ?? 'Host',
            'game_mode' => $game->mode,
            'total_questions' => ($game->total_questions * 3),
            'participants_expected' => $game->participants_expected,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Quiz validé et lobby créé',
            'game' => $game
        ]);
    }
    
    /**
     * Démarre le jeu (passe du lobby au jeu)
     */
    public function startGame(Request $request, $gameId)
    {
        $game = MasterGame::findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'hôte peut démarrer le jeu'
            ], 403);
        }
        
        // Mettre à jour PostgreSQL
        $game->status = 'playing';
        $game->current_question = 1;
        $game->started_at = now();
        $game->save();
        
        // Démarrer dans Firestore
        $this->firestoreService->startGame($game->id);
        
        return response()->json([
            'success' => true,
            'message' => 'Jeu démarré',
            'game' => $game
        ]);
    }
    
    /**
     * Passe à la question suivante
     */
    public function nextQuestion(Request $request, $gameId)
    {
        $game = MasterGame::findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'hôte peut avancer les questions'
            ], 403);
        }
        
        $nextQuestionNumber = $game->current_question + 1;
        
        if ($nextQuestionNumber > ($game->total_questions * 3)) {
            return response()->json([
                'success' => false,
                'message' => 'C\'est déjà la dernière question'
            ], 400);
        }
        
        // Mettre à jour PostgreSQL
        $game->current_question = $nextQuestionNumber;
        $game->save();
        
        // Mettre à jour Firestore
        $this->firestoreService->nextQuestion($game->id, $nextQuestionNumber);
        
        return response()->json([
            'success' => true,
            'current_question' => $nextQuestionNumber,
            'question' => MasterGameQuestion::where('master_game_id', $gameId)
                ->where('question_number', $nextQuestionNumber)
                ->first()
        ]);
    }
    
    /**
     * Soumet la réponse d'un participant
     */
    public function submitAnswer(Request $request, $gameId)
    {
        $validated = $request->validate([
            'question_number' => 'required|integer|min:1',
            'answer_index' => 'required|integer|min:0|max:3',
        ]);
        
        $user = Auth::user();
        $game = MasterGame::findOrFail($gameId);
        
        // Récupérer la question
        $question = MasterGameQuestion::where('master_game_id', $gameId)
            ->where('question_number', $validated['question_number'])
            ->firstOrFail();
        
        // Vérifier si la réponse est correcte
        $isCorrect = in_array($validated['answer_index'], $question->correct_indexes);
        
        // Calculer le score (exemple: +10 si correct, 0 sinon)
        $score = $isCorrect ? 10 : 0;
        
        // Mettre à jour ou créer le joueur PostgreSQL
        $player = MasterGamePlayer::updateOrCreate(
            [
                'master_game_id' => $gameId,
                'user_id' => $user->id,
            ],
            [
                'status' => 'playing'
            ]
        );
        
        // Mettre à jour le score et les réponses
        $answered = $player->answered ?? [];
        $answered[$validated['question_number']] = $validated['answer_index'];
        $player->answered = $answered;
        $player->score += $score;
        $player->save();
        
        // Enregistrer dans Firestore
        $this->firestoreService->recordAnswer(
            $gameId,
            $validated['question_number'],
            $user->id,
            $validated['answer_index'],
            $isCorrect,
            $score
        );
        
        // Mettre à jour le score Firestore
        $this->firestoreService->updateParticipantScore(
            $gameId,
            $user->id,
            $player->score,
            array_keys($answered)
        );
        
        return response()->json([
            'success' => true,
            'is_correct' => $isCorrect,
            'score' => $score,
            'total_score' => $player->score,
        ]);
    }
    
    /**
     * Termine le jeu
     */
    public function finishGame(Request $request, $gameId)
    {
        $game = MasterGame::findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'hôte peut terminer le jeu'
            ], 403);
        }
        
        // Déterminer le gagnant (joueur avec le score le plus élevé)
        $winner = MasterGamePlayer::where('master_game_id', $gameId)
            ->orderBy('score', 'desc')
            ->first();
        
        // Mettre à jour PostgreSQL
        $game->status = 'finished';
        $game->ended_at = now();
        $game->save();

        // Quêtes Maître du Jeu (organisateur)
        try {
            $host = Auth::user();
            if ($host) {
                $questService = app(\App\Services\QuestService::class);
                foreach (['master_games_hosted_epique', 'master_games_hosted_legendaire', 'master_games_hosted_maitre'] as $code) {
                    $questService->checkAndCompleteQuests($host, $code, ['master_game_hosted' => true]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Quest hook error in MasterGameController::finishGame: ' . $e->getMessage());
        }

        // Terminer dans Firestore
        $this->firestoreService->finishGame($game->id, $winner?->user_id);
        
        // Cleanup: supprimer la session Firestore
        $this->firestoreService->deleteGameSession($game->id);
        
        return response()->json([
            'success' => true,
            'winner' => $winner ? $winner->load('user') : null,
            'players' => MasterGamePlayer::where('master_game_id', $gameId)
                ->with('user')
                ->orderBy('score', 'desc')
                ->get()
        ]);
    }
    
    /**
     * Annule le jeu (avant qu'il ne démarre ou en cours)
     */
    public function cancelGame(Request $request, $gameId)
    {
        $game = MasterGame::findOrFail($gameId);
        
        if ($game->host_user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'hôte peut annuler le jeu'
            ], 403);
        }
        
        // Mettre à jour PostgreSQL
        $game->status = 'cancelled';
        $game->save();
        
        // Cleanup Firestore
        if ($this->firestoreService->sessionExists($game->id)) {
            $this->firestoreService->deleteGameSession($game->id);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Jeu annulé'
        ]);
    }
    
    // ===== PLAYER JOIN & INVITE FEATURES =====
    
    /**
     * Show the secure join form - no game info exposed until code is validated
     * Players only see a form to enter the game code
     */
    public function showJoinForm()
    {
        $user = Auth::user();
        
        // Get player level from duo stats
        $playerLevel = 0;
        if ($user->playerDuoStat) {
            $playerLevel = $user->playerDuoStat->level ?? 0;
        }
        
        return view('master.player-join', compact('user', 'playerLevel'));
    }
    
    /**
     * Process player join - validates code first, then finds game and registers player
     * No gameId in URL prevents bypass attacks
     * Rate limited to prevent brute force attacks on game codes
     */
    public function processJoin(Request $request)
    {
        $validated = $request->validate([
            'game_code' => 'required|string|size:6'
        ]);
        
        $user = Auth::user();
        $gameCode = strtoupper($validated['game_code']);
        
        // Rate limiting: max 5 attempts per minute per user
        $cacheKey = 'master_join_attempts_' . $user->id;
        $attempts = cache($cacheKey, 0);
        
        if ($attempts >= 5) {
            Log::warning('Master join rate limit exceeded', [
                'user_id' => $user->id,
                'attempts' => $attempts
            ]);
            return back()->with('error', __('Trop de tentatives. Veuillez réessayer dans une minute.'));
        }
        
        // Find game by code - this is the only way to discover a game
        $game = MasterGame::where('game_code', $gameCode)->first();
        
        if (!$game) {
            // Increment failed attempts counter
            cache([$cacheKey => $attempts + 1], now()->addMinute());
            Log::info('Master join failed - invalid code', [
                'user_id' => $user->id,
                'attempted_code' => $gameCode
            ]);
            return back()->with('error', __('Code de partie invalide'));
        }
        
        // Check if game is accepting players
        if (!in_array($game->status, ['draft', 'lobby'])) {
            return back()->with('error', __('Cette partie n\'accepte plus de joueurs'));
        }
        
        // Check max players limit
        $currentPlayerCount = MasterGamePlayer::where('master_game_id', $game->id)
            ->where('status', 'joined')
            ->count();
        
        if ($currentPlayerCount >= ($game->participants_expected ?? 40)) {
            return back()->with('error', __('Cette partie est complète'));
        }
        
        // Clear rate limit on successful join
        cache()->forget($cacheKey);
        
        // Create or update the player registration
        $player = MasterGamePlayer::updateOrCreate(
            [
                'master_game_id' => $game->id,
                'user_id' => $user->id,
            ],
            [
                'score' => 0,
                'answered' => [],
                'status' => 'joined'
            ]
        );
        
        Log::info('Player joined Master game', [
            'game_id' => $game->id,
            'game_code' => $gameCode,
            'user_id' => $user->id
        ]);
        
        // Add to Firestore if game session exists
        try {
            $this->firestoreService->addParticipant($game->id, $user->id, $user->name);
        } catch (\Exception $e) {
            Log::warning('Could not add player to Firestore', [
                'game_id' => $game->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
        
        return redirect()->route('master.lobby', $game->id)
            ->with('success', __('Vous avez rejoint la partie !'));
    }
    
    /**
     * Show the invite page for the Game Master to invite contacts
     */
    public function showInvite($gameId)
    {
        $game = MasterGame::findOrFail($gameId);
        
        // Verify that the current user is the host
        if ($game->host_user_id !== Auth::id()) {
            abort(403, __('Vous n\'êtes pas l\'hôte de cette partie'));
        }
        
        $user = Auth::user();
        
        // Get contacts using PlayerContactService
        $contactService = app(PlayerContactService::class);
        $contacts = $contactService->getContacts($user->id);
        
        // Get already invited players for this game
        $invitedUserIds = MasterGamePlayer::where('master_game_id', $gameId)
            ->pluck('user_id')
            ->toArray();
        
        return view('master.invite', compact('game', 'contacts', 'invitedUserIds'));
    }
    
    /**
     * Process invitations - store invited contacts
     * Security: Verify each contact_id belongs to the host's contacts
     */
    public function sendInvites(Request $request, $gameId)
    {
        $game = MasterGame::findOrFail($gameId);
        $hostId = Auth::id();
        
        // Verify that the current user is the host
        if ($game->host_user_id !== $hostId) {
            abort(403, __('Vous n\'êtes pas l\'hôte de cette partie'));
        }
        
        $validated = $request->validate([
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'integer|exists:users,id'
        ]);
        
        // Security: Get the host's actual contacts to verify each contact_id
        $contactService = app(PlayerContactService::class);
        $hostContacts = $contactService->getContacts($hostId);
        $validContactIds = $hostContacts->pluck('id')->toArray();
        
        $invitedCount = 0;
        
        foreach ($validated['contact_ids'] as $contactId) {
            // Security check: Verify this contact_id is actually a contact of the host
            if (!in_array($contactId, $validContactIds)) {
                Log::warning('Attempted to invite non-contact user', [
                    'host_id' => $hostId,
                    'game_id' => $gameId,
                    'contact_id' => $contactId
                ]);
                continue; // Skip invalid contacts
            }
            
            // Check if already registered
            $existing = MasterGamePlayer::where('master_game_id', $gameId)
                ->where('user_id', $contactId)
                ->first();
            
            if (!$existing) {
                // Create a pending invitation record
                MasterGamePlayer::create([
                    'master_game_id' => $gameId,
                    'user_id' => $contactId,
                    'status' => 'invited',
                    'score' => 0,
                    'answered' => []
                ]);
                $invitedCount++;
            }
        }
        
        // Quête quotidienne : inviter un joueur (une fois par action sendInvites si ≥1 invité)
        if ($invitedCount > 0) {
            try {
                $host = Auth::user();
                app(\App\Services\DailyQuestService::class)->checkAndCompleteDailyQuest(
                    $host, 'daily_invite_player', ['player_invited' => true]
                );
            } catch (\Throwable $e) {
                Log::warning('DailyQuest player_invited (Master) failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', __(':count joueurs ont été invités', ['count' => $invitedCount]));
    }
    
    // ===== SOCKET.IO GAMEPLAY METHODS (for up to 40 players) =====
    
    /**
     * Get MasterGame from session
     */
    protected function getGameFromSession(): ?MasterGame
    {
        $gameState = session('master_game_state');
        
        if (!$gameState || !isset($gameState['game_id'])) {
            return null;
        }
        
        return MasterGame::with(['questions', 'players.user', 'host'])->find($gameState['game_id']);
    }
    
    /**
     * POST /game/master/start - Starts game from lobby
     */
    public function startGameFromLobby(Request $request)
    {
        $user = Auth::user();
        $gameState = session('master_game_state');
        
        if (!$gameState || !isset($gameState['game_id'])) {
            return redirect()->route('master.index')->with('error', __('Aucune partie en cours'));
        }
        
        $game = MasterGame::find($gameState['game_id']);
        
        if (!$game) {
            session()->forget('master_game_state');
            return redirect()->route('master.index')->with('error', __('Partie introuvable'));
        }
        
        if ($game->host_user_id != $user->id) {
            return redirect()->route('master.index')->with('error', __('Seul l\'hôte peut démarrer la partie'));
        }
        
        $game->status = 'playing';
        $game->current_question = 1;
        $game->started_at = now();
        $game->save();
        
        if ($game->room_id) {
            $this->gameServerService->startGame($game->room_id, (string) $user->id);
        }
        
        session(['master_game_state' => array_merge($gameState, [
            'started' => true,
            'started_at' => now()->timestamp,
        ])]);
        
        return redirect()->route('game.master.question');
    }
    
    /**
     * GET /game/master/resume - Resume a game in progress
     */
    public function showResume()
    {
        $user = Auth::user();
        $game = $this->getGameFromSession();
        
        if (!$game) {
            return redirect()->route('master.index')->with('error', __('Aucune partie en cours'));
        }
        
        $isHost = $game->host_user_id == $user->id;
        $player = $game->players->where('user_id', $user->id)->first();
        
        if (!$isHost && !$player) {
            session()->forget('master_game_state');
            return redirect()->route('master.index')->with('error', __('Vous n\'appartenez pas à cette partie'));
        }
        
        return $this->renderQuestionView($game, $user, $isHost);
    }
    
    /**
     * GET /game/master/question - Shows current question to all players
     */
    public function showQuestionPage()
    {
        $user = Auth::user();
        $game = $this->getGameFromSession();
        
        if (!$game) {
            return redirect()->route('master.index')->with('error', __('Aucune partie en cours'));
        }
        
        $isHost = $game->host_user_id == $user->id;
        $player = $game->players->where('user_id', $user->id)->first();
        
        if (!$isHost && !$player) {
            session()->forget('master_game_state');
            return redirect()->route('master.index')->with('error', __('Vous n\'appartenez pas à cette partie'));
        }
        
        return $this->renderQuestionView($game, $user, $isHost);
    }
    
    /**
     * GET /game/master/answer - Shows answer selection
     */
    public function showAnswerPage()
    {
        $user = Auth::user();
        $game = $this->getGameFromSession();
        
        if (!$game) {
            return redirect()->route('master.index')->with('error', __('Aucune partie en cours'));
        }
        
        $isHost = $game->host_user_id == $user->id;
        $player = $game->players->where('user_id', $user->id)->first();
        
        if (!$isHost && !$player) {
            session()->forget('master_game_state');
            return redirect()->route('master.index')->with('error', __('Vous n\'appartenez pas à cette partie'));
        }
        
        $gameState = session('master_game_state', []);
        
        return $this->renderAnswerView($game, $user, $isHost, $gameState);
    }
    
    /**
     * GET /game/master/result - Shows question result and scores
     */
    public function showResultPage()
    {
        $user = Auth::user();
        $game = $this->getGameFromSession();
        
        if (!$game) {
            return redirect()->route('master.index')->with('error', __('Aucune partie en cours'));
        }
        
        $isHost = $game->host_user_id == $user->id;
        $player = $game->players->where('user_id', $user->id)->first();
        
        if (!$isHost && !$player) {
            session()->forget('master_game_state');
            return redirect()->route('master.index')->with('error', __('Vous n\'appartenez pas à cette partie'));
        }
        
        $gameState = session('master_game_state', []);
        
        return $this->renderResultView($game, $user, $isHost, $gameState);
    }
    
    /**
     * POST /game/master/fetch-question - Returns current question data as JSON
     */
    public function fetchQuestionJsonApi(Request $request)
    {
        $user = Auth::user();
        $game = $this->getGameFromSession();
        
        if (!$game) {
            return response()->json(['success' => false, 'error' => 'No active game'], 400);
        }
        
        $isHost = $game->host_user_id == $user->id;
        $player = $game->players->where('user_id', $user->id)->first();
        
        if (!$isHost && !$player) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }
        
        $currentQuestionNumber = $game->current_question ?? 1;
        $question = $game->questions->where('question_number', $currentQuestionNumber)->first();
        
        if (!$question) {
            return response()->json([
                'success' => false,
                'finished' => true,
                'message' => 'No more questions'
            ]);
        }
        
        $questionData = [
            'id' => $question->id,
            'number' => $question->question_number,
            'text' => $question->text,
            'type' => $question->type,
            'choices' => $question->choices,
            'media_url' => $question->media_url,
        ];
        
        if ($isHost) {
            $questionData['correct_indexes'] = $question->correct_indexes;
        }
        
        return response()->json([
            'success' => true,
            'question' => $questionData,
            'current_question' => $currentQuestionNumber,
            'total_questions' => ($game->total_questions * 3),
            'is_host' => $isHost,
        ]);
    }
    
    /**
     * GET /game/master/match-result - Shows final game results with rankings
     */
    public function showMatchResultPage()
    {
        $user = Auth::user();
        $game = $this->getGameFromSession();
        
        if (!$game) {
            return redirect()->route('master.index')->with('error', __('Aucune partie en cours'));
        }
        
        $isHost = $game->host_user_id == $user->id;
        $player = $game->players->where('user_id', $user->id)->first();
        
        if (!$isHost && !$player) {
            session()->forget('master_game_state');
            return redirect()->route('master.index')->with('error', __('Vous n\'appartenez pas à cette partie'));
        }
        
        $players = $game->players()
            ->with('user')
            ->orderBy('score', 'desc')
            ->get();
        
        $winner = $players->first();
        
        $gameState = session('master_game_state', []);
        
        session()->forget('master_game_state');
        
        return view('master.match-result', [
            'game' => $game,
            'players' => $players,
            'winner' => $winner,
            'is_host' => $isHost,
            'current_user' => $user,
            'game_state' => $gameState,
        ]);
    }
    
    /**
     * Render the question view for Master mode
     */
    protected function renderQuestionView(MasterGame $game, $user, bool $isHost)
    {
        $gameServerUrl = env('GAME_SERVER_URL', 'http://localhost:3001');
        $roomId = $game->room_id ?? null;
        
        $jwtToken = null;
        if ($roomId) {
            $jwtToken = $this->gameServerService->generatePlayerToken($user->id, $roomId);
        }
        
        $profileSettings = $user->profile_settings ?? [];
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true) ?? [];
        }
        
        $playerAvatarPath = data_get($profileSettings, 'avatar.url', 'images/avatars/standard/standard1.png');
        $playerName = data_get($profileSettings, 'pseudonym', $user->name ?? 'Joueur');
        
        $currentQuestion = $game->current_question ?? 1;
        $question = $game->questions->where('question_number', $currentQuestion)->first();
        
        $players = $game->players()
            ->with('user')
            ->orderBy('score', 'desc')
            ->get();
        
        $viewData = [
            'game' => $game,
            'game_id' => $game->id,
            'game_server_url' => $gameServerUrl,
            'room_id' => $roomId,
            'jwt_token' => $jwtToken,
            'is_host' => $isHost,
            'current_user' => $user,
            'player_name' => $playerName,
            'player_avatar' => $playerAvatarPath,
            'current_question' => $currentQuestion,
            'total_questions' => ($game->total_questions * 3),
            'question' => $question,
            'players' => $players,
            'game_mode' => $game->mode,
            'structure_type' => $game->structure_type,
        ];
        
        return response()->view('master.game-question', $viewData)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
    
    /**
     * Render the answer view for Master mode
     */
    protected function renderAnswerView(MasterGame $game, $user, bool $isHost, array $gameState)
    {
        $profileSettings = $user->profile_settings ?? [];
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true) ?? [];
        }
        
        $playerAvatarPath = data_get($profileSettings, 'avatar.url', 'images/avatars/standard/standard1.png');
        $playerName = data_get($profileSettings, 'pseudonym', $user->name ?? 'Joueur');
        
        $currentQuestion = $game->current_question ?? 1;
        $question = $game->questions->where('question_number', $currentQuestion)->first();
        
        $players = $game->players()
            ->with('user')
            ->orderBy('score', 'desc')
            ->get();
        
        $gameServerUrl = env('GAME_SERVER_URL', 'http://localhost:3001');
        $roomId = $game->room_id ?? null;
        
        $jwtToken = null;
        if ($roomId) {
            $jwtToken = $this->gameServerService->generatePlayerToken($user->id, $roomId);
        }
        
        // Historien skills data (for players, not host)
        $skills = !$isHost ? $this->getPlayerSkillsWithTriggers($user) : [];
        $strategicAvatar = data_get($profileSettings, 'strategic_avatar', 'Aucun');

        $viewData = [
            'game' => $game,
            'game_id' => $game->id,
            'game_server_url' => $gameServerUrl,
            'room_id' => $roomId,
            'jwt_token' => $jwtToken,
            'is_host' => $isHost,
            'current_user' => $user,
            'player_name' => $playerName,
            'player_avatar' => $playerAvatarPath,
            'current_question' => $currentQuestion,
            'total_questions' => ($game->total_questions * 3),
            'question' => $question,
            'players' => $players,
            'game_mode' => $game->mode,
            'structure_type' => $game->structure_type,
            'game_state' => $gameState,
            'skills' => $skills,
            'strategic_avatar' => $strategicAvatar,
        ];
        
        return response()->view('master.game-answer', $viewData)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
    
    /**
     * Render the result view for Master mode
     */
    protected function renderResultView(MasterGame $game, $user, bool $isHost, array $gameState)
    {
        $profileSettings = $user->profile_settings ?? [];
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true) ?? [];
        }
        
        $playerAvatarPath = data_get($profileSettings, 'avatar.url', 'images/avatars/standard/standard1.png');
        $playerName = data_get($profileSettings, 'pseudonym', $user->name ?? 'Joueur');
        
        $currentQuestion = $game->current_question ?? 1;
        $question = $game->questions->where('question_number', $currentQuestion)->first();
        
        $players = $game->players()
            ->with('user')
            ->orderBy('score', 'desc')
            ->get();
        
        $gameServerUrl = env('GAME_SERVER_URL', 'http://localhost:3001');
        $roomId = $game->room_id ?? null;
        
        $jwtToken = null;
        if ($roomId) {
            $jwtToken = $this->gameServerService->generatePlayerToken($user->id, $roomId);
        }
        
        // Historien skills data (for players, not host)
        $skills = !$isHost ? $this->getPlayerSkillsWithTriggers($user) : [];
        $strategicAvatar = data_get($profileSettings, 'strategic_avatar', 'Aucun');

        $viewData = [
            'game' => $game,
            'game_id' => $game->id,
            'game_server_url' => $gameServerUrl,
            'room_id' => $roomId,
            'jwt_token' => $jwtToken,
            'is_host' => $isHost,
            'current_user' => $user,
            'player_name' => $playerName,
            'player_avatar' => $playerAvatarPath,
            'current_question' => $currentQuestion,
            'total_questions' => ($game->total_questions * 3),
            'question' => $question,
            'players' => $players,
            'game_mode' => $game->mode,
            'structure_type' => $game->structure_type,
            'game_state' => $gameState,
            'skills' => $skills,
            'strategic_avatar' => $strategicAvatar,
        ];
        
        return response()->view('master.game-result', $viewData)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
    
    /**
     * Get player skills with triggers for Historian avatar
     */
    protected function getPlayerSkillsWithTriggers($user): array
    {
        $profileSettings = $user->profile_settings;
        if (is_string($profileSettings)) {
            $profileSettings = json_decode($profileSettings, true);
        }
        
        $avatarName = $profileSettings['strategic_avatar'] ?? 'Aucun';
        
        if ($avatarName === 'Aucun' || empty($avatarName)) {
            return [];
        }
        
        $catalog = \App\Services\AvatarCatalog::get();
        $strategicAvatars = $catalog['stratégiques']['items'] ?? [];
        $avatarInfo = null;
        
        foreach ($strategicAvatars as $avatar) {
            if (isset($avatar['name']) && $avatar['name'] === $avatarName) {
                $avatarInfo = $avatar;
                break;
            }
        }
        
        if (!$avatarInfo || empty($avatarInfo['skills'])) {
            return [];
        }
        
        $skills = [];
        foreach ($avatarInfo['skills'] as $skillId) {
            $skillInfo = \App\Services\SkillCatalog::getSkillById($skillId);
            if ($skillInfo) {
                $skills[] = [
                    'id' => $skillId,
                    'name' => $skillInfo['name'] ?? $skillId,
                    'description' => $skillInfo['description'] ?? '',
                    'icon' => $skillInfo['icon'] ?? '🔮',
                    'used' => false,
                ];
            }
        }
        
        return $skills;
    }
}
