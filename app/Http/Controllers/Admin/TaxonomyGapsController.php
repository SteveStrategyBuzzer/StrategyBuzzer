<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;

/**
 * GET /admin/questions/taxonomy-gaps
 *
 * Browser page listing every subject that is exhausted with zero PASS ideas.
 * Authorization is provided centrally by the `auth` and `admin` route
 * middleware.
 */
class TaxonomyGapsController extends Controller
{
    public function __invoke()
    {
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

}
