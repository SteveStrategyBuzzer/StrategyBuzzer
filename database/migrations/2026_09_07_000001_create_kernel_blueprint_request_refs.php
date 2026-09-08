<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_blueprint_request_refs', function (Blueprint $table) {
            $table->string('request_reference', 128)->primary();
            $table->string('blueprint_id', 36);

            $table->foreign('blueprint_id')
                ->references('blueprint_id')
                ->on('kernel_blueprint_runs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_blueprint_request_refs');
    }
};