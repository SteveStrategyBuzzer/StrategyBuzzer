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
        Schema::create('coin_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('delta'); // +100, -300, etc.
            $table->string('reason'); // purchase, refund, admin_adjustment, pack_unlock, etc.
            $table->string('ref_type')->nullable(); // Payment, Purchase, etc.
            $table->unsignedBigInteger('ref_id')->nullable(); // ID de la référence
            $table->integer('balance_after');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index('ref_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin_ledger');
    }
};
