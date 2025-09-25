<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class PlayController extends BaseController
{
    // Mini banque de questions (MVP)
    private array $questions = [
        [
            'id' => 1,
            'text' => "Quelle est la capitale du Canada ?",
            'choices' => ["Toronto", "Montréal", "Ottawa", "Vancouver"],
            // index (0-based) de la bonne réponse
            'answer' => 2,
        ],
        [
            'id' => 2,
            'text' => "2 + 2 = ?",
            'choices' => ["3", "4", "5", "22"],
            'answer' => 1,
        ],
    ];

    // GET /api/solo/next : question aléatoire (sans donner la solution)
    public function next(Request $request)
    {
        $q = $this->questions[array_rand($this->questions)];

        return response()->json([
            'ok' => true,
            'question' => [
                'id'      => $q['id'],
                'text'    => $q['text'],
                'choices' => $q['choices'],
            ],
        ]);
    }

    // POST /api/solo/answer : vérifie la réponse
    public function answer(Request $request)
    {
        $id     = (int) $request->input('id');
        $choice = (int) $request->input('choice');

        $q = collect($this->questions)->firstWhere('id', $id);
        if (!$q) {
            return response()->json(['ok' => false, 'error' => 'Question inconnue'], 404);
        }

        $correct = ($choice === (int) $q['answer']);

        return response()->json([
            'ok'            => true,
            'correct'       => $correct,
            'explanation'   => $correct ? 'Bravo !' : 'Mauvaise réponse.',
            'correct_index' => (int) $q['answer'],
        ]);
    }
}
