<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_intents', function (Blueprint $table) {
            // Clé sémantique (remplace progressivement intent_key legacy)
            $table->string('semantic_key', 255)->nullable()->after('intent_key');

            // Tracking dialyse
            $table->string('dialysis_status', 32)->notNull()->default('pending')->after('semantic_key');
            $table->timestamp('dialysed_at')->nullable()->after('dialysis_status');
            $table->timestamp('locked_at')->nullable()->after('dialysed_at');
            $table->string('locked_by', 64)->nullable()->after('locked_at');

            // Variantes
            $table->jsonb('variantes_present')->nullable()->after('locked_by');
            $table->jsonb('variantes_missing')->nullable()->after('variantes_present');
            $table->smallInteger('variantes_count')->notNull()->default(0)->after('variantes_missing');

            // Notes structurées (pas de dump libre)
            $table->string('dialysis_summary', 500)->nullable()->after('variantes_count');
            $table->string('dialysis_last_issue', 255)->nullable()->after('dialysis_summary');
            $table->smallInteger('dialysis_action_count')->notNull()->default(0)->after('dialysis_last_issue');
        });

        // Contrainte CHECK sur dialysis_status
        DB::statement("ALTER TABLE question_intents
            ADD CONSTRAINT qi_dialysis_status_check
            CHECK (dialysis_status IN ('pending','in_progress','complete','blocked'))");

        // Index
        DB::statement('CREATE INDEX qi_dialysis_status_idx ON question_intents (dialysis_status)');
        DB::statement('CREATE UNIQUE INDEX qi_semantic_key_idx ON question_intents (semantic_key) WHERE semantic_key IS NOT NULL');

        // Initialiser dialysis_status = 'pending' sur toutes les lignes existantes
        DB::statement("UPDATE question_intents SET dialysis_status = 'pending' WHERE dialysis_status IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS qi_semantic_key_idx');
        DB::statement('DROP INDEX IF EXISTS qi_dialysis_status_idx');
        DB::statement('ALTER TABLE question_intents DROP CONSTRAINT IF EXISTS qi_dialysis_status_check');

        Schema::table('question_intents', function (Blueprint $table) {
            $table->dropColumn([
                'semantic_key',
                'dialysis_status',
                'dialysed_at',
                'locked_at',
                'locked_by',
                'variantes_present',
                'variantes_missing',
                'variantes_count',
                'dialysis_summary',
                'dialysis_last_issue',
                'dialysis_action_count',
            ]);
        });
    }
};
