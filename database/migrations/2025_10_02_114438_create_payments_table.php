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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_session_id')->unique()->nullable();
            $table->string('stripe_payment_intent_id')->unique()->nullable();
            $table->string('product_key');
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('usd');
            $table->integer('coins_awarded')->default(0);
            $table->string('status')->default('pending'); // pending, completed, failed, refunded
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
