<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_privilege_audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('action', 32);
            $table->unsignedBigInteger('target_user_id')->index();
            $table->string('origin', 32);
            $table->timestamp('created_at')->useCurrent();
            $table->json('metadata')->nullable();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_privilege_audit_logs');
    }
};