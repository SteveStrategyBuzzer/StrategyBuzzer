<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_qualification_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('event_type', 32);
            $table->unsignedInteger('reference_id');
            $table->timestamp('counted_at')->useCurrent();

            $table->unique(['user_id', 'event_type', 'reference_id'], 'bqe_user_event_ref_unique');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_qualification_events');
    }
};
