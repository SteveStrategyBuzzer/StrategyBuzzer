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
