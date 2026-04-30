<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QuestionBank\BankDryDetector;
use App\Services\QuestionBank\QuestionBankRepository;
use App\Services\QuestionBank\Worker\BankNeedsCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * GET /api/admin/questions/health
 *
 * Json snapshot of the bank for Ops + the on-call dashboard. Intentionally
 * minimal — only enumerations the worker already maintains. No graphs.
 */
class QuestionBankHealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Bearer-token gate. Until a proper Admin role exists, we require a
        // shared secret in the QB_HEALTH_TOKEN env var. If the env var is
        // unset, the endpoint is closed (deny-by-default) — never publicly
        // browsable. Token comparison is hash_equals (timing-safe).
        $expected = (string) env('QB_HEALTH_TOKEN', '');
        $given = (string) $request->bearerToken();
        if ($expected === '' || $given === '' || !hash_equals($expected, $given)) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $language = $request->query('language');
        $modeFilter = $request->query('mode');

        $repo = new QuestionBankRepository();
        $needs = new BankNeedsCalculator($repo);

        $deficits = $needs->computeDeficits(limit: 50);
        if ($modeFilter) {
            $deficits = array_values(array_filter($deficits, fn ($r) => $r['mode'] === $modeFilter));
        }
        if ($language) {
            $deficits = array_values(array_filter($deficits, fn ($r) => $r['language'] === $language));
        }

        $buildable = $needs->estimateMatchesBuildable($language ?: null);
        if ($modeFilter) {
            $buildable = array_values(array_filter($buildable, fn ($r) => $r['mode'] === $modeFilter));
        }

        $minute = (int) floor(time() / 60);
        $rateOk = 0;
        $rateErr = 0;
        $okPattern = config('question_bank_profiles.worker.redis_keys.gen_counter_ok');
        $errPattern = config('question_bank_profiles.worker.redis_keys.gen_counter_err');
        for ($i = 0; $i < 60; $i++) {
            $rateOk += (int) Redis::get(sprintf($okPattern, $minute - $i));
            $rateErr += (int) Redis::get(sprintf($errPattern, $minute - $i));
        }

        $semaphoreKey = config('question_bank_profiles.worker.redis_keys.semaphore');
        $semaphoreOwner = Redis::get($semaphoreKey);

        $lastSuccess = $this->decodeRedisJson(config('question_bank_profiles.worker.redis_keys.last_success'));
        $lastRejectsRaw = Redis::lrange(config('question_bank_profiles.worker.redis_keys.last_rejects'), 0, 9) ?? [];
        $lastRejects = array_map(fn ($r) => json_decode($r, true) ?: $r, $lastRejectsRaw);

        $dry = (new BankDryDetector())->snapshot();

        return response()->json([
            'reported_at' => now()->toIso8601String(),
            'worker' => [
                'state' => $semaphoreOwner ? 'active' : 'dormant',
                'owner' => $semaphoreOwner,
                'rate_last_hour' => [
                    'success' => $rateOk,
                    'error' => $rateErr,
                ],
                'last_success' => $lastSuccess,
                'last_rejects' => $lastRejects,
            ],
            'critical_segments' => $deficits,
            'matches_buildable' => $buildable,
            'dry' => $dry,
        ]);
    }

    private function decodeRedisJson(string $key): ?array
    {
        $raw = Redis::get($key);
        if (!$raw) return null;
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }
}
