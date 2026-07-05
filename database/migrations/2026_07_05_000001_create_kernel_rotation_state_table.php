<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_rotation_state', function (Blueprint $table) {
            $table->id();
            $table->integer('current_depth');
            $table->integer('current_domain_index')->default(0);
            $table->integer('completed_domains')->default(0);
            $table->string('last_rotation_identifier')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_rotation_state');
    }
};
