<?php

declare(strict_types=1);

/**
 * PHPUnit hard gate for disposable PostgreSQL databases.
 *
 * The launcher creates a random database, supplies its name in both
 * PGDATABASE and TEST_DATABASE_NAME, and generates a one-run guard. A direct
 * vendor/bin/phpunit invocation therefore fails before Laravel can run
 * RefreshDatabase or migrate:fresh against any persistent database.
 */

require __DIR__ . '/../vendor/autoload.php';

$database = getenv('PGDATABASE') ?: '';
$expected = getenv('TEST_DATABASE_NAME') ?: '';
$guard = getenv('TEST_DATABASE_GUARD') ?: '';
$appEnv = getenv('APP_ENV') ?: '';
$connection = getenv('DB_CONNECTION') ?: '';
$databaseUrl = getenv('DATABASE_URL');

$validName = preg_match('/\Astrategybuzzer_test_[a-z0-9_]{12,80}\z/', $database) === 1;
$validGuard = preg_match('/\A[0-9a-f]{64}\z/', $guard) === 1;
$forbidden = in_array(strtolower($database), [
    'heliumdb',
    'postgres',
    'template0',
    'template1',
    'production',
    'development',
], true);

if (
    $appEnv !== 'testing'
    || $connection !== 'pgsql'
    || $databaseUrl !== ''
    || ! $validName
    || $database !== $expected
    || ! $validGuard
    || $forbidden
) {
    fwrite(
        STDERR,
        "TEST DATABASE SAFETY GUARD: refused. "
        . "Use scripts/run-isolated-postgres-tests.php; persistent databases are forbidden.\n",
    );
    exit(86);
}

$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    getenv('PGHOST') ?: '',
    getenv('PGPORT') ?: '5432',
    $database,
);

try {
    $pdo = new PDO(
        $dsn,
        getenv('PGUSER') ?: '',
        getenv('PGPASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $actual = $pdo->query('SELECT current_database()')->fetchColumn();
} catch (Throwable $exception) {
    fwrite(STDERR, "TEST DATABASE SAFETY GUARD: isolated database is unreachable.\n");
    exit(87);
}

if ($actual !== $database) {
    fwrite(STDERR, "TEST DATABASE SAFETY GUARD: connected database does not match the disposable target.\n");
    exit(88);
}