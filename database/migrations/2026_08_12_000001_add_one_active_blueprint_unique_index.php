<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1 — Garantie atomique : au plus un Blueprint actif à la fois.
 *
 * Stratégie : index unique partiel PostgreSQL sur l'expression constante (1).
 * Seules les lignes dont execution_state est dans l'ensemble des états actifs
 * participent à l'index. L'expression indexée étant une constante, l'index
 * ne peut contenir qu'une seule entrée → au plus une ligne active en DB.
 *
 * Cette contrainte est atomique par nature : l'INSERT échoue avec une
 * UniqueConstraintViolationException si une ligne active existe déjà,
 * indépendamment du nombre de connexions concurrentes.
 *
 * SQLite (utilisé en tests PHPUnit) ne supporte pas les index partiels.
 * La migration est un NO-OP sur SQLite ; la protection sequentielle reste
 * assurée par la vérification applicative dans KernelBlueprintFactory.
 * Les tests de concurrence PostgreSQL sont dans kernel_blueprint_factory_concurrent.php.
 *
 * Additive — aucune colonne ni table existante modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS one_active_blueprint_idx
             ON kernel_blueprint_runs ((1))
             WHERE execution_state IN ('CREATED_UNENGAGED', 'ENGAGED_IN_PIPELINE')"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS one_active_blueprint_idx');
    }
};
