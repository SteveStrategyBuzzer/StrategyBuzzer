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
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();

            // Stripe event ID (idempotence clé absolue)
            $table->string('event_id')->unique();

            // Type d'événement (ex: checkout.session.completed)
            $table->string('type');

            // Session Stripe associée
            $table->string('stripe_session_id')->nullable()->index();

            // Payload brut pour audit (optionnel mais recommandé)
            $table->json('payload')->nullable();

            // Date réelle de traitement
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
