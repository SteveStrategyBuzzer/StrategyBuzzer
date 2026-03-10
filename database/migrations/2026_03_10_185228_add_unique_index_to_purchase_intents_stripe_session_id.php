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
        Schema::table('purchase_intents', function (Blueprint $table) {
            $table->dropIndex(['stripe_session_id']);
            $table->unique('stripe_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_intents', function (Blueprint $table) {
            $table->dropUnique(['stripe_session_id']);
            $table->index('stripe_session_id');
        });
    }
};
