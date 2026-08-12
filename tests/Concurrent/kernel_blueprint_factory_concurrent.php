<?php

/**
 * Test de concurrence — KernelBlueprintFactory::create() — B1
 *
 * Objectif : démontrer que la contrainte DB one_active_blueprint_idx garantit
 * qu'au plus un Blueprint actif peut être créé, même sous charge concurrente.
 *
 * Protocole :
 *   - N_WORKERS processus forkés tentent chacun KernelBlueprintFactory::create()
 *     simultanément après une barrière de synchronisation.
 *   - Résultat attendu :
 *       exactement 1 SUCCESS
 *       N_WORKERS - 1 RuntimeException
 *       exactement 1 ligne CREATED_UNENGAGED en DB
 *
 * Prérequis :
 *   - PHP avec pcntl et pdo_pgsql
 *   - Variable d'env NEON_DATABASE_URL (PostgreSQL)
 *   - Migration 2026_08_12_000001 déjà connue (index recréé dans schéma isolé)
 *
 * Utilisation :
 *   php tests/Concurrent/kernel_blueprint_factory_concurrent.php
 */

declare(strict_types=1);

define('N_WORKERS', 20);

// ── Bootstrap Laravel ────────────────────────────────────────────────────────
require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use Illuminate\Support\Facades\DB;

// ── Schéma isolé ─────────────────────────────────────────────────────────────
$runId = substr(md5(uniqid('', true)), 0, 8);
$schema = "test_kbf_{$runId}";
$tmpDir = sys_get_temp_dir() . "/kbf_concurrent_{$runId}";
mkdir($tmpDir, 0777, true);

// Créer le schéma isolé et la table
DB::statement("CREATE SCHEMA {$schema}");
DB::statement("SET search_path TO {$schema}");

DB::statement("
    CREATE TABLE {$schema}.kernel_blueprint_runs (
        blueprint_id    VARCHAR(36)  PRIMARY KEY,
        execution_state VARCHAR(64)  NOT NULL DEFAULT 'CREATED_UNENGAGED',
        depth           SMALLINT     NULL,
        domain_code     VARCHAR(64)  NULL,
        kernel_code     VARCHAR(22)  NULL,
        engaged_at      TIMESTAMP    NULL,
        received_at     TIMESTAMP    NULL,
        created_at      TIMESTAMP    NOT NULL,
        updated_at      TIMESTAMP    NOT NULL
    )
");

// Index partiel atomique — cœur de la garantie B1
DB::statement("
    CREATE UNIQUE INDEX one_active_blueprint_idx
    ON {$schema}.kernel_blueprint_runs ((1))
    WHERE execution_state IN ('CREATED_UNENGAGED', 'ENGAGED_IN_PIPELINE')
");

// ── Synchronisation : barrière via fichier ───────────────────────────────────
$barrierFile = "{$tmpDir}/barrier";
$pids        = [];

for ($i = 0; $i < N_WORKERS; $i++) {
    $pid = pcntl_fork();

    if ($pid === -1) {
        die("[FATAL] pcntl_fork() échoué pour le worker {$i}\n");
    }

    if ($pid === 0) {
        // ── PROCESSUS ENFANT ─────────────────────────────────────────────────
        // Nouvelle connexion indépendante avec search_path vers le schéma isolé.
        DB::reconnect('pgsql');
        DB::statement("SET search_path TO {$schema}");

        // Attendre la barrière (parent la crée quand tous les workers sont prêts)
        $waited = 0;
        while (! file_exists($barrierFile) && $waited < 5) {
            usleep(10_000); // 10 ms
            $waited += 0.01;
        }

        try {
            $factory = new KernelBlueprintFactory();
            $bp      = $factory->create();

            file_put_contents(
                "{$tmpDir}/{$i}.json",
                json_encode([
                    'ok'           => true,
                    'blueprint_id' => $bp->blueprint_id,
                    'pid'          => getmypid(),
                ])
            );
        } catch (\RuntimeException $e) {
            file_put_contents(
                "{$tmpDir}/{$i}.json",
                json_encode([
                    'ok'    => false,
                    'error' => $e->getMessage(),
                    'pid'   => getmypid(),
                ])
            );
        } catch (\Throwable $e) {
            file_put_contents(
                "{$tmpDir}/{$i}.json",
                json_encode([
                    'ok'    => false,
                    'error' => '[UNEXPECTED] ' . $e->getMessage(),
                    'pid'   => getmypid(),
                ])
            );
        }

        DB::disconnect();
        exit(0);
        // ── FIN PROCESSUS ENFANT ─────────────────────────────────────────────
    }

    $pids[] = $pid;
}

// Laisser un instant pour que tous les workers soient en attente, puis ouvrir la barrière
usleep(300_000); // 300 ms
file_put_contents($barrierFile, '1');

// Attendre tous les enfants
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}

// ── Collecte des résultats ───────────────────────────────────────────────────
$results  = [];
$successes = 0;
$failures  = 0;

for ($i = 0; $i < N_WORKERS; $i++) {
    $file = "{$tmpDir}/{$i}.json";
    if (! file_exists($file)) {
        $results[$i] = ['ok' => false, 'error' => 'NO_OUTPUT_FILE'];
        $failures++;
        continue;
    }
    $r = json_decode(file_get_contents($file), true);
    $results[$i] = $r;
    if ($r['ok'] === true) {
        $successes++;
    } else {
        $failures++;
    }
}

// ── Vérification DB ──────────────────────────────────────────────────────────
DB::reconnect('pgsql');
DB::statement("SET search_path TO {$schema}");

$activeCount = DB::table('kernel_blueprint_runs')
    ->whereIn('execution_state', ['CREATED_UNENGAGED', 'ENGAGED_IN_PIPELINE'])
    ->count();

$totalRows = DB::table('kernel_blueprint_runs')->count();

// ── Critères de PASS ─────────────────────────────────────────────────────────
$exactlyOneSuccess = ($successes === 1);
$exactlyNMinusOneFail = ($failures === N_WORKERS - 1);
$exactlyOneActiveInDb = ($activeCount === 1);

$allPass = $exactlyOneSuccess && $exactlyNMinusOneFail && $exactlyOneActiveInDb;

// ── Nettoyage ────────────────────────────────────────────────────────────────
DB::statement("SET search_path TO public");
DB::statement("DROP SCHEMA {$schema} CASCADE");

// Supprimer les fichiers temporaires
foreach (glob("{$tmpDir}/*.json") as $f) {
    unlink($f);
}
if (file_exists($barrierFile)) {
    unlink($barrierFile);
}
rmdir($tmpDir);

// ── Rapport ──────────────────────────────────────────────────────────────────
echo "\n";
echo "=== kernel_blueprint_factory_concurrent — B1 Atomicité ===\n\n";
echo "Workers lancés    : " . N_WORKERS . "\n";
echo "Succès            : {$successes}  (attendu : 1)\n";
echo "Refus             : {$failures}  (attendu : " . (N_WORKERS - 1) . ")\n";
echo "Blueprints actifs : {$activeCount} (attendu : 1)\n";
echo "Total lignes DB   : {$totalRows}\n";
echo "\n";

echo "Critères :\n";
echo "  Exactement 1 SUCCESS       : " . ($exactlyOneSuccess     ? 'PASS' : 'FAIL') . "\n";
echo "  Exactement N-1 REFUS       : " . ($exactlyNMinusOneFail  ? 'PASS' : 'FAIL') . "\n";
echo "  Exactement 1 Blueprint actif en DB : " . ($exactlyOneActiveInDb ? 'PASS' : 'FAIL') . "\n";
echo "\n";
echo "VERDICT B1 : " . ($allPass ? 'PASS' : 'FAIL') . "\n\n";

if (! $allPass) {
    echo "Détail des workers :\n";
    foreach ($results as $i => $r) {
        $status = $r['ok'] ? 'SUCCESS' : 'FAIL';
        $detail = $r['ok']
            ? ('blueprint_id=' . ($r['blueprint_id'] ?? '?'))
            : ('error=' . ($r['error'] ?? '?'));
        echo "  Worker {$i} [{$status}] {$detail}\n";
    }
}

exit($allPass ? 0 : 1);
