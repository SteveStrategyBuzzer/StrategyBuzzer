<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kernel_blueprint_request_refs', function (Blueprint $table) {
            $table->string('request_reference', 128);
            $table->string('blueprint_id', 36);

            $table->primary(
                'request_reference',
                'kernel_blueprint_request_refs_pkey'
            );

            $table->foreign(
                'blueprint_id',
                'kernel_blueprint_request_refs_blueprint_id_foreign'
            )
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