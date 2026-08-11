<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * QuestionIntentEncoder — RACCORDEMENT A du flow canonique (2026-08-11).
 *
 * CRÉATION DU KERNELBLUEPRINT (avant KRP) → KRP → Taxonomy ↕ ValidationDominantIdeas
 *   → [ICI] QuestionIntent → Phase 1 → … → ReadyBank
 *
 * Rôle : encodeur PUR et PASSIF.
 *   - Encode le territoire du Blueprint engagé {subdomain_active, subject_active,
 *     dominant_idea_active} en UNE ligne question_intents.
 *   - Ne calcule rien, ne décide rien, ne valide rien : l'idée dominante arrive
 *     DÉJÀ validée par ValidationDominantIdeas (unique autorité).
 *   - Ne fabrique aucun hash métier (kernel_code / ks_hash / kld_hash hors de portée).
 *
 * Correspondance vers le vocabulaire legacy kernel_core (mapping d'identité,
 * exigé non-null par KernelFrameValidator — aucune fabrication de contenu) :
 *   angle_large / micro_angle / answer_target ← dominant_idea_active
 *     (l'idée dominante EST l'angle d'attaque et la cible de réponse du noyau)
 *   concept_family ← subdomain_active (famille = sous-domaine canonique)
 *   semantic_key / intent_key ← 'BP:' + blueprint_id (identification déterministe)
 *
 * Idempotence : blueprint_id est UNIQUE sur question_intents — ré-encoder le
 * même Blueprint retourne l'intent existant sans écrire.
 *
 * ⛔ KLD / KEY_STRUCTURE : SUPERSEDED — aucun appel, aucun gate, aucun adapter.
 */
final class QuestionIntentEncoder
{
    private const TABLE = 'question_intents';

    public const SOURCE = 'kernel_rotation';

    /**
     * Encode un Blueprint engagé en QuestionIntent (idempotent par blueprint_id).
     *
     * @return int id de la ligne question_intents (existante ou créée)
     *
     * @throws RuntimeException STOP si l'identité intellectuelle du Blueprint est incomplète.
     */
    public function encode(KernelBlueprint $blueprint): int
    {
        $this->assertEncodable($blueprint);

        $existing = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprint->blueprint_id)
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $key = 'BP:' . $blueprint->blueprint_id;

        return (int) DB::table(self::TABLE)->insertGetId([
            'intent_key'       => $key,
            'semantic_key'     => $key,
            'language_source'  => 'en',
            'domain'           => (string) $blueprint->domain,
            'sub_domain'       => (string) $blueprint->subdomain_active,
            'difficulty_depth' => (int) $blueprint->depth,
            'subject'          => (string) $blueprint->subject_active,
            'dominant_idea'    => (string) $blueprint->dominant_idea_active,
            'angle_large'      => (string) $blueprint->dominant_idea_active,
            'micro_angle'      => (string) $blueprint->dominant_idea_active,
            'answer_target'    => (string) $blueprint->dominant_idea_active,
            'concept_family'   => (string) $blueprint->subdomain_active,
            'source'           => self::SOURCE,
            'blueprint_id'     => (string) $blueprint->blueprint_id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /**
     * STOP explicite si un champ de l'identité intellectuelle manque —
     * jamais d'encodage partiel silencieux.
     */
    private function assertEncodable(KernelBlueprint $blueprint): void
    {
        $missing = [];

        if ($blueprint->blueprint_id === null || $blueprint->blueprint_id === '') {
            $missing[] = 'blueprint_id';
        }
        if ($blueprint->depth === null) {
            $missing[] = 'depth';
        }
        if ($blueprint->domain === null || $blueprint->domain === '') {
            $missing[] = 'domain';
        }
        if ($blueprint->subdomain_active === null || $blueprint->subdomain_active === '') {
            $missing[] = 'subdomain_active';
        }
        if ($blueprint->subject_active === null || $blueprint->subject_active === '') {
            $missing[] = 'subject_active';
        }
        if ($blueprint->dominant_idea_active === null || $blueprint->dominant_idea_active === '') {
            $missing[] = 'dominant_idea_active';
        }

        if ($missing !== []) {
            throw new RuntimeException(
                '[QuestionIntentEncoder] STOP — Blueprint non encodable, champs manquants : '
                . implode(', ', $missing) . '.'
            );
        }
    }
}
