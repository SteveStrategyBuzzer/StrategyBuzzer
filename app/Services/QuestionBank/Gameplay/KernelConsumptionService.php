<?php

namespace App\Services\QuestionBank\Gameplay;

use App\Models\PlayerKernelCognitiveUsage;
use Illuminate\Database\QueryException;

/**
 * KernelConsumptionService
 *
 * Écrit la consommation cognitive d'un noyau pour chaque joueur EXPOSÉ.
 * Source de vérité pour la mémoire gameplay durable (table
 * player_kernel_cognitive_usage), lue ensuite par le MAP builder (S2).
 *
 * Idempotent : la clé métier est (user_id, kernel_code, cognitive_family,
 * cognitive_form) — voir UNIQUE 'pkcu_unique'. Rejouer le même signal de
 * consommation ne crée jamais de doublon ; consumed_at reste la 1ère fois.
 *
 * Aucune lecture READY_BANK ici (pas de notion de back_support à ce niveau) :
 * ce service ne fait QUE persister un fait d'exposition. La dérivation
 * vierge/touché/back_support appartient au MAP builder (S2).
 */
class KernelConsumptionService
{
    /** Familles cognitives canoniques (cf. KernelFrameValidator::VARIANT_META). */
    public const FAMILIES = ['recognition', 'reasoning', 'deceptive_trap'];

    /** Formes cognitives canoniques (cf. migration player_kernel_cognitive_usage). */
    public const FORMS = ['qcm', 'tf_true', 'tf_false', 'trap'];

    /**
     * Les 7 cognitifs officiels d'un noyau : variant_key → [famille, forme].
     * Miroir 1:1 de KernelFrameValidator::VARIANT_META, projeté sur le couple
     * (cognitive_family, cognitive_form) stocké en base.
     */
    public const VARIANT_TO_PAIR = [
        'qcm_recognition'      => ['recognition',    'qcm'],
        'qcm_reasoning'        => ['reasoning',      'qcm'],
        'qcm_deceptive_trap'   => ['deceptive_trap', 'trap'],
        'tf_recognition_true'  => ['recognition',    'tf_true'],
        'tf_recognition_false' => ['recognition',    'tf_false'],
        'tf_reasoning_true'    => ['reasoning',      'tf_true'],
        'tf_reasoning_false'   => ['reasoning',      'tf_false'],
    ];

    /**
     * Couples (famille, forme) autorisés — exactement les 7 cognitifs d'un noyau.
     * Garde-fou contre des combinaisons impossibles (ex. deceptive_trap + qcm).
     */
    private const ALLOWED_PAIRS = [
        'recognition|qcm',
        'reasoning|qcm',
        'deceptive_trap|trap',
        'recognition|tf_true',
        'recognition|tf_false',
        'reasoning|tf_true',
        'reasoning|tf_false',
    ];

    /**
     * Résout (famille, forme) depuis un variant_key officiel.
     * @return array{0:string,1:string}|null
     */
    public static function pairFromVariantKey(string $variantKey): ?array
    {
        return self::VARIANT_TO_PAIR[$variantKey] ?? null;
    }

    /**
     * Couple (famille, forme) valide ?
     */
    public static function isAllowedPair(string $family, string $form): bool
    {
        return in_array($family . '|' . $form, self::ALLOWED_PAIRS, true);
    }

    /**
     * Persiste la consommation d'UN cognitif de noyau pour une liste de joueurs exposés.
     *
     * @param  array{
     *     kernelCode:string,
     *     cognitiveFamily:string,
     *     cognitiveForm:string,
     *     depth:int,
     *     domain:string,
     *     matchRef:string,
     *     mode:string,
     *     exposedUserIds:array<int>,
     *     questionIntentId?:int|null
     *   } $payload
     * @return array{created:int,existing:int,users:int}
     *
     * @throws \InvalidArgumentException si le couple (famille, forme) est invalide.
     */
    public function consume(array $payload): array
    {
        $kernelCode = (string) ($payload['kernelCode'] ?? '');
        $family     = (string) ($payload['cognitiveFamily'] ?? '');
        $form       = (string) ($payload['cognitiveForm'] ?? '');
        $depth      = (int) ($payload['depth'] ?? 0);
        $domain     = (string) ($payload['domain'] ?? '');
        $matchRef   = (string) ($payload['matchRef'] ?? '');
        $mode       = (string) ($payload['mode'] ?? '');
        $intentId   = $payload['questionIntentId'] ?? null;
        $userIds    = array_values(array_unique(array_map('intval', $payload['exposedUserIds'] ?? [])));

        if ($kernelCode === '' || $domain === '' || $matchRef === '' || $depth < 1) {
            throw new \InvalidArgumentException('kernelCode, domain, matchRef et depth (>=1) sont requis.');
        }
        if (!self::isAllowedPair($family, $form)) {
            throw new \InvalidArgumentException("Couple cognitif invalide: {$family}/{$form}.");
        }
        if (empty($userIds)) {
            return ['created' => 0, 'existing' => 0, 'users' => 0];
        }

        $created  = 0;
        $existing = 0;

        // Pas de transaction englobante : chaque ligne (user × noyau × famille ×
        // forme) est un insert indépendant et idempotent. Sous Postgres, une
        // violation UNIQUE à l'intérieur d'une transaction la rendrait "aborted"
        // et bloquerait les inserts suivants ; on isole donc chaque save().
        foreach ($userIds as $userId) {
            if ($userId <= 0) {
                continue;
            }

            $row = PlayerKernelCognitiveUsage::firstOrNew([
                'user_id'          => $userId,
                'kernel_code'      => $kernelCode,
                'cognitive_family' => $family,
                'cognitive_form'   => $form,
            ]);

            if ($row->exists) {
                $existing++;
                continue;
            }

            $row->question_intent_id = $intentId !== null ? (int) $intentId : null;
            $row->depth              = $depth;
            $row->domain             = $domain;
            $row->match_ref          = $matchRef;
            $row->mode               = $mode;
            $row->consumed_at        = now();

            try {
                $row->save();
                $created++;
            } catch (QueryException $e) {
                // Course concurrente : un autre process a inséré la même clé
                // métier entre le firstOrNew et le save. La violation UNIQUE
                // (pkcu_unique) est traitée comme succès idempotent : la ligne
                // existe déjà, consumed_at = 1ère exposition reste intacte.
                if ($this->isUniqueViolation($e)) {
                    $existing++;
                    continue;
                }
                throw $e;
            }
        }

        return [
            'created'  => $created,
            'existing' => $existing,
            'users'    => count($userIds),
        ];
    }

    /**
     * La QueryException correspond-elle à une violation de contrainte UNIQUE ?
     * Couvre Postgres (SQLSTATE 23505) et SQLite (SQLSTATE 23000 / message).
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->getCode();
        if ($sqlState === '23505' || $sqlState === '23000') {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique constraint')
            || str_contains($message, 'unique violation')
            || str_contains($message, 'duplicate key')
            || str_contains($message, 'pkcu_unique');
    }
}
