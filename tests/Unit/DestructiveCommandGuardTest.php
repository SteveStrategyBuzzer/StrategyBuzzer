<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Console\Events\CommandStarting;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Vérifie le garde-fou ABSOLU anti-effacement (AppServiceProvider::boot()).
 *
 * Tous ces tests tournent sur sqlite in-memory et ne se connectent JAMAIS
 * à Neon : le garde-fou lève l'exception en lisant uniquement la config,
 * avant toute connexion DB.
 */
class DestructiveCommandGuardTest extends TestCase
{
    private function dispatchCommand(string $command, ?string $database = null): void
    {
        $definition = new InputDefinition([
            new InputOption('database', null, InputOption::VALUE_OPTIONAL),
        ]);
        $input = new ArrayInput($database ? ['--database' => $database] : [], $definition);

        event(new CommandStarting($command, $input, new NullOutput()));
    }

    public function test_bloque_migrate_fresh_sur_pgsql(): void
    {
        config(['database.default' => 'pgsql']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/BLOQU/u');
        $this->dispatchCommand('migrate:fresh');
    }

    public function test_bloque_db_wipe_sur_pgsql(): void
    {
        config(['database.default' => 'pgsql']);
        $this->expectException(\RuntimeException::class);
        $this->dispatchCommand('db:wipe');
    }

    public function test_bloque_migrate_rollback_sur_pgsql(): void
    {
        // rollback exécute les down() (drop table/colonne) : destructeur.
        config(['database.default' => 'pgsql']);
        $this->expectException(\RuntimeException::class);
        $this->dispatchCommand('migrate:rollback');
    }

    public function test_autorise_migrate_rollback_sur_sqlite(): void
    {
        config(['database.default' => 'sqlite']);
        $this->dispatchCommand('migrate:rollback');
        $this->assertTrue(true, 'rollback autorisé sur sqlite (sandbox)');
    }

    public function test_bloque_migrate_refresh_sur_pgsql(): void
    {
        config(['database.default' => 'pgsql']);
        $this->expectException(\RuntimeException::class);
        $this->dispatchCommand('migrate:refresh');
    }

    public function test_bloque_meme_avec_option_database_pgsql(): void
    {
        // Simule --database=pgsql explicite : doit rester bloqué (aucun bypass).
        config(['database.default' => 'sqlite']);
        $this->expectException(\RuntimeException::class);
        $this->dispatchCommand('migrate:fresh', 'pgsql');
    }

    public function test_autorise_migrate_fresh_sur_sqlite(): void
    {
        config(['database.default' => 'sqlite']);
        $this->dispatchCommand('migrate:fresh');
        $this->assertTrue(true, 'sqlite ne doit pas être bloqué');
    }

    public function test_autorise_commande_non_destructive_sur_pgsql(): void
    {
        config(['database.default' => 'pgsql']);
        $this->dispatchCommand('migrate');
        $this->dispatchCommand('migrate:status');
        $this->assertTrue(true, 'les commandes non destructrices restent autorisées');
    }

    public function test_environnement_de_test_reste_sqlite_pas_neon(): void
    {
        // Prouve le SLICE 1 (DATABASE_URL neutralisé) + SLICE 2 (url retirée du sqlite).
        $this->assertSame('sqlite', \DB::connection()->getDriverName());
        $this->assertNull(config('database.connections.sqlite.url'));
    }
}
