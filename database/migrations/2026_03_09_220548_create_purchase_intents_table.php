<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_intents', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->string('product_key');
            $table->string('product_type'); 
            $table->string('coin_type')->nullable();

            $table->integer('coins_to_deliver')->nullable();

            $table->integer('amount_cents');
            $table->string('currency', 3);

            $table->string('stripe_session_id')->nullable();

            $table->string('status')->default('created');
            $table->timestamp('fulfilled_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['stripe_session_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_intents');
    }
};
