<?php

namespace App\Http\Controllers;

use App\Models\MasterGame;
use App\Models\MasterGameCode;
use App\Models\MasterGameQuestion;
use App\Models\MasterGamePlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use OpenAI\Laravel\Facades\OpenAI;

class MasterGameController extends Controller
{
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
            'languages' => 'required|array',
            'participants_expected' => 'required|integer|min:3|max:40',
            'mode' => 'required|in:face_to_face,one_vs_all,podium,groups',
            'total_questions' => 'required|in:10,20,30,40',
            'question_types' => 'required|array',
            'domain_type' => 'required|in:theme,scolaire',
            'theme' => 'nullable|string',
            'school_country' => 'nullable|string',
            'school_level' => 'nullable|string',
            'school_subject' => 'nullable|string',
            'creation_mode' => 'required|in:automatique,personnalise'
        ]);

        // Générer un code unique
        $gameCode = $this->generateUniqueGameCode();

        $game = MasterGame::create([
            'game_code' => $gameCode,
            'host_user_id' => Auth::id(),
            'name' => $validated['name'],
            'languages' => $validated['languages'],
            'participants_expected' => $validated['participants_expected'],
            'mode' => $validated['mode'],
            'total_questions' => $validated['total_questions'],
            'question_types' => $validated['question_types'],
            'domain_type' => $validated['domain_type'],
            'theme' => $validated['theme'] ?? null,
            'school_country' => $validated['school_country'] ?? null,
            'school_level' => $validated['school_level'] ?? null,
            'school_subject' => $validated['school_subject'] ?? null,
            'creation_mode' => $validated['creation_mode'],
            'status' => 'draft'
        ]);

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
    public function compose($gameId)
    {
        $game = MasterGame::with('questions')->findOrFail($gameId);
        
        // Vérifier que c'est bien l'hôte
        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }

        return view('master.compose', compact('game'));
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

        // Créer ou mettre à jour la question
        MasterGameQuestion::updateOrCreate(
            [
                'master_game_id' => $gameId,
                'question_number' => $questionNumber,
            ],
            [
                'question_text' => $validated['question_text'],
                'question_image' => $imagePath,
                'answers' => $validated['answers'],
                'correct_answer' => $validated['correct_answer'],
            ]
        );

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

        // Récupérer les questions déjà créées pour éviter les doublons
        $existingQuestions = MasterGameQuestion::where('master_game_id', $gameId)
            ->where('question_number', '!=', $questionNumber)
            ->get()
            ->pluck('question_text')
            ->filter()
            ->toArray();

        // Générer des sous-thèmes variés pour forcer la diversité
        $subTheme = $this->generateSubTheme($game, $questionNumber, $existingQuestions);

        // Construire le prompt basé sur le type de question
        $isImageQuestion = in_array('image', $game->question_types);
        $questionType = $game->question_types[0] ?? 'multiple_choice';
        
        $prompt = $this->buildQuestionPrompt($game, $questionType, $isImageQuestion, $existingQuestions, $questionNumber, $subTheme);

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un expert en création de quiz éducatifs. Tu crées des questions pertinentes, variées et UNIQUES avec des réponses plausibles. Chaque question doit être différente des autres.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ],
                ],
                'max_tokens' => 500,
                'temperature' => 0.9,
            ]);

            $content = $response->choices[0]->message->content;
            
            // Parser la réponse JSON de l'IA
            $data = json_decode($content, true);
            
            if (!$data || !isset($data['answers'])) {
                throw new \Exception('Format de réponse invalide');
            }

            return response()->json($data);
            
        } catch (\Exception $e) {
            // En cas d'erreur, retourner des données par défaut
            return response()->json([
                'question_text' => 'Question générée automatiquement',
                'answers' => [
                    'Réponse A',
                    'Réponse B', 
                    'Réponse C',
                    'Réponse D',
                ],
                'correct_answer' => 0,
                'error' => $e->getMessage()
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
        
        // Instruction de sous-thème pour forcer la variété
        $subThemeInstruction = "";
        if ($subTheme) {
            $subThemeInstruction = "\n🎯 SOUS-THÈME IMPOSÉ: {$subTheme}\n";
            $subThemeInstruction .= "⚠️ OBLIGATION: Ta question DOIT porter UNIQUEMENT sur ce sous-thème spécifique.\n";
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
    
    // Générer un sous-thème varié pour forcer la diversité
    private function generateSubTheme($game, $questionNumber, $existingQuestions)
    {
        $theme = $game->theme ?? $game->school_subject ?? 'culture générale';
        
        // Listes de sous-thèmes par thème principal
        $subThemes = [
            'géographie' => [
                'capitales de pays', 'fleuves et rivières', 'montagnes et sommets', 'océans et mers',
                'déserts et climats', 'îles et archipels', 'villes importantes', 'frontières et pays limitrophes',
                'population et démographie', 'langues parlées', 'drapeaux nationaux', 'monuments célèbres',
                'régions et départements', 'volcans', 'lacs', 'forêts', 'parcs naturels', 'continents'
            ],
            'histoire' => [
                'dates importantes', 'personnages historiques', 'guerres et conflits', 'révolutions',
                'découvertes scientifiques', 'dynasties et rois', 'inventions', 'traités et accords',
                'civilisations anciennes', 'batailles célèbres', 'mouvements sociaux', 'empire et colonies',
                'art et architecture', 'religion et croyances', 'économie historique'
            ],
            'science' => [
                'biologie et animaux', 'physique et forces', 'chimie et éléments', 'astronomie et espace',
                'médecine et santé', 'technologie', 'mathématiques', 'environnement et écologie',
                'météorologie', 'géologie', 'génétique', 'évolution', 'corps humain', 'plantes'
            ],
            'culture générale' => [
                'cinéma et films', 'musique et artistes', 'littérature et écrivains', 'sports et athlètes',
                'gastronomie', 'art et peinture', 'télévision', 'mode et tendances',
                'célébrités', 'jeux vidéo', 'traditions', 'fêtes', 'langues', 'religion'
            ],
            'sport' => [
                'football', 'basketball', 'tennis', 'rugby', 'athlétisme', 'natation',
                'cyclisme', 'jeux olympiques', 'records sportifs', 'équipes célèbres',
                'stades et infrastructures', 'compétitions internationales', 'sports d\'hiver'
            ],
            'culture' => [
                'peinture et tableaux', 'sculpteurs', 'architectes', 'musées', 'opéra et théâtre',
                'danse', 'cinéma mondial', 'festivals', 'prix et récompenses', 'mouvements artistiques'
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
                "évolution de {$theme}", "impact social de {$theme}"
            ];
        }
        
        // Sélectionner un sous-thème basé sur le numéro de question (rotation)
        $index = ($questionNumber - 1) % count($availableSubThemes);
        return $availableSubThemes[$index];
    }

    // Page 5: Générer les codes
    public function codes($gameId)
    {
        $game = MasterGame::findOrFail($gameId);
        
        // Vérifier que c'est bien l'hôte
        if ($game->host_user_id !== Auth::id()) {
            abort(403, 'Vous n\'êtes pas l\'hôte de cette partie');
        }

        return view('master.codes', compact('game'));
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
}
