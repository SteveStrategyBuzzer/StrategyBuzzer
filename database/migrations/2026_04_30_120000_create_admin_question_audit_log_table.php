<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #94 — Audit trail for admin AI composition calls.
 *
 * Every short-lived JWT minted by App\Services\QuestionApi\QuestionApiClient
 * to call /generate-master-question or /generate-image-question writes one
 * row here so we know who triggered which provider call, with what payload,
 * and how the question-api responded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_question_audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('jti', 64)->unique();
            $table->unsignedBigInteger('caller_user_id')->nullable()->index();
            $table->string('endpoint', 64)->index();
            $table->char('payload_hash', 64);
            $table->string('source', 64)->nullable();
            $table->boolean('accepted')->default(false);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('responded_at')->nullable();

            $table->index('created_at', 'aqal_created_at_idx');
            $table->index(['endpoint', 'created_at'], 'aqal_endpoint_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_question_audit_log');
    }
};
