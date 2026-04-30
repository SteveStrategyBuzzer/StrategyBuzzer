<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Belt-and-suspenders safety guard: refuse to start ANY test if the
     * active default DB connection is not the in-memory sqlite sandbox.
     *
     * Background: phpunit.xml forces DB_CONNECTION=sqlite + DB_DATABASE=:memory:
     * via <env> blocks, but this only takes effect if config/database.php
     * actually reads env('DB_CONNECTION'). A hard-coded default (e.g. the
     * historical 'default' => 'pgsql' on line 19) silently bypasses that
     * override and lets RefreshDatabase / migrate:fresh wipe the live
     * Postgres database — which is exactly how the dev DB got nuked.
     *
     * This guard hard-fails the suite at setUp() if the safety net is not
     * in effect, so the regression can never silently destroy data again.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $defaultConnection = config('database.default');
        if ($defaultConnection !== 'sqlite') {
            throw new \RuntimeException(
                "TEST SAFETY GUARD TRIPPED: default DB connection is '{$defaultConnection}', expected 'sqlite'. " .
                "Tests must NEVER touch the live database (RefreshDatabase would wipe it). " .
                "Verify phpunit.xml DB_CONNECTION env override AND config/database.php 'default' honors env('DB_CONNECTION')."
            );
        }

        $sqliteDatabase = config('database.connections.sqlite.database');
        if ($sqliteDatabase !== ':memory:') {
            throw new \RuntimeException(
                "TEST SAFETY GUARD TRIPPED: sqlite database is '{$sqliteDatabase}', expected ':memory:'. " .
                "Tests must use in-memory sqlite. Verify phpunit.xml DB_DATABASE env override."
            );
        }
    }
}
