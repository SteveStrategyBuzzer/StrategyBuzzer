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
        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description');
            $table->enum('tier', ['bronze', 'silver', 'gold'])->default('bronze');
            $table->integer('reward_pieces')->default(10);
            $table->string('icon')->nullable();
            $table->string('category')->default('general');
            $table->json('requirements')->nullable();
            $table->json('unlocks')->nullable();
            $table->integer('target_value')->default(1);
            $table->boolean('repeatable')->default(false);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quests');
    }
};
