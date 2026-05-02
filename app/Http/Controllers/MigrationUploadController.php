<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * TEMPORAIRE — Migration VM→Neon
 * À supprimer intégralement après import via scripts/migration_cleanup.sh
 */
class MigrationUploadController extends Controller
{
    private const UPLOAD_SECRET = '81528f0c3498cc2cb5ad04146e2d5028700b7269a635b7fa8a890beb38d43d73';

    private const ALLOWED_FILES = [
        'export_users',
        'export_quests',
        'export_coin_ledger',
        'export_payments',
        'export_player_statistics',
        'export_player_duo_stats',
        'export_match_performances',
        'export_duo_matches',
        'export_user_avatars',
        'export_user_quest_progress',
        'export_player_contacts',
        'export_player_messages',
    ];

    // Noms acceptés sans préfixe export_ (normalisés automatiquement)
    private const NAME_ALIASES = [
        'users', 'quests', 'coin_ledger', 'payments',
        'player_statistics', 'player_duo_stats', 'match_performances',
        'duo_matches', 'user_avatars', 'user_quest_progress',
        'player_contacts', 'player_messages',
    ];

    /**
     * Vérifie le secret dans chaque requête entrante.
     */
    private function checkSecret(Request $request): bool
    {
        return $request->header('X-Migration-Secret') === self::UPLOAD_SECRET;
    }

    /**
     * Reçoit un fichier CSV et le stocke HORS de public/.
     * Destination : <project_root>/database/migration_exports/
     * (non accessible via HTTP — le web root est public/)
     */
    public function upload(Request $request)
    {
        if (!$this->checkSecret($request)) {
            Log::warning('Migration upload: tentative non autorisée depuis ' . $request->ip());
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $name = $request->input('name');
        // Normalise : accepte "users" ou "export_users"
        if (in_array($name, self::NAME_ALIASES, true)) {
            $name = 'export_' . $name;
        }
        if (!in_array($name, self::ALLOWED_FILES, true)) {
            return response()->json(['error' => 'Invalid file name: ' . $name], 422);
        }

        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file provided'], 422);
        }

        $file = $request->file('file');
        if ($file->getClientOriginalExtension() !== 'csv') {
            return response()->json(['error' => 'Only CSV files accepted'], 422);
        }

        // Stockage dans database/migration_exports/ — hors public/, non exposé web
        $destination = base_path('database/migration_exports');
        if (!is_dir($destination)) {
            mkdir($destination, 0700, true);
        }

        $filename = $name . '.csv';
        $file->move($destination, $filename);

        $path    = $destination . '/' . $filename;
        $lines   = max(0, count(file($path)) - 1);

        Log::info("Migration upload reçu : {$filename} ({$lines} lignes de données)");

        return response()->json([
            'status'    => 'ok',
            'file'      => $filename,
            'data_rows' => $lines,
        ]);
    }

    /**
     * Retourne l'état des fichiers uploadés.
     * Protégé par le même secret X-Migration-Secret.
     */
    public function status(Request $request)
    {
        if (!$this->checkSecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $destination = base_path('database/migration_exports');
        $files       = [];

        foreach (self::ALLOWED_FILES as $name) {
            $path          = $destination . '/' . $name . '.csv';
            $files[$name]  = file_exists($path)
                ? ['exists' => true, 'rows' => max(0, count(file($path)) - 1), 'size' => filesize($path)]
                : ['exists' => false];
        }

        return response()->json(['files' => $files]);
    }
}
