<?php

namespace App\Http\Controllers;

use App\Models\MasterGame;
use App\Models\MasterGameCode;
use App\Models\MasterGameQuestion;
use App\Models\MasterGamePlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

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

        // TODO: Intégrer OpenAI pour générer la question
        // Pour l'instant, retourner des données de test
        
        return response()->json([
            'question_text' => 'Question générée par IA',
            'answers' => [
                'Réponse 1 générée',
                'Réponse 2 générée',
                'Réponse 3 générée',
                'Réponse 4 générée',
            ],
            'correct_answer' => 0,
        ]);
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
