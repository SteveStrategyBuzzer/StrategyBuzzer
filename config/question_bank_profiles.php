<?php

/*
|--------------------------------------------------------------------------
| Question Bank Profiles
|--------------------------------------------------------------------------
|
| Authoritative source for the StrategyBuzzer Difficulty Blueprint:
|   - Cognitive types & their stable priority order (used by QuotaAllocator
|     for ties in largest-remainder allocation).
|   - Solo student bands with depth ranges (no orphan level between 1-99).
|   - Boss profiles (depth + cognitive mix as composition quotas).
|   - Mode mappings (Duo, MJ auto, Ligue → Solo levels / Boss tiers).
|   - The 8 sub-domains for "Général" and their default weights.
|   - Depth rubric (qualitative description), shared with the worker (#82)
|     so it can inject the right rubric in its generation prompts.
|
| Cognitive percentages are MATCH-COMPOSITION QUOTAS, not per-question
| probabilities. The MatchQuestionPlanner converts them to integer counts
| via largest-remainder allocation.
|
*/

return [

    'cognitive_types' => ['recognition', 'reasoning', 'deceptive_trap'],

    /*
    | Stable order used by QuotaAllocator to break ties in largest-remainder
    | allocation. The first type in this list wins ties first.
    */
    'cognitive_priority_order' => ['recognition', 'deceptive_trap', 'reasoning'],

    'languages' => ['fr', 'en', 'es', 'it', 'de', 'pt', 'ru', 'zh', 'ar', 'el'],

    /*
    | The 8 official sub-domains. "Général" is NOT a stored domain — it is
    | an orchestrator that draws from these. The planner balances picks
    | across them and avoids consecutive repetition of the same sub-domain.
    */
    'general_sub_domains' => [
        'Histoire', 'Sport', 'Géographie', 'Art',
        'Cuisine', 'Science', 'Cinéma', 'Faune',
    ],

    /*
    | Default weights for "Général" sub-domain distribution. 'equal' means
    | each of the 8 sub-domains gets 1/8 of the total. Override with an
    | associative array { 'Histoire' => 2, 'Sport' => 1, ... } if needed.
    */
    'general_sub_domain_weights' => 'equal',

    /*
    | Qualitative rubric per depth band. The worker (#82) injects this into
    | its generation prompts so the IA produces content of the right tier.
    */
    'depth_rubric' => [
        '3-4' => 'accessible mais pas bébé ; distracteurs plausibles ; aucune question trop évidente',
        '5-6' => 'intermédiaire ; vraie connaissance ou petite déduction ; réponse non évidente',
        '7-8' => 'avancé ; comparaison, élimination ou raisonnement solide ; distracteurs subtils',
        '9-10' => 'élite ; très difficile mais répondable ; connaissance avancée ; pièges crédibles ; aucune réponse évidente',
    ],

    /*
    | Constant cognitive mix for all Solo student levels (1-99 except Boss).
    */
    'student_cognitive_mix' => [
        'recognition' => 50,
        'reasoning' => 20,
        'deceptive_trap' => 30,
    ],

    /*
    | Solo student bands. Every Solo level 1-99 (except Boss multiples of 10)
    | maps to exactly one band — no orphan level. Levels 10/20/30/40/60/70/90/100
    | are Boss and use boss_profiles. Levels 50 and 80 fall into 41-69 and 71-99.
    */
    'student_bands' => [
        ['levels' => [1, 9],   'depth_range' => [3, 4]],
        ['levels' => [11, 19], 'depth_range' => [4, 5]],
        ['levels' => [21, 39], 'depth_range' => [5, 6]],
        ['levels' => [40, 40], 'depth_range' => [6, 7]],
        ['levels' => [41, 69], 'depth_range' => [7, 8]],
        ['levels' => [70, 70], 'depth_range' => [8, 9]],
        ['levels' => [71, 99], 'depth_range' => [9, 10]],
    ],

    /*
    | Official Boss profiles. Each Boss has a fixed depth and a cognitive mix
    | which is interpreted as a match-composition quota.
    */
    'boss_profiles' => [
        10  => ['depth' => 6,  'mix' => ['recognition' => 40, 'reasoning' => 20, 'deceptive_trap' => 40]],
        20  => ['depth' => 7,  'mix' => ['recognition' => 40, 'reasoning' => 30, 'deceptive_trap' => 30]],
        30  => ['depth' => 7,  'mix' => ['recognition' => 40, 'reasoning' => 30, 'deceptive_trap' => 30]],
        40  => ['depth' => 8,  'mix' => ['recognition' => 50, 'reasoning' => 30, 'deceptive_trap' => 20]],
        60  => ['depth' => 8,  'mix' => ['recognition' => 50, 'reasoning' => 30, 'deceptive_trap' => 20]],
        70  => ['depth' => 9,  'mix' => ['recognition' => 60, 'reasoning' => 30, 'deceptive_trap' => 10]],
        90  => ['depth' => 9,  'mix' => ['recognition' => 60, 'reasoning' => 30, 'deceptive_trap' => 10]],
        100 => ['depth' => 10, 'mix' => ['recognition' => 55, 'reasoning' => 30, 'deceptive_trap' => 15]],
    ],

    /*
    | Mode mappings. Each mode/division resolves to either:
    |   - ['type' => 'solo_range', 'levels' => [from, to]]  → student profile
    |   - ['type' => 'boss',       'level'  => 10|20|...]    → boss profile
    */
    'mode_mappings' => [
        'duo' => [
            'novice'        => ['type' => 'solo_range', 'levels' => [31, 39]],
            'intermediaire' => ['type' => 'solo_range', 'levels' => [51, 59]],
            'expert'        => ['type' => 'solo_range', 'levels' => [71, 79]],
        ],
        'mj_auto' => [
            'novice'        => ['type' => 'solo_range', 'levels' => [31, 39]],
            'intermediaire' => ['type' => 'solo_range', 'levels' => [51, 59]],
            'expert'        => ['type' => 'solo_range', 'levels' => [71, 79]],
        ],
        'ligue' => [
            'bronze'  => ['type' => 'boss', 'level' => 10],
            'argent'  => ['type' => 'boss', 'level' => 30],
            'or'      => ['type' => 'boss', 'level' => 40],
            'platine' => ['type' => 'boss', 'level' => 60],
            'diamant' => ['type' => 'boss', 'level' => 70],
            'legende' => ['type' => 'boss', 'level' => 90],
        ],
    ],

    /*
    | Concept-family overuse threshold inside a single match plan. If a
    | concept_family already accounts for more than this fraction of the
    | match, additional groups from that family are deprioritised.
    */
    'concept_family_match_max_share' => 0.35,
];
