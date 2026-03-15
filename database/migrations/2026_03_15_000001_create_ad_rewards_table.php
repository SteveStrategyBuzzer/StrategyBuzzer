<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('coin_type', 20)->default('competence');
            $table->unsignedSmallInteger('coin_amount')->default(10);
            $table->timestamp('rewarded_at')->useCurrent();
            $table->ipAddress('ip_address')->nullable();

            $table->index(['user_id', 'rewarded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_rewards');
    }
};
