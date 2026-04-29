<?php

namespace App\Services;

/**
 * Wrapper léger autour du planner pour les rares cas mono-question
 * (appels unitaires depuis QuestionService::generateQuestion).
 *
 * Utilise un plan "1 question / 1 manche" pour bénéficier de toute la
 * machinerie de sélection (depth, cognitive_type, domain, sub_domain,
 * fallback chain, anti-clone par concept_id).
 */
class QuestionBankPicker
{
    private MatchQuestionPlanner $planner;

    public function __construct(?MatchQuestionPlanner $planner = null)
    {
        $this->planner = $planner ?? new MatchQuestionPlanner();
    }

    /**
     * Pioche UNE question dans la banque pour (mode, level/division, language).
     * Retourne null si la banque est vide pour ce segment — au caller de
     * décider du fallback (cache Redis legacy, seed pool, ou erreur).
     *
     * @return array|null  format pipeline (question_text, answers, correct_index, …)
     */
    public function pickOne(
        string $mode,
        $levelOrDivision,
        string $language,
        array $extra = []
    ): ?array {
        try {
            $plan = $this->planner->buildPlan(
                $mode,
                $levelOrDivision,
                1, // total
                1, // rounds
                $language,
                $extra
            );
        } catch (\Throwable $e) {
            return null;
        }

        $first = $plan['ordered_questions'][0] ?? null;
        if (!$first || !empty($first['shortage'])) {
            return null;
        }

        // Si la banque n'a rien pour ce segment, le planner peut renvoyer un
        // seed (from_seed=true). Pour le picker mono-question, on considère
        // que c'est l'affaire du caller : il préférera son chemin AI/cache
        // historique plutôt que servir un seed sans contexte. On ne retourne
        // donc QUE les hits banque.
        if (empty($first['group_id'])) {
            return null;
        }

        return $first;
    }
}
