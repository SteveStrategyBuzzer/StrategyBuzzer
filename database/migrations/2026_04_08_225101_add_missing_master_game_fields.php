<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_games', function (Blueprint $table) {

            if (!Schema::hasColumn('master_games', 'buzzer_sound_id')) {
                $table->string('buzzer_sound_id')->nullable();
            }

            if (!Schema::hasColumn('master_games', 'buzzer_sound_choice')) {
                $table->string('buzzer_sound_choice')->nullable();
            }

            if (!Schema::hasColumn('master_games', 'buzzer_sound_enabled')) {
                $table->boolean('buzzer_sound_enabled')->default(true);
            }

            if (!Schema::hasColumn('master_games', 'background_music_enabled')) {
                $table->boolean('background_music_enabled')->default(false);
            }

            if (!Schema::hasColumn('master_games', 'ambiance_music_id')) {
                $table->string('ambiance_music_id')->nullable();
            }

            if (!Schema::hasColumn('master_games', 'ambiance_music_choice')) {
                $table->string('ambiance_music_choice')->nullable();
            }

            if (!Schema::hasColumn('master_games', 'gameplay_ambiance_enabled')) {
                $table->boolean('gameplay_ambiance_enabled')->default(false);
            }

            if (!Schema::hasColumn('master_games', 'strategic_avatars_enabled')) {
                $table->boolean('strategic_avatars_enabled')->default(false);
            }

            if (!Schema::hasColumn('master_games', 'strategic_avatars_tiers')) {
                $table->json('strategic_avatars_tiers')->nullable();
            }

        });
    }

    public function down(): void
    {
        Schema::table('master_games', function (Blueprint $table) {

            if (Schema::hasColumn('master_games', 'buzzer_sound_id')) {
                $table->dropColumn('buzzer_sound_id');
            }

            if (Schema::hasColumn('master_games', 'buzzer_sound_choice')) {
                $table->dropColumn('buzzer_sound_choice');
            }

            if (Schema::hasColumn('master_games', 'buzzer_sound_enabled')) {
                $table->dropColumn('buzzer_sound_enabled');
            }

            if (Schema::hasColumn('master_games', 'background_music_enabled')) {
                $table->dropColumn('background_music_enabled');
            }

            if (Schema::hasColumn('master_games', 'ambiance_music_id')) {
                $table->dropColumn('ambiance_music_id');
            }

            if (Schema::hasColumn('master_games', 'ambiance_music_choice')) {
                $table->dropColumn('ambiance_music_choice');
            }

            if (Schema::hasColumn('master_games', 'gameplay_ambiance_enabled')) {
                $table->dropColumn('gameplay_ambiance_enabled');
            }

            if (Schema::hasColumn('master_games', 'strategic_avatars_enabled')) {
                $table->dropColumn('strategic_avatars_enabled');
            }

            if (Schema::hasColumn('master_games', 'strategic_avatars_tiers')) {
                $table->dropColumn('strategic_avatars_tiers');
            }

        });
    }
};
