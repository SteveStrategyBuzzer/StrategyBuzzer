<?php

namespace App\Services;

use App\Models\QuestionGroup;
use App\Models\QuestionTranslation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Encapsule toutes les lectures/écritures de la banque centrale (Postgres).
 * Pas de cache : la couche cache est gérée plus haut (QuestionCacheService
 * pour la chaleur Redis, et le picker / planner construisent leur propre
 * fenêtre en mémoire pendant une sélection).
 */
class QuestionBankRepository
{
    /**
     * Recherche de candidats par filtres riches.
     *
     * @param array{
     *   difficulty_level?: int|null,
     *   boss_level?: int|null,
     *   depth_min?: int|null,
     *   depth_max?: int|null,
     *   domain?: string|null,
     *   sub_domain?: string|null,
     *   sub_domains?: array<int,string>|null,
     *   cognitive_type?: string|null,
     *   cognitive_types?: array<int,string>|null,
     *   question_type?: string|null,
     *   excluded_concept_ids?: array<int,string>|null,
     *   excluded_concept_families?: array<int,string>|null,
     *   excluded_group_ids?: array<int,int>|null,
     *   validated_only?: bool,
     *   language?: string|null,
     *   limit?: int|null,
     * } $filters
     */
    public function findCandidates(array $filters): Collection
    {
        $q = QuestionGroup::query();

        if (!empty($filters['difficulty_level'])) {
            $q->where('difficulty_level', (int) $filters['difficulty_level']);
        }
        if (!empty($filters['boss_level'])) {
            $q->where('boss_level', (int) $filters['boss_level']);
        }
        if (isset($filters['depth_min'])) {
            $q->where('difficulty_depth', '>=', (int) $filters['depth_min']);
        }
        if (isset($filters['depth_max'])) {
            $q->where('difficulty_depth', '<=', (int) $filters['depth_max']);
        }
        if (!empty($filters['domain'])) {
            $q->where('domain', $filters['domain']);
        }
        if (!empty($filters['sub_domain'])) {
            $q->where('sub_domain', $filters['sub_domain']);
        }
        if (!empty($filters['sub_domains'])) {
            $q->whereIn('sub_domain', $filters['sub_domains']);
        }
        if (!empty($filters['cognitive_type'])) {
            $q->where('cognitive_type', $filters['cognitive_type']);
        }
        if (!empty($filters['cognitive_types'])) {
            $q->whereIn('cognitive_type', $filters['cognitive_types']);
        }
        if (!empty($filters['question_type'])) {
            $q->where('question_type', $filters['question_type']);
        }
        if (!empty($filters['excluded_concept_ids'])) {
            $q->whereNotIn('concept_id', $filters['excluded_concept_ids']);
        }
        if (!empty($filters['excluded_concept_families'])) {
            $q->whereNotIn('concept_family', $filters['excluded_concept_families']);
        }
        if (!empty($filters['excluded_group_ids'])) {
            $q->whereNotIn('id', $filters['excluded_group_ids']);
        }
        if (!empty($filters['validated_only'])) {
            $q->where('validated', true);
        }
        if (!empty($filters['language'])) {
            // S'assurer qu'au moins une traduction existe dans la langue demandée
            // OU dans la chaîne de fallback. Ici on impose juste la présence
            // d'une traduction dans LA langue demandée — le fallback est
            // appliqué au moment du chargement du texte si absent.
            $q->whereHas('translations', function ($sub) use ($filters) {
                $sub->where('language', $filters['language']);
            });
        }

        // Tri stable : favoriser les questions les moins utilisées en premier.
        $q->orderBy('usage_count')->inRandomOrder();

        if (!empty($filters['limit'])) {
            $q->limit((int) $filters['limit']);
        }

        return $q->get();
    }

    /**
     * Insère un question_group + ses traductions, atomique. Dédoublonne par
     * concept_id : si déjà présent, ne ré-insère rien et retourne le groupe
     * existant.
     *
     * @param array $groupAttrs       attributs du QuestionGroup
     * @param array<string,array> $translations  language => attrs
     */
    public function upsertGroupWithTranslations(array $groupAttrs, array $translations): QuestionGroup
    {
        return DB::transaction(function () use ($groupAttrs, $translations) {
            $existing = QuestionGroup::where('concept_id', $groupAttrs['concept_id'])->first();
            if ($existing) {
                Log::debug('[QuestionBankRepository] concept_id already present, skipping insert', [
                    'concept_id' => $groupAttrs['concept_id'],
                    'group_id'   => $existing->id,
                ]);
                return $existing;
            }

            $group = QuestionGroup::create($groupAttrs);

            foreach ($translations as $language => $attrs) {
                $attrs['question_group_id'] = $group->id;
                $attrs['language'] = $language;
                QuestionTranslation::create($attrs);
            }

            return $group;
        });
    }

    /**
     * Incrémente usage_count et met à jour last_used_at. Conçu pour être
     * appelé en asynchrone par MarkQuestionGroupUsedJob — l'écriture est
     * minuscule.
     */
    public function markUsed(int $groupId): void
    {
        QuestionGroup::where('id', $groupId)->update([
            'usage_count'  => DB::raw('usage_count + 1'),
            'last_used_at' => now(),
        ]);
    }

    /**
     * Stats de profondeur : combien de question_groups dans chaque tuple
     * (language, level/boss, domain, sub_domain, cognitive_type, depth,
     * question_type). Utilisé par la commande questions:bank:stats.
     *
     * @return array<int,array<string,mixed>>
     */
    public function depthStats(): array
    {
        return DB::table('question_groups as qg')
            ->join('question_translations as qt', 'qt.question_group_id', '=', 'qg.id')
            ->selectRaw('
                qt.language,
                qg.difficulty_level,
                qg.boss_level,
                qg.domain,
                qg.sub_domain,
                qg.cognitive_type,
                qg.difficulty_depth,
                qg.question_type,
                COUNT(*) as cnt
            ')
            ->groupBy(
                'qt.language',
                'qg.difficulty_level',
                'qg.boss_level',
                'qg.domain',
                'qg.sub_domain',
                'qg.cognitive_type',
                'qg.difficulty_depth',
                'qg.question_type'
            )
            ->orderBy('qt.language')
            ->orderBy('qg.boss_level')
            ->orderBy('qg.difficulty_level')
            ->orderBy('qg.domain')
            ->orderBy('qg.sub_domain')
            ->orderBy('qg.cognitive_type')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
