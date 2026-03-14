<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── ÉTAPE 1 : supprimer les 7 vrais doublons ────────────────────
        $duplicateIds = [98, 106, 144, 151, 156, 159, 164];
        DB::table('user_quest_progress')->whereIn('quest_id', $duplicateIds)->delete();
        DB::table('quests')->whereIn('id', $duplicateIds)->delete();

        // ── ÉTAPE 2 : activer auto_complete pour les quêtes câblables ───
        $wirableIds = [
            96, 97, 99, 100, 101, 102, 107, 109, 110, 111, 112, 113,
            140, 142, 143, 145, 146, 147, 148, 149, 150, 152, 153,
            155, 157, 158, 160, 161, 163, 165, 166, 168, 169, 171,
            173, 174, 176, 179, 181, 182, 183,
        ];
        DB::table('quests')->whereIn('id', $wirableIds)->update(['auto_complete' => true]);
    }

    public function down(): void
    {
        $wirableIds = [
            96, 97, 99, 100, 101, 102, 107, 109, 110, 111, 112, 113,
            140, 142, 143, 145, 146, 147, 148, 149, 150, 152, 153,
            155, 157, 158, 160, 161, 163, 165, 166, 168, 169, 171,
            173, 174, 176, 179, 181, 182, 183,
        ];
        DB::table('quests')->whereIn('id', $wirableIds)->update(['auto_complete' => false]);
        // Note : les doublons supprimés ne sont pas recréés dans le down() car ils
        // sont gérés par les seeders d'origine si nécessaire.
    }
};
