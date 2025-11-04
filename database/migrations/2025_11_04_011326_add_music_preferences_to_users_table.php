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
            // Musique d'ambiance (navigation)
            $table->string('ambient_music_id')->default('strategybuzzer')->after('profile_settings');
            $table->boolean('ambient_music_enabled')->default(true)->after('ambient_music_id');
            
            // Musique de gameplay
            $table->string('gameplay_music_id')->nullable()->after('ambient_music_enabled');
            $table->boolean('gameplay_music_enabled')->default(true)->after('gameplay_music_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ambient_music_id', 'ambient_music_enabled', 'gameplay_music_id', 'gameplay_music_enabled']);
        });
    }
};
