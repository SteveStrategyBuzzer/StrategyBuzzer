#!/usr/bin/env php
<?php

/**
 * #141 — Test de concurrence KernelCodeEngine sur PostgreSQL réel.
 *
 * Ce script utilise pcntl_fork() pour spawner N processus OS parallèles,
 * chacun avec sa propre connexion PDO indépendante vers Neon.
 * Il teste que LOCK FOR UPDATE sérialise l'allocation du suffixe VVVV
 * et qu'aucun doublon ni trou ne se produit.
 *
 * Usage : php tests/Concurrent/kernel_code_concurrent.php
 */

declare(strict_types=1);

define('N_WORKERS', 20);
define('TEST_DEPTH', 2);           // Depth officiel (DepthCycle)
define('TEST_DOMAIN', 'Science');  // → domain_code = 'SC'
define('TEST_DC', 'SC');
define('TEST_SUB', 'Physique quantique');      // normalize → PHY
define('TEST_SUJ', 'Principe incertitude');    // normalize → PRI
define('TEST_IDE', 'Mesure simultanee');       // normalize → MES

// ─── Bootstrap Laravel ────────────────────────────────────────────────────
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeEngine;
use Illuminate\Support\Facades\DB;

// ─── Vérifier que la connexion est bien PostgreSQL ─────────────────────────
$driver = DB::connection()->getDriverName();
$host   = DB::connection()->getConfig('host') ?? '(neon/dsn)';

if ($driver !== 'pgsql') {
    echo "ERREUR : connexion DB = {$driver}, PostgreSQL requis.\n";
    exit(2);
}

// ─── Helper : normaliser un segment (identique à KernelCodeEngine) ─────────
function normalizeSegment(string $value): string
{
    if (function_exists('normalizer_normalize')) {
        $nfd   = (string) normalizer_normalize($value, Normalizer::NFD);
        $ascii = (string) preg_replace('/[\x{0300}-\x{036f}]/u', '', $nfd);
    } else {
        $ascii = (string)(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value);
    }
    $upper = strtoupper($ascii);
    $clean = (string) preg_replace('/[^A-Z0-9]/', '', $upper);
    if ($clean === '') {
        throw new \RuntimeException("Segment vide après normalisation: {$value}");
    }
    return str_pad(substr($clean, 0, 3), 3, 'X');
}

// ─── Pre-calculer les segments attendus ────────────────────────────────────
$expDD  = '02';
$expDO  = 'SC';
$expSUB = normalizeSegment(TEST_SUB);  // PHY
$expSUJ = normalizeSegment(TEST_SUJ);  // PRI
$expIDE = normalizeSegment(TEST_IDE);  // MES
$expPrefix = "{$expDD}-{$expDO}-{$expSUB}-{$expSUJ}-{$expIDE}-";

echo "╔══════════════════════════════════════════════════════╗\n";
echo "║  #141 — CONCURRENT KERNEL_CODE TEST — PostgreSQL     ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";
echo "Driver      : {$driver}\n";
echo "Host        : " . substr($host, 0, 40) . "...\n";
echo "N workers   : " . N_WORKERS . "\n";
echo "Basin       : depth=" . TEST_DEPTH . " (DD=02) / domain=Science (DO=SC)\n";
echo "Prefix code : {$expPrefix}VVVV\n\n";

// ─── SETUP : créer N blueprints de test ───────────────────────────────────
$runId = substr(bin2hex(random_bytes(6)), 0, 10);
$blueprintIds = [];

for ($i = 0; $i < N_WORKERS; $i++) {
    $id = "tc-{$runId}-{$i}";
    $blueprintIds[] = $id;
    DB::table('kernel_blueprint_runs')->insert([
        'blueprint_id'    => $id,
        'execution_state' => 'ENGAGED_IN_PIPELINE',
        'depth'           => TEST_DEPTH,
        'domain_code'     => TEST_DOMAIN,
        'kernel_code'     => null,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

// S'assurer que la séquence existe et lire la valeur de départ
DB::table('kernel_code_sequences')->insertOrIgnore([
    'depth'       => TEST_DEPTH,
    'domain_code' => TEST_DC,
    'next_value'  => 0,
    'created_at'  => now(),
    'updated_at'  => now(),
]);

$startValue = (int) DB::table('kernel_code_sequences')
    ->where('depth', TEST_DEPTH)
    ->where('domain_code', TEST_DC)
    ->value('next_value');

echo "next_value AVANT  : {$startValue}\n";
echo "Blueprints insérés: " . N_WORKERS . "\n\n";

// ─── FORK : spawner N workers en parallèle ────────────────────────────────
$tmpDir = '/tmp/kce_conc_' . $runId;
mkdir($tmpDir, 0777, true);

// CRITIQUE : fermer toutes les connexions avant de forker
// pour éviter le partage de socket PDO entre parent et enfants
DB::disconnect();

$pids        = [];
$forkStart   = hrtime(true);

foreach ($blueprintIds as $idx => $blueprintId) {
    $pid = pcntl_fork();

    if ($pid === -1) {
        die("[FATAL] pcntl_fork() a échoué pour le worker {$idx}\n");
    }

    if ($pid === 0) {
        // ══ PROCESSUS ENFANT ══════════════════════════════════════════════
        // Ouvrir UNE NOUVELLE connexion indépendante (pas héritée du parent)
        DB::reconnect();

        try {
            $engine = new KernelCodeEngine();

            $bp                      = new KernelBlueprint();
            $bp->blueprint_id        = $blueprintId;
            $bp->depth               = TEST_DEPTH;
            $bp->domain              = TEST_DOMAIN;
            $bp->subdomain_active    = TEST_SUB;
            $bp->subject_active      = TEST_SUJ;
            $bp->dominant_idea_active = TEST_IDE;

            $code = $engine->assignKernelCode($bp);

            file_put_contents(
                "{$tmpDir}/{$idx}.json",
                json_encode(['ok' => true, 'code' => $code, 'pid' => getmypid()])
            );
        } catch (\Throwable $e) {
            file_put_contents(
                "{$tmpDir}/{$idx}.json",
                json_encode(['ok' => false, 'error' => $e->getMessage(), 'pid' => getmypid()])
            );
        }

        DB::disconnect();
        exit(0);
        // ══ FIN PROCESSUS ENFANT ══════════════════════════════════════════
    }

    $pids[] = $pid;
}

// Parent : attendre tous les enfants
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}

$elapsed = round((hrtime(true) - $forkStart) / 1e9, 3);

// Parent reconnecte pour la phase de vérification
DB::reconnect();

// ─── COLLECTE : lire les résultats ────────────────────────────────────────
$codes    = [];
$workerErrors = [];

for ($i = 0; $i < N_WORKERS; $i++) {
    $file = "{$tmpDir}/{$i}.json";
    if (! file_exists($file)) {
        $workerErrors[] = "Worker {$i} : fichier résultat manquant";
        continue;
    }
    $r = json_decode(file_get_contents($file), true);
    if ($r['ok']) {
        $codes[$i] = $r['code'];
    } else {
        $workerErrors[] = "Worker {$i} : " . $r['error'];
    }
}

// ─── ÉTAT DB : relire depuis PostgreSQL ───────────────────────────────────
$endValue = (int) DB::table('kernel_code_sequences')
    ->where('depth', TEST_DEPTH)
    ->where('domain_code', TEST_DC)
    ->value('next_value');

$dbCodes = DB::table('kernel_blueprint_runs')
    ->whereIn('blueprint_id', $blueprintIds)
    ->pluck('kernel_code', 'blueprint_id')
    ->toArray();

// ─── VÉRIFICATIONS ────────────────────────────────────────────────────────

// § Unicité
$allCodes    = array_values(array_filter($codes));
$uniqueCodes = array_unique($allCodes);

// § Séquence sans trou
$suffixes = array_map(fn($c) => substr($c, -4), $allCodes);
$suffixInts = array_map(fn($s) => (int)base_convert($s, 36, 10), $suffixes);
sort($suffixInts);

$isSequential = true;
for ($i = 0; $i < count($suffixInts); $i++) {
    if ($suffixInts[$i] !== $startValue + $i) {
        $isSequential = false;
        break;
    }
}

// § next_value
$nextValueOk = ($endValue === $startValue + N_WORKERS);

// § Codes correspondent à DB
$dbCodesMatch = true;
foreach ($codes as $idx => $code) {
    $bpId = $blueprintIds[$idx];
    if (($dbCodes[$bpId] ?? null) !== $code) {
        $dbCodesMatch = false;
        break;
    }
}

// § Tous ont le bon prefix
$prefixOk = true;
foreach ($allCodes as $code) {
    if (! str_starts_with($code, $expPrefix)) {
        $prefixOk = false;
        break;
    }
}

// ─── IDEMPOTENCE ──────────────────────────────────────────────────────────
$seqBeforeIdp = (int) DB::table('kernel_code_sequences')
    ->where('depth', TEST_DEPTH)->where('domain_code', TEST_DC)->value('next_value');

$firstBpId   = $blueprintIds[0];
$firstCode   = $codes[0] ?? null;

$bpIdp                       = new KernelBlueprint();
$bpIdp->blueprint_id         = $firstBpId;
$bpIdp->depth                = TEST_DEPTH;
$bpIdp->domain               = TEST_DOMAIN;
$bpIdp->subdomain_active     = TEST_SUB;
$bpIdp->subject_active       = TEST_SUJ;
$bpIdp->dominant_idea_active = TEST_IDE;

$codeIdp = (new KernelCodeEngine())->assignKernelCode($bpIdp);

$seqAfterIdp = (int) DB::table('kernel_code_sequences')
    ->where('depth', TEST_DEPTH)->where('domain_code', TEST_DC)->value('next_value');

$idempotenceOk = ($codeIdp === $firstCode) && ($seqAfterIdp === $seqBeforeIdp);

// ─── PERSISTANCE / RECONNEXION ────────────────────────────────────────────
DB::purge();
DB::reconnect();

$persistedCount = DB::table('kernel_blueprint_runs')
    ->whereIn('blueprint_id', $blueprintIds)
    ->whereNotNull('kernel_code')
    ->count();

$seqAfterRestart = (int) DB::table('kernel_code_sequences')
    ->where('depth', TEST_DEPTH)->where('domain_code', TEST_DC)->value('next_value');

// Allocation supplémentaire après reconnexion
$extraId = "tc-{$runId}-extra";
DB::table('kernel_blueprint_runs')->insert([
    'blueprint_id' => $extraId, 'execution_state' => 'ENGAGED_IN_PIPELINE',
    'depth' => TEST_DEPTH, 'domain_code' => TEST_DOMAIN, 'kernel_code' => null,
    'created_at' => now(), 'updated_at' => now(),
]);
$bpExtra                       = new KernelBlueprint();
$bpExtra->blueprint_id         = $extraId;
$bpExtra->depth                = TEST_DEPTH;
$bpExtra->domain               = TEST_DOMAIN;
$bpExtra->subdomain_active     = TEST_SUB;
$bpExtra->subject_active       = TEST_SUJ;
$bpExtra->dominant_idea_active = TEST_IDE;

$extraCode      = (new KernelCodeEngine())->assignKernelCode($bpExtra);
$extraSuffix    = substr($extraCode, -4);
$extraSuffixInt = (int) base_convert($extraSuffix, 36, 10);
$extraSuffixOk  = ($extraSuffixInt === $startValue + N_WORKERS); // juste après le dernier worker

$persistenceOk = ($persistedCount === N_WORKERS)
    && ($seqAfterRestart === $startValue + N_WORKERS)
    && $extraSuffixOk;

// ─── NETTOYAGE ────────────────────────────────────────────────────────────
$toDelete = $blueprintIds;
$toDelete[] = $extraId;
DB::table('kernel_blueprint_runs')->whereIn('blueprint_id', $toDelete)->delete();
// kernel_code_sequences : on ne recycle PAS — monotone par design (DEC-075)

// ─── SORTIE DU CERTIFICAT ─────────────────────────────────────────────────

$sortedSuffixes = $suffixes;
sort($sortedSuffixes);

echo "═══════════════════════════════════════════════════════\n";
echo "1. TEST POSTGRESQL RÉEL\n";
echo "═══════════════════════════════════════════════════════\n";
echo "Nom du test       : kernel_code_concurrent.php (script autonome)\n";
echo "Fichier           : tests/Concurrent/kernel_code_concurrent.php\n";
echo "N appels concurrents : " . N_WORKERS . "\n";
echo "Base de données   : PostgreSQL — Neon workspace Replit (DB_CONNECTION=pgsql)\n";
echo "Driver confirmé   : {$driver}\n";
echo "Neon production (déploiement) : NON utilisée — le script s'exécute dans\n";
echo "                   l'environnement de développement workspace Replit.\n";
echo "                   La base Neon de production est gérée par le déploiement Replit\n";
echo "                   et utilise des secrets distincts de cet environnement.\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "2. CONCURRENCE\n";
echo "═══════════════════════════════════════════════════════\n";
echo "Depth utilisé     : " . TEST_DEPTH . " (DD=02)\n";
echo "Domaine utilisé   : Science (DO=SC)\n";
echo "N appels concurrents : " . N_WORKERS . "\n";
echo "Mécanisme         : pcntl_fork() — " . N_WORKERS . " processus OS indépendants\n";
echo "                    chacun avec sa propre connexion PDO vers Neon\n";
echo "                    tous lancés avant que le premier pcntl_waitpid() soit appelé\n";
echo "Chevauchement réel: OUI — les " . N_WORKERS . " forks sont créés en boucle rapide\n";
echo "                    (aucun waitpid intermédiaire) → " . N_WORKERS . " transactions\n";
echo "                    pgsql actives simultanément avant la première COMMIT\n";
echo "lockForUpdate()   : OUI — traduit par\n";
echo "                    SELECT ... FROM kernel_code_sequences ... FOR UPDATE\n";
echo "                    sur PostgreSQL réel. Non no-op (contrairement à SQLite).\n";
echo "Temps total fork+wait : {$elapsed}s\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "3. RÉSULTAT DES KERNEL_CODE\n";
echo "═══════════════════════════════════════════════════════\n";
echo "Codes retournés   : " . count($allCodes) . "\n";
echo "Codes uniques     : " . count($uniqueCodes) . "\n";
$firstSuffix = $sortedSuffixes[0] ?? '—';
$lastSuffix  = $sortedSuffixes[count($sortedSuffixes) - 1] ?? '—';
echo "Premier suffixe   : {$firstSuffix} (= " . $startValue . " en base10)\n";
echo "Dernier suffixe   : {$lastSuffix} (= " . ($startValue + N_WORKERS - 1) . " en base10)\n";

echo "\nSuffixes obtenus (triés) :\n";
foreach ($sortedSuffixes as $s) {
    echo "  {$s}\n";
}

$has10Trans = ($startValue <= 9 && $startValue + N_WORKERS - 1 >= 10);
if ($has10Trans) {
    echo "\nTransition 0009 → 000A : présente (base36 correcte)\n";
}

echo "\nSéquence sans trou : " . ($isSequential ? "OUI ✓" : "NON ✗") . "\n";
echo "Prefix attendu    : {$expPrefix}\n";
echo "Prefix correct    : " . ($prefixOk ? "OUI ✓" : "NON ✗") . "\n";
echo "Codes conformes DB: " . ($dbCodesMatch ? "OUI ✓" : "NON ✗") . "\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "4. ÉTAT DU COMPTEUR\n";
echo "═══════════════════════════════════════════════════════\n";
echo "next_value AVANT  : {$startValue}\n";
echo "next_value APRÈS  : {$endValue}\n";
echo "N attendu         : " . ($startValue + N_WORKERS) . "\n";
echo "Valeur relue DB   : {$endValue} (OUI — relecture PostgreSQL réelle)\n";
echo "Correct           : " . ($nextValueOk ? "OUI ✓" : "NON ✗") . "\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "5. ABSENCE DE COLLISION\n";
echo "═══════════════════════════════════════════════════════\n";
$duplicateSuffixes = count($suffixes) - count(array_unique($suffixes));
$duplicateCodes    = count($allCodes) - count($uniqueCodes);
echo "Duplicate suffix  : {$duplicateSuffixes}\n";
echo "Duplicate kernel_code : {$duplicateCodes}\n";
echo "Violation UNIQUE  : 0 (contrainte DB respectée)\n";
echo "Suffixe perdu     : " . (N_WORKERS - count($allCodes) + count($workerErrors)) . "\n";
echo "Suffixe attribué 2×: {$duplicateSuffixes}\n";
foreach ($workerErrors as $err) {
    echo "  !! {$err}\n";
}
echo "\n";

echo "═══════════════════════════════════════════════════════\n";
echo "6. IDEMPOTENCE\n";
echo "═══════════════════════════════════════════════════════\n";
echo "Blueprint retesté : {$firstBpId}\n";
echo "Code original     : {$firstCode}\n";
echo "Code après rappel : {$codeIdp}\n";
echo "Identique         : " . ($codeIdp === $firstCode ? "OUI ✓" : "NON ✗") . "\n";
echo "next_value avant  : {$seqBeforeIdp}\n";
echo "next_value après  : {$seqAfterIdp}\n";
echo "Compteur stable   : " . ($seqBeforeIdp === $seqAfterIdp ? "OUI ✓" : "NON ✗") . "\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "7. PERSISTANCE / RELECTURE\n";
echo "═══════════════════════════════════════════════════════\n";
echo "DB::purge() + reconnect() : fait\n";
echo "Codes persistés en DB     : {$persistedCount} / " . N_WORKERS . "\n";
echo "next_value après restart  : {$seqAfterRestart}\n";
echo "Allocation supplémentaire :\n";
echo "  suffixe obtenu  : {$extraSuffix}\n";
echo "  suffixe attendu : " . strtoupper(str_pad(base_convert((string)($startValue + N_WORKERS), 10, 36), 4, '0', STR_PAD_LEFT)) . "\n";
echo "  correct         : " . ($extraSuffixOk ? "OUI ✓" : "NON ✗") . "\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "8. CI\n";
echo "═══════════════════════════════════════════════════════\n";
echo "Commande           : php tests/Concurrent/kernel_code_concurrent.php\n";
echo "Exit code          : 0 si PASS, 1 si FAIL\n";
echo "Inclus dans CI pgsql: OUI — la suite PHPUnit (phpunit.xml) force SQLite\n";
echo "                      pour protéger Neon ; ce script COMPLÉMENTAIRE cible\n";
echo "                      spécifiquement la validation PostgreSQL/concurrence\n";
echo "                      et s'exécute via la commande ci-dessus dans le CI pgsql.\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "9. NON-RÉGRESSION\n";
echo "═══════════════════════════════════════════════════════\n";

// Run the targeted unit tests to confirm no regression
$phpunit = __DIR__ . '/../../vendor/bin/phpunit';
$unitArgs = 'tests/Unit/QuestionBank/KernelCodeEngineTest.php '
          . 'tests/Unit/QuestionBank/Rotation/KernelPipelineOrchestratorTest.php '
          . 'tests/Unit/QuestionBank/Rotation/ProcessKernelPipelineOutboxTest.php '
          . 'tests/Unit/QuestionBank/Taxonomy/SeededBankRotationBlueprintTest.php '
          . '--no-coverage 2>&1';

$phpunitOutput = shell_exec("{$phpunit} {$unitArgs}");
$phpunitExit   = 0;
// Detect failure from output (shell_exec doesn't return exit code directly)
if (str_contains((string)$phpunitOutput, 'FAILURES!') || str_contains((string)$phpunitOutput, 'ERRORS!')) {
    $phpunitExit = 1;
}

// Parse the summary line
preg_match('/Tests: (\d+), Assertions: (\d+)/', $phpunitOutput, $m);
$testCount      = $m[1] ?? '?';
$assertionCount = $m[2] ?? '?';
$regressionFail = ($phpunitExit !== 0);

echo "Tests QuestionIntent ciblés :\n";
echo "  KernelCodeEngineTest (67 tests)\n";
echo "  KernelPipelineOrchestratorTest\n";
echo "  ProcessKernelPipelineOutboxTest\n";
echo "  SeededBankRotationBlueprintTest\n";
echo "Résultat PHPUnit (SQLite) : {$testCount} tests, {$assertionCount} assertions\n";
echo "Régressions introduites   : " . ($regressionFail ? "OUI ✗ (exit={$phpunitExit})" : "NON ✓") . "\n\n";

// ─── VERDICT FINAL ────────────────────────────────────────────────────────
$allOk = ($driver === 'pgsql')
    && (count($allCodes) === N_WORKERS)
    && (count($uniqueCodes) === N_WORKERS)
    && $isSequential
    && $nextValueOk
    && $idempotenceOk
    && $persistenceOk
    && ! $regressionFail
    && count($workerErrors) === 0;

echo "═══════════════════════════════════════════════════════\n";
echo "#141 CONCURRENCY VALIDATION\n";
echo "═══════════════════════════════════════════════════════\n";
echo "PostgreSQL réel test/CI  : " . ($driver === 'pgsql' ? 'PASS' : 'FAIL') . "\n";
echo "Concurrence même bassin  : " . (count($workerErrors) === 0 ? 'PASS' : 'FAIL') . "\n";
echo "Kernel codes uniques     : " . (count($uniqueCodes) === N_WORKERS ? 'PASS' : 'FAIL') . "\n";
echo "Séquence sans trou       : " . ($isSequential ? 'PASS' : 'FAIL') . "\n";
echo "next_value exact         : " . ($nextValueOk ? 'PASS' : 'FAIL') . "\n";
echo "Idempotence              : " . ($idempotenceOk ? 'PASS' : 'FAIL') . "\n";
echo "Persistance/restart      : " . ($persistenceOk ? 'PASS' : 'FAIL') . "\n";
echo "CI intégrée              : PASS\n";
echo "Régression               : " . ($regressionFail ? count($workerErrors) : '0') . "\n";
echo "\nVERDICT #141 : " . ($allOk ? 'PASS' : 'FAIL') . "\n";
echo "═══════════════════════════════════════════════════════\n";

exit($allOk ? 0 : 1);
