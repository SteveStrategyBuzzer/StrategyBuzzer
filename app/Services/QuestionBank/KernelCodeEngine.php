<?php

declare(strict_types=1);

namespace App\Services\QuestionBank;

use App\Exceptions\QuestionBank\KernelCodeEngineException;
use Illuminate\Support\Facades\DB;

/**
 * KernelCodeEngine — propriétaire exclusif de l'écriture de kernel_code.
 *
 * ══════════════════════════════════════════════════════════════════════════════
 * MODULE : 05_QuestionIntent (VERROUILLÉ)
 * ══════════════════════════════════════════════════════════════════════════════
 *
 * Mission : recevoir le KernelBlueprint dont le territoire intellectuel a été
 * entièrement déterminé et validé, construire son kernel_code canonique selon
 * la structure officielle StrategyBuzzer, attribuer un suffixe séquentiel
 * unique dans le bassin (Depth + Domaine), écrire ce kernel_code dans le
 * KernelBlueprint et rendre cette identité immuable pour toute la durée de
 * vie du noyau canonique.
 *
 * ── Format ──────────────────────────────────────────────────────────────────
 *
 *   DD-DO-SUB-SUJ-IDE-VVVV   (23 caractères)
 *
 *   DD   = Depth, 2 chiffres zero-padded
 *   DO   = code Domaine, 3 caractères majuscules
 *   SUB  = 3 chars issus du sous-domaine (normalisés)
 *   SUJ  = 3 chars issus du sujet (normalisés)
 *   IDE  = 3 chars issus de l'idée dominante (normalisés)
 *   VVVV = suffixe séquentiel base36, 4 chars (0000..ZZZZ)
 *
 *   Regex : ^[0-9]{2}-[A-Z0-9]{3}-[A-Z0-9]{3}-[A-Z0-9]{3}-[A-Z0-9]{3}-[0-9A-Z]{4}$
 *
 * ── Invariants ───────────────────────────────────────────────────────────────
 *
 *   - 1 Blueprint → 0 ou 1 kernel_code (jamais plusieurs)
 *   - Idempotent : même Blueprint deux fois → même kernel_code, compteur avancé 1 seule fois
 *   - Immuable après attribution (NULL → valeur, jamais valeur → autre valeur)
 *   - 1 compteur indépendant par (Depth, domain_code)
 *   - Capacité : 36^4 = 1 679 616 par bassin
 *   - Aucun recyclage de suffixe consommé
 *
 * ── Interdictions absolues ───────────────────────────────────────────────────
 *
 *   ✗ ne choisit pas Depth, Domaine, Sous-domaine, Sujet, Idée Dominante
 *   ✗ n'appelle pas Gemini / OpenAI / Phase 1 / Quarantine / ReadyBank
 *   ✗ n'écrit pas ks_hash, kld_hash, intent_key, semantic_key
 *   ✗ ne réactive pas KLD / KEY_STRUCTURE / KernelIdentifierManager
 *   ✗ ne produit aucun contenu cognitif
 */
final class KernelCodeEngine
{
    // ── Tables ────────────────────────────────────────────────────────────────
    private const RUNS_TABLE = 'kernel_blueprint_runs';
    private const SEQ_TABLE  = 'kernel_code_sequences';

    // ── Format ────────────────────────────────────────────────────────────────
    public const FORMAT_REGEX = KernelCodeFormat::FORMAT_REGEX;
    public const CODE_LENGTH  = KernelCodeFormat::CODE_LENGTH;

    // ── Base36 ────────────────────────────────────────────────────────────────
    private const SUFFIX_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const  MAX_SUFFIX   = 1_679_615; // 36^4 - 1

    // ═════════════════════════════════════════════════════════════════════════
    // Point d'entrée public — allocation atomique du kernel_code
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Attribue et persiste le kernel_code du Blueprint canonique.
     *
     * Transaction unique : kernel_blueprint_runs + kernel_code_sequences
     * sont mis à jour dans la même frontière transactionnelle.
     *
     * @throws KernelCodeEngineException si une entrée est invalide ou le bassin épuisé.
     */
    public function assignKernelCode(KernelBlueprint $blueprint): string
    {
        return DB::transaction(function () use ($blueprint) {

            // ── 1. Verrouiller la ligne Blueprint ────────────────────────────
            $run = DB::table(self::RUNS_TABLE)
                ->where('blueprint_id', $blueprint->blueprint_id)
                ->lockForUpdate()
                ->first();

            if ($run === null) {
                throw new KernelCodeEngineException(
                    KernelCodeEngineException::MISSING_INPUT,
                    "Blueprint introuvable : {$blueprint->blueprint_id}"
                );
            }

            // ── 2. Vérifier la projection intellectuelle complète ───────────
            $prefix = $blueprint->kernelCodePrefix();
            if ($prefix === null) {
                throw new KernelCodeEngineException(
                    KernelCodeEngineException::MISSING_INPUT,
                    'Projection DD-DO-SUB-SUJ-IDE incomplète.'
                );
            }

            // ── 3. Idempotence ───────────────────────────────────────────────
            if ($run->kernel_code !== null) {
                $code = (string) $run->kernel_code;
                if (! preg_match(self::FORMAT_REGEX, $code)) {
                    throw new KernelCodeEngineException(
                        KernelCodeEngineException::EXISTING_CODE_INVALID,
                        "kernel_code persisté non conforme au format : {$code}"
                    );
                }
                if (! str_starts_with($code, $prefix . '-')) {
                    throw new KernelCodeEngineException(
                        KernelCodeEngineException::IDENTITY_CONFLICT,
                        "kernel_code persisté divergent de la projection du Blueprint : {$code}"
                    );
                }
                $blueprint->fillKernelCode($code);
                return $code;
            }

            // ── 4. Depth → DD pour le bassin et les erreurs ──────────────────
            $dd = $this->resolveDepth((int) $blueprint->depth);

            // ── 5. Domaine → DO (3 chars), bassin Depth + Domain ─────────────
            $domainCode = $this->resolveDomainCode((string) $blueprint->domain);

            // ── 6. Refuser un reset silencieux depuis un bassin legacy ───────
            $legacyDomainCode = KernelCodeFormat::legacyDomain((string) $blueprint->domain);
            $newSequenceExists = DB::table(self::SEQ_TABLE)
                ->where('depth', $blueprint->depth)
                ->where('domain_code', $domainCode)
                ->exists();

            if (! $newSequenceExists
                && $legacyDomainCode !== null
                && DB::table(self::SEQ_TABLE)
                    ->where('depth', $blueprint->depth)
                    ->where('domain_code', $legacyDomainCode)
                    ->exists()) {
                throw new KernelCodeEngineException(
                    KernelCodeEngineException::IDENTITY_CONFLICT,
                    "Bassin legacy {$dd}-{$legacyDomainCode} présent : réconciliation requise avant allocation {$dd}-{$domainCode}."
                );
            }

            // ── 7. Obtenir ou créer la ligne de séquence ─────────────────────
            DB::table(self::SEQ_TABLE)->insertOrIgnore([
                'depth'       => (int) $blueprint->depth,
                'domain_code' => $domainCode,
                'next_value'  => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $seq = DB::table(self::SEQ_TABLE)
                ->where('depth', $blueprint->depth)
                ->where('domain_code', $domainCode)
                ->lockForUpdate()
                ->first();

            // ── 8. Vérifier l'exhaustion du bassin ───────────────────────────
            if ((int) $seq->next_value > self::MAX_SUFFIX) {
                throw new KernelCodeEngineException(
                    KernelCodeEngineException::SUFFIX_EXHAUSTED,
                    "Bassin {$dd}-{$domainCode} épuisé (ZZZZ atteint, aucun suffixe restant)."
                );
            }

            // ── 9. Convertir en suffixe base36 4 chars ───────────────────────
            $suffix = $this->toBase36((int) $seq->next_value);

            // ── 10. Construire le kernel_code ────────────────────────────────
            $kernelCode = "{$prefix}-{$suffix}";

            // ── 11. Écrire dans kernel_blueprint_runs ────────────────────────
            $updated = DB::table(self::RUNS_TABLE)
                ->where('blueprint_id', $blueprint->blueprint_id)
                ->whereNull('kernel_code') // garde anti-race (double-check)
                ->update(['kernel_code' => $kernelCode, 'updated_at' => now()]);

            if ($updated === 0) {
                // Un autre worker a assigné le code entre notre lock et notre write.
                // Lire la valeur qui a été persistée et la retourner.
                $fresh = DB::table(self::RUNS_TABLE)
                    ->where('blueprint_id', $blueprint->blueprint_id)
                    ->value('kernel_code');

                if ($fresh === null || ! preg_match(self::FORMAT_REGEX, (string) $fresh)) {
                    throw new KernelCodeEngineException(
                        KernelCodeEngineException::IDENTITY_CONFLICT,
                        "Conflit d'identité détecté sur blueprint {$blueprint->blueprint_id}"
                    );
                }

                $blueprint->fillKernelCode((string) $fresh);
                return (string) $fresh;
            }

            // ── 12. Incrémenter next_value ───────────────────────────────────
            DB::table(self::SEQ_TABLE)
                ->where('depth', $blueprint->depth)
                ->where('domain_code', $domainCode)
                ->update(['next_value' => (int) $seq->next_value + 1, 'updated_at' => now()]);

            // ── 13. Remplir le Blueprint en mémoire ──────────────────────────
            $blueprint->fillKernelCode($kernelCode);

            return $kernelCode;
        });
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers publics — testables indépendamment
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Valide le Depth via DepthContractRegistry et retourne son code DD (2 chars).
     *
     * @throws KernelCodeEngineException INVALID_DEPTH si le Depth est inconnu.
     */
    public function resolveDepth(int $depth): string
    {
        return KernelCodeFormat::depth($depth);
    }

    /**
     * Résout le code Domaine 3 chars à partir du nom canonique.
     * Refuse explicitement "Général" (non domaine de création).
     *
     * @throws KernelCodeEngineException INVALID_DOMAIN si le domaine est inconnu.
     */
    public function resolveDomainCode(string $domain): string
    {
        return KernelCodeFormat::domain($domain);
    }

    /**
     * Normalise une valeur en un segment de 3 caractères [A-Z0-9].
     *
     * Pipeline :
     *   valeur canonique → NFD → retrait accents → UPPERCASE → A-Z0-9 → 3 premiers chars
     *
     * Padding : si < 3 chars après normalisation → right-pad avec 'X'.
     *
     * @throws KernelCodeEngineException INVALID_SEGMENT si la valeur est vide
     *                                   après normalisation complète.
     */
    public function normalizeSegment(string $value): string
    {
        return KernelCodeFormat::segment($value);
    }

    /**
     * Convertit un entier en suffixe base36 sur exactement 4 caractères.
     *
     * Alphabet : 0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ
     *   0    → "0000"
     *   9    → "0009"
     *   10   → "000A"
     *   35   → "000Z"
     *   36   → "0010"
     *   1295 → "00ZZ"
     *   1296 → "0100"
     * 1679615 → "ZZZZ"
     */
    public function toBase36(int $value): string
    {
        if ($value === 0) {
            return '0000';
        }

        $chars  = self::SUFFIX_CHARS;
        $result = '';
        $n      = $value;

        while ($n > 0) {
            $result = $chars[$n % 36] . $result;
            $n      = intdiv($n, 36);
        }

        return str_pad($result, 4, '0', STR_PAD_LEFT);
    }
}
