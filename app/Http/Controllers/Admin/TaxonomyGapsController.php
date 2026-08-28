<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use Illuminate\Http\Request;

/**
 * GET /admin/questions/taxonomy-gaps
 *
 * Browser page listing every subject that is exhausted with zero PASS ideas.
 * Auth model: same shared secret as the health endpoint (QB_HEALTH_TOKEN,
 * timing-safe hash_equals, fail-closed). Token accepted as either:
 *   1. Authorization: Bearer <token>  (preferred)
 *   2. ?token=<token>                 (plain browser access)
 */
class TaxonomyGapsController extends Controller
{
    public function __invoke(Request $request)
    {
        if (! $this->isAuthorized($request)) {
            return response()->view('admin.question_audit_log_forbidden', [], 403);
        }

        $subjects = [];
        $error    = null;

        try {
            $repo     = new TaxonomyBankRepository();
            $subjects = $repo->findV11PreparationAnomalies(minFails: 1);
        } catch (\Throwable $e) {
            $error = 'Taxonomy schema unavailable: ' . $e->getMessage();
        }

        return view('admin.taxonomy_gaps', [
            'subjects' => $subjects,
            'error'    => $error,
        ]);
    }

    private function isAuthorized(Request $request): bool
    {
        $expected = (string) env('QB_HEALTH_TOKEN', '');
        if ($expected === '') {
            return false;
        }
        $given = (string) ($request->bearerToken() ?: $request->query('token', ''));
        if ($given === '') {
            return false;
        }
        return hash_equals($expected, $given);
    }
}
