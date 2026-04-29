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

    /*
    |--------------------------------------------------------------------------
    | Continuous Bank Worker (#82)
    |--------------------------------------------------------------------------
    | Targets and safety rails for the long-running worker that keeps the
    | bank refilled by SEGMENT (level/Boss × depth × domain × sub_domain ×
    | cognitive_type × question_type × language).
    |
    | All knobs are env-overridable so dev / staging / prod can run at
    | different rhythms without code changes.
    */
    'worker' => [
        // How many distinct matches each profile must be able to build
        // without recycling within `recycle_days`. The needs calculator
        // multiplies by per-match cognitive quotas to derive segment depth.
        'target_matches_per_profile' => (int) env('QB_WORKER_TARGET_MATCHES', 10),

        // Number of days before a question may legitimately be re-served
        // for a profile. Higher values demand more groups in the bank.
        'recycle_days' => (int) env('QB_WORKER_RECYCLE_DAYS', 3),

        // Token-bucket rate limit. Worker generates at most N segments per
        // minute, regardless of upstream latency. Set to 0 to pause entirely.
        'rate_per_minute' => (int) env('QB_WORKER_RATE_PER_MINUTE', 6),

        // Sleep between cycles when nothing is needed (in seconds).
        'idle_sleep_seconds' => (int) env('QB_WORKER_IDLE_SLEEP', 60),

        // Bounded exponential back-off on upstream errors (429 / timeout / 5xx).
        'backoff_initial_seconds' => 5,
        'backoff_max_seconds' => 300,

        // Languages a generation must minimally produce to be accepted as
        // a valid group. Below this bar the row is rejected and retried.
        'min_required_languages' => ['fr', 'en'],

        // Languages the worker tries to fill in one shot. Real coverage may
        // be smaller; we still insert with `validated=false` whenever any of
        // these is missing, so the picker won't serve it for those languages.
        'preferred_languages' => ['fr', 'en', 'es', 'it', 'de', 'pt', 'ru', 'zh', 'ar', 'el'],

        // Quality guards.
        'guards' => [
            // A generated question_text whose normalised similarity (Jaccard
            // on token shingles) with any existing same-segment text exceeds
            // this is rejected as a clone / reformulation.
            'text_similarity_max' => 0.55,

            // Minimum length of saviez_vous (in characters, normalised). Below
            // this is treated as too weak / generic.
            'saviez_vous_min_length' => 30,

            // Reject when concept_family already accounts for more than this
            // share of a single segment (capped before insertion).
            'concept_family_segment_max_share' => 0.40,
        ],

        // Redis keys (single source of truth so health endpoint can read them).
        'redis_keys' => [
            'semaphore' => 'qb:worker:lock',
            'rate_bucket' => 'qb:worker:rate:%s', // sprintf with minute window
            'last_success' => 'qb:worker:last_success',
            'last_rejects' => 'qb:worker:last_rejects', // LIST capped to 25
            'gen_counter_ok' => 'qb:worker:gen:ok:%s', // sprintf with minute window
            'gen_counter_err' => 'qb:worker:gen:err:%s',
        ],
    ],
];
