<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #182 — retire uniquement la reconstruction legacy vide de taxonomy_progress.
 */
return new class extends Migration
{
    private const LEGACY_COLUMNS = [
        'id',
        'depth',
        'domain_code',
        'active_sub_domain',
        'active_subject',
        'dominant_idea_index',
        'used_sub_domains',
        'status',
        'created_at',
        'updated_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('taxonomy_progress')) {
            return;
        }

        DB::transaction(function (): void {
            DB::statement('LOCK TABLE taxonomy_progress IN ACCESS EXCLUSIVE MODE');

            if (DB::table('taxonomy_progress')->count() !== 0) {
                throw new RuntimeException(
                    'Réconciliation #182 interrompue : taxonomy_progress contient des données.'
                );
            }

            $actual = Schema::getColumnListing('taxonomy_progress');
            sort($actual);
            $expected = self::LEGACY_COLUMNS;
            sort($expected);

            if ($actual !== $expected) {
                throw new RuntimeException(
                    'Réconciliation #182 interrompue : signature taxonomy_progress inattendue.'
                );
            }

            Schema::drop('taxonomy_progress');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('taxonomy_progress')) {
            return;
        }

        Schema::create('taxonomy_progress', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('depth');
            $table->string('domain_code', 32);
            $table->string('active_sub_domain', 128)->nullable();
            $table->string('active_subject', 128)->nullable();
            $table->unsignedTinyInteger('dominant_idea_index')->default(0);
            $table->json('used_sub_domains')->default('[]');
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['depth', 'domain_code'], 'tp_depth_domain_unique');
            $table->index(['depth', 'domain_code', 'status'], 'tp_depth_domain_status_idx');
        });
    }
};