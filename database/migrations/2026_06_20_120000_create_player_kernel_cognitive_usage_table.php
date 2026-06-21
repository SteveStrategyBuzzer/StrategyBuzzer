<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_kernel_cognitive_usage', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id');

            // Identité métier/pédagogique LISIBLE du noyau (source de vérité)
            // ex. "D04-GEO-MON-EVE-MOR-00"
            $table->string('kernel_code', 128);

            // Référence technique vers question_intents.id — OPTIONNELLE, SANS FK.
            // L'historique survit si le noyau est modifié/nettoyé/archivé.
            $table->unsignedBigInteger('question_intent_id')->nullable();

            $table->unsignedTinyInteger('depth');          // 1..10
            $table->string('domain', 64);

            // recognition | reasoning | deceptive_trap (lève l'ambiguïté de cognitive_form)
            $table->string('cognitive_family', 24);
            // qcm | tf_true | tf_false | trap
            $table->string('cognitive_form', 16);

            // Colonne INFORMATIVE : match/session de la PREMIÈRE consommation (pas une clé d'unicité)
            $table->string('match_ref', 128);
            // solo | duo | league_individual | league_team | master
            $table->string('mode', 32);

            // Ligne créée SEULEMENT après exposition complète (question + bonne réponse + SV)
            $table->timestamp('consumed_at')->useCurrent();

            // Seule FK : convention player_question_history
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();

            // Mémoire gameplay durable : 1 ligne par joueur / noyau / famille / forme cognitive
            // (indépendant du match → pas de match_ref ici)
            $table->unique(
                ['user_id', 'kernel_code', 'cognitive_family', 'cognitive_form'],
                'pkcu_unique'
            );

            // État vierge/touché d'un noyau pour CE joueur
            $table->index(['user_id', 'kernel_code'], 'pkcu_user_kernel_idx');
            // Chargement MAP par depth+domain du roster
            $table->index(['user_id', 'depth', 'domain', 'kernel_code'], 'pkcu_user_depth_domain_kernel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_kernel_cognitive_usage');
    }
};
