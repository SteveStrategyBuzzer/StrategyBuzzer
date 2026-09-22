<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QuestionBank\Quarantine\KernelQuarantineAdminReadService;
use App\Services\QuestionBank\Quarantine\KernelQuarantineAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class QuarantineAdminController extends Controller
{
    private const CLAIM_SESSION_PREFIX = 'admin_quarantine_claim.';

    public function __construct(
        private readonly KernelQuarantineAdminReadService $reader = new KernelQuarantineAdminReadService(),
        private readonly KernelQuarantineAdminService $service = new KernelQuarantineAdminService(),
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'state' => ['nullable', 'in:EDITABLE,READY,IN_FLIGHT'],
            'cause' => ['nullable', 'in:SUSPICION,CONTENT_UNTRANSLATABLE,MANUAL_CORRECTION'],
            'phase' => ['nullable', 'in:PHASE1,VALIDATION_PHASE1,PHASE2,VALIDATION_PHASE2'],
            'blueprint' => ['nullable', 'string', 'max:128'],
            'slot' => ['nullable', 'string', 'max:64'],
            'language' => ['nullable', 'in:fr,es,de,it,pt,ru,zh,ar,el'],
        ]);

        return view('admin.quarantine.index', [
            'rows' => $this->reader->queue($filters),
            'filters' => $filters,
        ]);
    }

    public function show(string $copyId)
    {
        $detail = $this->reader->detail($copyId);
        abort_if($detail === null, 404);

        return view('admin.quarantine.show', $detail);
    }

    public function claim(Request $request, string $copyId): RedirectResponse
    {
        $data = $request->validate(['copy_version' => ['required', 'integer', 'min:1']]);
        try {
            $token = (string) Str::orderedUuid();
            $this->service->claimForAdmin($copyId, (int) $data['copy_version'], $token);
            $request->session()->put(self::CLAIM_SESSION_PREFIX . $copyId, $token);
            return back()->with('status', 'Copie prise en charge.');
        } catch (LogicException $exception) {
            return $this->conflict($exception->getMessage());
        }
    }

    public function source(Request $request, string $copyId): RedirectResponse
    {
        $data = $this->validateCorrection($request);
        $source = $this->validatePayload($data['source'], 'source');
        return $this->runAction($request, $copyId, fn (?string $claim) => $this->service->prepareSourceCorrection(
            $copyId, $data['cognitive_type'], $source, (int) $data['copy_version'],
            (int) $data['manual_revision'], $this->nullableInt($data['canonical_base_revision'] ?? null), $claim,
        ));
    }

    public function translation(Request $request, string $copyId): RedirectResponse
    {
        $data = $this->validateCorrection($request);
        $language = $request->validate([
            'language_code' => ['required', 'in:fr,es,de,it,pt,ru,zh,ar,el'],
        ])['language_code'];
        $translation = $this->validatePayload($data['translation'], 'translation');
        return $this->runAction($request, $copyId, fn (?string $claim) => $this->service->prepareTranslationCorrection(
            $copyId, $data['cognitive_type'], $language, $translation, (int) $data['copy_version'],
            (int) $data['manual_revision'], $this->nullableInt($data['canonical_base_revision'] ?? null), $claim,
        ));
    }

    public function regenerate(Request $request, string $copyId): RedirectResponse
    {
        $data = $request->validate([
            'copy_version' => ['required', 'integer', 'min:1'],
            'owner_phase' => ['required', 'in:PHASE1,VALIDATION_PHASE1,PHASE2,VALIDATION_PHASE2'],
            'operation' => ['required', 'in:REGENERATE_SOURCE,REGENERATE_TRANSLATION'],
            'cognitive_type' => ['nullable', 'string', 'max:64'],
            'language_code' => ['nullable', 'in:fr,es,de,it,pt,ru,zh,ar,el'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'expected_source_revision' => ['nullable', 'string', 'size:64'],
            'expected_translation_revision' => ['nullable', 'integer', 'min:0'],
            'expected_manual_revision' => ['nullable', 'integer', 'min:0'],
            'expected_yellow_revision' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->runAction($request, $copyId, fn (?string $claim) => $this->service->requestRegeneration(
            $copyId, (int) $data['copy_version'], $data['owner_phase'], $data['operation'],
            $data['cognitive_type'] ?? null, $data['language_code'] ?? null,
            $data['idempotency_key'] ?? null, [], $claim,
            $data['expected_source_revision'] ?? null,
            $this->nullableInt($data['expected_translation_revision'] ?? null),
            $this->nullableInt($data['expected_manual_revision'] ?? null),
            $this->nullableInt($data['expected_yellow_revision'] ?? null),
        ));
    }

    public function ready(Request $request, string $copyId): RedirectResponse
    {
        $data = $request->validate(['copy_version' => ['required', 'integer', 'min:1']]);
        return $this->runAction($request, $copyId, fn (?string $claim) => $this->service->enqueue(
            $copyId, (int) $data['copy_version'],
        ));
    }

    public function returnToOwner(Request $request, string $copyId): RedirectResponse
    {
        $data = $request->validate([
            'copy_version' => ['required', 'integer', 'min:1'],
            'owner_phase' => ['required', 'in:PHASE1,VALIDATION_PHASE1,PHASE2,VALIDATION_PHASE2'],
            'operation' => ['nullable', 'in:RESUME_PHASE1,RESUME_VALIDATION_PHASE1,RESUME_PHASE2,RESUME_VALIDATION_PHASE2'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);
        return $this->runAction($request, $copyId, function (?string $claim) use ($copyId, $data): array {
            if ($claim === null) {
                throw new LogicException('La copie doit être prise en charge avant son retour.');
            }
            return $this->service->returnToOwner(
                $copyId, (int) $data['copy_version'], $claim, $data['owner_phase'],
                $data['operation'] ?? 'RESUME_PHASE1', $data['idempotency_key'] ?? null,
            );
        });
    }

    private function validateCorrection(Request $request): array
    {
        return $request->validate([
            'copy_version' => ['required', 'integer', 'min:1'],
            'cognitive_type' => ['required', 'string', 'max:64'],
            'manual_revision' => ['required', 'integer', 'min:0'],
            'canonical_base_revision' => ['nullable', 'integer', 'min:0'],
            'source' => ['required_without:translation', 'array'],
            'translation' => ['required_without:source', 'array'],
        ]);
    }

    private function validatePayload(mixed $payload, string $key): array
    {
        return Validator::make([$key => $payload], [
            $key => ['required', 'array', 'max:32'],
        ])->validate()[$key];
    }

    private function runAction(Request $request, string $copyId, callable $action): RedirectResponse
    {
        try {
            $action($request->session()->get(self::CLAIM_SESSION_PREFIX . $copyId));
            return back()->with('status', 'Action Quarantaine effectuée.');
        } catch (LogicException $exception) {
            return $this->conflict($exception->getMessage());
        }
    }

    private function conflict(string $message): RedirectResponse
    {
        return back()->withErrors(['quarantine' => $message], 'quarantine')->setStatusCode(Response::HTTP_CONFLICT);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}