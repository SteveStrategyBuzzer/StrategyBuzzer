<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_contacts', function (Blueprint $table) {
            $table->string('display_name_choice', 10)->default('name')->after('last_played_at');
            $table->string('display_id_choice', 10)->default('code')->after('display_name_choice');
        });
    }

    public function down(): void
    {
        Schema::table('player_contacts', function (Blueprint $table) {
            $table->dropColumn(['display_name_choice', 'display_id_choice']);
        });
    }
};
