<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /** Refuse every Laravel test outside the launcher's disposable database. */
    protected function setUp(): void
    {
        parent::setUp();

        $defaultConnection = config('database.default');
        $expectedDatabase = getenv('TEST_DATABASE_NAME') ?: '';
        $configuredDatabase = (string) config('database.connections.pgsql.database');

        if ($defaultConnection !== 'pgsql') {
            throw new \RuntimeException(
                "TEST SAFETY GUARD TRIPPED: expected the isolated pgsql connection."
            );
        }

        if (
            preg_match('/\Astrategybuzzer_test_[a-z0-9_]{12,80}\z/', $expectedDatabase) !== 1
            || $configuredDatabase !== $expectedDatabase
            || in_array(strtolower($configuredDatabase), ['heliumdb', 'postgres', 'production', 'development'], true)
        ) {
            throw new \RuntimeException(
                "TEST SAFETY GUARD TRIPPED: configured database is not the disposable test target."
            );
        }

        $actualDatabase = DB::connection()->selectOne('SELECT current_database() AS name')->name ?? null;
        if ($actualDatabase !== $expectedDatabase) {
            throw new \RuntimeException(
                "TEST SAFETY GUARD TRIPPED: active database does not match the disposable test target."
            );
        }
    }
}
