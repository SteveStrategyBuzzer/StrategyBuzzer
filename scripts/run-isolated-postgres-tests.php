#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Creates one disposable PostgreSQL database, verifies the complete migration
 * chain, runs PHPUnit, and always drops the database.
 *
 * No URL, password, token, or credential is printed.
 */

const TEST_DATABASE_PREFIX = 'strategybuzzer_test_';
const FORBIDDEN_DATABASES = [
    'heliumdb',
    'postgres',
    'template0',
    'template1',
    'production',
    'development',
];

$root = dirname(__DIR__);
$host = getenv('PGHOST') ?: '';
$port = getenv('PGPORT') ?: '5432';
$user = getenv('PGUSER') ?: '';
$password = getenv('PGPASSWORD') ?: '';
$runtimeDatabase = getenv('PGDATABASE') ?: '';

if ($host === '' || $user === '' || $runtimeDatabase === '') {
    fwrite(STDERR, "Disposable PostgreSQL launcher refused: runtime PostgreSQL configuration is incomplete.\n");
    exit(80);
}

if (strtolower($runtimeDatabase) !== 'heliumdb') {
    fwrite(STDERR, "Disposable PostgreSQL launcher refused: unexpected runtime database identity.\n");
    exit(81);
}

$database = TEST_DATABASE_PREFIX . strtolower(bin2hex(random_bytes(12)));
$guard = hash('sha256', random_bytes(32));

if (
    preg_match('/\Astrategybuzzer_test_[a-z0-9_]{12,80}\z/', $database) !== 1
    || in_array(strtolower($database), FORBIDDEN_DATABASES, true)
) {
    fwrite(STDERR, "Disposable PostgreSQL launcher refused: unsafe generated database name.\n");
    exit(82);
}

$maintenance = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=postgres', $host, $port),
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$quotedDatabase = '"' . str_replace('"', '""', $database) . '"';
$created = false;
$exitCode = 1;

try {
    $maintenance->exec("CREATE DATABASE {$quotedDatabase}");
    $created = true;
    fwrite(STDOUT, "ISOLATION database={$database} source=postgres disposable=yes\n");

    $runtimeEnvironment = getenv();
    if (! is_array($runtimeEnvironment)) {
        throw new RuntimeException('Unable to read the process environment.');
    }

    $environment = array_merge($runtimeEnvironment, [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_DATABASE' => $database,
        'DATABASE_URL' => '',
        'PGHOST' => $host,
        'PGPORT' => $port,
        'PGUSER' => $user,
        'PGPASSWORD' => $password,
        'PGDATABASE' => $database,
        'TEST_DATABASE_NAME' => $database,
        'TEST_DATABASE_GUARD' => $guard,
    ]);

    $migrationExit = runCommand(
        [PHP_BINARY, 'artisan', 'migrate:fresh', '--force', '--no-interaction', '--no-ansi'],
        $root,
        $environment,
    );
    if ($migrationExit !== 0) {
        fwrite(STDERR, "MIGRATION_CHAIN=FAIL\n");
        $exitCode = $migrationExit;
    } else {
        fwrite(STDOUT, "MIGRATION_CHAIN=PASS\n");

        $phpunitArguments = array_slice($argv, 1);
        if ($phpunitArguments === []) {
            $phpunitArguments = ['--colors=never'];
        }

        $exitCode = runCommand(
            array_merge([$root . '/vendor/bin/phpunit'], $phpunitArguments),
            $root,
            $environment,
        );
    }
} finally {
    if ($created) {
        $maintenance->exec(
            "SELECT pg_terminate_backend(pid) FROM pg_stat_activity "
            . "WHERE datname = " . $maintenance->quote($database) . " AND pid <> pg_backend_pid()"
        );
        $maintenance->exec("DROP DATABASE IF EXISTS {$quotedDatabase}");
        fwrite(STDOUT, "ISOLATION_CLEANUP database={$database} dropped=yes\n");
    }
}

exit($exitCode);

/**
 * @param list<string> $command
 * @param array<string, string> $environment
 */
function runCommand(array $command, string $cwd, array $environment): int
{
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    $process = proc_open(
        $escaped,
        [STDIN, STDOUT, STDERR],
        $pipes,
        $cwd,
        $environment,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start isolated test command.');
    }

    return proc_close($process);
}