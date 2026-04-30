<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminQuestionAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Task #109 — Admin browser page to consult `admin_question_audit_log`.
 *
 * Strict scope: observability + audit only. No AI logic, no gameplay touch,
 * no schema change (the table was created in #94).
 *
 * Auth model: same shared secret as `/api/admin/questions/health`
 * (env `QB_HEALTH_TOKEN`), same timing-safe `hash_equals` comparison, same
 * deny-by-default behaviour if the env var is unset. Two transports are
 * accepted so the page is usable from a browser without weakening the secret:
 *   1. `Authorization: Bearer <token>` header (preferred — no log leak)
 *   2. `?token=<token>` query param (fallback for plain browser viewing)
 * Both go through the same `hash_equals` check; the secret itself is the
 * same as the JSON health endpoint, satisfying the "same auth or stricter"
 * requirement of #109.
 */
class QuestionBankAuditLogController extends Controller
{
    private const PER_PAGE = 50;

    public function __invoke(Request $request)
    {
        if (!$this->isAuthorized($request)) {
            return response()->view('admin.question_audit_log_forbidden', [], 403);
        }

        $filters = $this->extractFilters($request);

        $query = AdminQuestionAuditLog::query()
            ->select([
                'admin_question_audit_log.id',
                'admin_question_audit_log.caller_user_id',
                'admin_question_audit_log.endpoint',
                'admin_question_audit_log.http_status',
                'admin_question_audit_log.accepted',
                'admin_question_audit_log.created_at',
                'admin_question_audit_log.responded_at',
                'users.name as caller_name',
            ])
            ->leftJoin('users', 'users.id', '=', 'admin_question_audit_log.caller_user_id')
            ->orderByDesc('admin_question_audit_log.created_at')
            ->orderByDesc('admin_question_audit_log.id');

        $this->applyFilters($query, $filters);

        $rows = $query->paginate(self::PER_PAGE)->withQueryString();

        $endpoints = AdminQuestionAuditLog::query()
            ->select('endpoint')
            ->distinct()
            ->orderBy('endpoint')
            ->pluck('endpoint')
            ->all();

        return view('admin.question_audit_log', [
            'rows' => $rows,
            'filters' => $filters,
            'endpoints' => $endpoints,
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

    /**
     * @return array{user:string,endpoint:string,status:string,from:?string,to:?string}
     */
    private function extractFilters(Request $request): array
    {
        return [
            'user' => trim((string) $request->query('user', '')),
            'endpoint' => trim((string) $request->query('endpoint', '')),
            'status' => (string) $request->query('status', 'all'),
            'from' => $this->parseDate($request->query('from')),
            'to' => $this->parseDate($request->query('to')),
        ];
    }

    private function parseDate($value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param  array{user:string,endpoint:string,status:string,from:?string,to:?string}  $filters
     */
    private function applyFilters($query, array $filters): void
    {
        if ($filters['user'] !== '') {
            if (ctype_digit($filters['user'])) {
                $query->where('admin_question_audit_log.caller_user_id', (int) $filters['user']);
            } else {
                $needle = '%' . $filters['user'] . '%';
                $query->where('users.name', 'like', $needle);
            }
        }

        if ($filters['endpoint'] !== '') {
            $query->where('admin_question_audit_log.endpoint', $filters['endpoint']);
        }

        if ($filters['status'] === 'accepted') {
            $query->where('admin_question_audit_log.accepted', true);
        } elseif ($filters['status'] === 'rejected') {
            $query->where('admin_question_audit_log.accepted', false);
        }

        if ($filters['from'] !== null) {
            $query->where('admin_question_audit_log.created_at', '>=', $filters['from'] . ' 00:00:00');
        }
        if ($filters['to'] !== null) {
            $query->where('admin_question_audit_log.created_at', '<=', $filters['to'] . ' 23:59:59');
        }
    }
}
