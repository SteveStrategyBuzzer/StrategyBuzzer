<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('player_code', 10)->unique()->nullable()->after('email');
        });
        
        // Générer des codes pour les utilisateurs existants
        $users = \App\Models\User::whereNull('player_code')->get();
        foreach ($users as $user) {
            $user->player_code = $this->generateUniquePlayerCode();
            $user->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('player_code');
        });
    }
    
    /**
     * Générer un code joueur unique SB-XXXX
     */
    private function generateUniquePlayerCode(): string
    {
        do {
            $code = 'SB-' . strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4));
        } while (\App\Models\User::where('player_code', $code)->exists());
        
        return $code;
    }
};
