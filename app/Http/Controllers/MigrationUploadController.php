<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    public function upload(Request $request)
    {
        if ($request->header('X-Migration-Secret') !== self::UPLOAD_SECRET) {
            Log::warning('Migration upload: invalid secret attempt from ' . $request->ip());
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $name = $request->input('name');
        if (!in_array($name, self::ALLOWED_FILES, true)) {
            return response()->json(['error' => 'Invalid file name'], 422);
        }

        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file provided'], 422);
        }

        $file = $request->file('file');
        if ($file->getClientOriginalExtension() !== 'csv') {
            return response()->json(['error' => 'Only CSV files accepted'], 422);
        }

        $destination = base_path('database/migration_exports');
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $filename = $name . '.csv';
        $file->move($destination, $filename);

        $path = $destination . '/' . $filename;
        $lines = max(0, count(file($path)) - 1);

        Log::info("Migration upload received: {$filename} ({$lines} data rows)");

        return response()->json([
            'status'    => 'ok',
            'file'      => $filename,
            'data_rows' => $lines,
        ]);
    }

    public function status()
    {
        $destination = base_path('database/migration_exports');
        $files = [];

        foreach (self::ALLOWED_FILES as $name) {
            $path = $destination . '/' . $name . '.csv';
            $files[$name] = file_exists($path)
                ? ['exists' => true, 'rows' => max(0, count(file($path)) - 1), 'size' => filesize($path)]
                : ['exists' => false];
        }

        return response()->json(['files' => $files]);
    }
}
