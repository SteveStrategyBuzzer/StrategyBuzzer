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
    'general_sub_domain_weights' => [
        'Histoire'   => 14,
        'Sport'      => 12,
        'Géographie' => 14,
        'Art'        => 10,
        'Cuisine'    => 10,
        'Science'    => 14,
        'Cinéma'     => 12,
        'Faune'      => 14,
    ],

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
            'novice'        => ['type' => 'solo_range', 'levels' => [1,  39], 'depth_range' => [3, 6]],
            'intermediaire' => ['type' => 'solo_range', 'levels' => [21, 59], 'depth_range' => [5, 7]],
            'expert'        => ['type' => 'solo_range', 'levels' => [40, 79], 'depth_range' => [6, 9]],
        ],
        'mj_auto' => [
            'novice'        => ['type' => 'solo_range', 'levels' => [1,  39], 'depth_range' => [3, 6]],
            'intermediaire' => ['type' => 'solo_range', 'levels' => [21, 59], 'depth_range' => [5, 7]],
            'expert'        => ['type' => 'solo_range', 'levels' => [40, 79], 'depth_range' => [6, 9]],
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
        'backoff_max_seconds'    => 300,
        'seg_reject_threshold'   => 10,    // consecutive guard rejections before cooldown
        'seg_cooldown_seconds'   => 1800,  // 30 minutes

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

            // Per-language overrides for saviez_vous minimum length.
            // CJK (zh) and Arabic (ar) scripts are semantically denser per
            // character — 20 ideographs carry as much information as 40 Latin
            // characters. Languages absent from this map fall back to the
            // global saviez_vous_min_length above.
            'saviez_vous_min_length_by_lang' => [
                'zh' => 20,
                'ar' => 20,
            ],

            // ── PATCH GROUP QUALITÉ CONTENU ─────────────────────────────────

            // Guard 11 — question_text too long.
            // QUESTION_ACTIVE phase = 8 s in ALL game modes (Duo, Solo, Master,
            // League) — confirmed in packages/shared/src/types.ts
            // DEFAULT_TIMERS + DEFAULT_DUO_TIMERS (questionActive: 8000 ms).
            // At 15 chars/s average mobile reading speed with a 0.9 comfort
            // margin: 8 × 15 × 0.9 ≈ 108 → rounded to 110 chars.
            // CJK/Arabic scripts are semantically denser per character
            // (more meaning per glyph) → tighter absolute caps for those scripts.
            'question_text_max_length'         => 110,
            'question_text_max_length_by_lang' => ['zh' => 60, 'ar' => 75],

            // Guard 12 — answer choice too long.
            // ANSWER_SELECTION phase = 10 s for 4 choices on a ~375 px screen.
            // 60 chars per choice ≈ 3 readable lines; beyond that the button
            // overflows and fast scanning becomes impossible under time pressure.
            'answer_max_length'         => 60,
            'answer_max_length_by_lang' => ['zh' => 30, 'ar' => 40],

            // Guard 13 — saviez_vous too long.
            // The RESULT screen shows saviez_vous for ~4 s before auto-advancing.
            // 220 chars is ~1 500 ms at average reading speed — a hard upper cap.
            'saviez_vous_max_length'         => 220,
            'saviez_vous_max_length_by_lang' => ['zh' => 100, 'ar' => 140],

            // Guard 14 — negative / ambiguous question framing.
            // "Lequel n'est PAS …", "Sauf …", "Aucun de ces …" questions are
            // cognitively expensive under time pressure and generate systematic
            // false-positive correct answers. Detected via keyword scan on FR.
            'negative_framing_keywords' => [
                "n'est pas", "ne sont pas", "ne fut pas", "ne peut pas",
                "ne doit pas", "n'a pas", "n'était pas", "jamais",
                " sauf ", " excepté ", " hormis ", " à l'exception",
                "aucun de ces", "aucune de ces", "lequel ne",
                "laquelle ne", "lesquels ne",
            ],

            // ── END PATCH GROUP QUALITÉ CONTENU ─────────────────────────────

            // Reject when concept_family already accounts for more than this
            // share of a single segment (capped before insertion).
            'concept_family_segment_max_share' => 0.40,

            // ── Entropy guards (E1 / E2 / E3) ───────────────────────────────
            // Replaces the old flat correct_answer_text_max_freq=12 cap that
            // produced ~16 false positives per 1 true positive (Chine, Indonésie,
            // numbers blocked; Barry Lyndon ×7, David ×4/path, Hopper ×5 missed).
            //
            // E1 — path-level cap (answer × concept_family × cognitive_type).
            //      Same fact asked the same way ≥ N times = cognitive redundancy.
            //      Applies to all QCM incl. generic answers (numbers, short strings).
            //      Calibration: 95.7% of bank paths have count=1 → virtually no
            //      interference; only 1.1% of paths (32/2999) are pathological.
            'correct_answer_path_max_freq'    => 2,
            //
            // E2 — family concentration ratio.
            //      If total ≥ min_count AND (distinct_families / total) < ratio → reject.
            //      Catches Manet (2/14=14%), Edward Hopper (1/6=17%).
            //      Skipped for pure numbers (regex ^\d+$) and answers ≤ 3 chars:
            //      their family distribution is structurally artificial (each sport
            //      rule = its own family) and does not reflect cognitive clustering.
            'correct_answer_family_min_ratio' => 0.25,
            'correct_answer_family_min_count' => 6,
            //
            // E3 — soft global alert (non-blocking, monitoring / human review only).
            //      Logs a Laravel warning when total ≥ threshold.
            //      Calibration: only Chine (37) and Indonésie (30) trigger today —
            //      both legitimately diverse (65% and 57% family coverage).
            'correct_answer_soft_alert_freq'  => 30,
        ],

        // Redis keys (single source of truth so health endpoint can read them).
        'redis_keys' => [
            'semaphore' => 'qb:worker:lock',
            'rate_bucket' => 'qb:worker:rate:%s', // sprintf with minute window
            'last_success' => 'qb:worker:last_success',
            'last_rejects' => 'qb:worker:last_rejects', // LIST capped to 25
            'gen_counter_ok' => 'qb:worker:gen:ok:%s', // sprintf with minute window
            'gen_counter_err' => 'qb:worker:gen:err:%s',
            // Bank-dry counters (sprintf with floor(time()/60) for per-minute
            // buckets summed into a rolling 1h figure by the health endpoint).
            'dry_fallback_counter' => 'qb:dry:fallback:%s',
            'dry_total_counter' => 'qb:dry:total:%s',
            'dry_last_event' => 'qb:dry:last_event',
            // Per-minute hashes (one HASH per minute bucket, field=label,
            // value=count) so the rolling 1h count summed across the last
            // 60 buckets is exact, not cumulative-since-TTL. Last-seen
            // ZSETs (score=ts, member=label) drive the "top offender" list
            // and bound which labels we sum over.
            'dry_total_segment_counts' => 'qb:dry:total:seg:counts:%d',
            'dry_total_segment_seen' => 'qb:dry:total:seg:seen',
            'dry_fallback_segment_counts' => 'qb:dry:fallback:seg:counts:%d',
            'dry_fallback_segment_seen' => 'qb:dry:fallback:seg:seen',
            // Last CRITICAL-only event marker — separate from dry_last_event
            // (which is the most recent event of any severity) so a degraded
            // fallback after a critical dry doesn't shift the "last critical
            // dry" timestamp displayed on the health endpoint.
            'dry_last_critical_event' => 'qb:dry:last_critical_event',
            // Cooldown marker (JSON blob) so a sustained outage produces
            // one alert per cooldown window, not a flood.
            'dry_last_alert' => 'qb:dry:last_alert',
            // PagerDuty incident-open marker (JSON blob with dedup_key + ts).
            // Presence means an incident has been triggered and not yet
            // resolved; the alerter clears it on a successful resolve.
            'dry_pagerduty_open' => 'qb:dry:pagerduty_open',
            // Auto-remediation hooks (#99). The worker reads
            // rate_override and priority_segment to bump throughput and
            // attack the affected segment first; the health endpoint
            // reads dry_last_self_heal to surface what was done.
            'rate_override' => 'qb:worker:rate_override',
            'priority_segment'  => 'qb:worker:priority_segment',
            'seg_reject_count'  => 'qb:worker:seg_rejects:%s',    // sprintf with segment hash
            'seg_cooldown'      => 'qb:worker:seg_cooldown:%s',   // sprintf with segment hash
            'dry_last_self_heal' => 'qb:dry:last_self_heal',
        ],

        // Ops alert thresholds for bank-dry CRITICAL events. The alerter
        // is a no-op for any channel left unset; all three unset = disabled.
        'dry_alert' => [
            'threshold' => (int) env('QB_DRY_ALERT_THRESHOLD', 5),
            'window_minutes' => (int) env('QB_DRY_ALERT_WINDOW_MINUTES', 10),
            'cooldown_minutes' => (int) env('QB_DRY_ALERT_COOLDOWN_MINUTES', 30),
            'slack_webhook_url' => env('QB_DRY_ALERT_SLACK_WEBHOOK_URL', ''),
            'email_recipient' => env('QB_DRY_ALERT_EMAIL', ''),
            // PagerDuty Events API v2 routing (integration) key. Unset = disabled.
            // When set, threshold breach opens an incident with a stable
            // dedup_key, and the alerter resolves it once the rolling
            // CRITICAL count over `window_minutes` falls back to 0.
            'pagerduty_routing_key' => env('QB_DRY_ALERT_PAGERDUTY_ROUTING_KEY', ''),
            // Endpoint override for tests; production should leave this
            // empty so the canonical PagerDuty Events v2 URL is used.
            'pagerduty_endpoint' => env('QB_DRY_ALERT_PAGERDUTY_ENDPOINT', ''),
            'environment_label' => env('APP_ENV', 'unknown'),
        ],

        /*
        | Auto-remediation hook (#99). When enabled, a CRITICAL bank-dry
        | breach triggers a self-heal cycle that bumps the worker's
        | per-minute budget for `boost_minutes`, force-flushes the
        | current rate bucket, and pins a priority segment so the worker
        | refills the affected tuple first. All effects are TTL-bounded
        | so a misfire automatically self-recovers. Default OFF — flip
        | `QB_DRY_AUTOREMEDIATE_ENABLED=true` once dry-run looks sane.
        */
        'dry_autoremediate' => [
            'enabled' => filter_var(env('QB_DRY_AUTOREMEDIATE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            'boost_minutes' => (int) env('QB_DRY_AUTOREMEDIATE_BOOST_MINUTES', 10),
            'boost_rate_per_minute' => (int) env('QB_DRY_AUTOREMEDIATE_BOOST_RATE', 30),
        ],
    ],

    /*
     * Domaines canoniques. Tous les autres champs "domain" passés au planner
     * doivent appartenir à cette liste pour être bank-able.
     */
    'domains' => [
        'general',
        'histoire',
        'sport',
        'geographie',
        'art',
        'cuisine',
        'science',
        'cinema',
        'faune',
    ],

    /*
     * Ordre stable utilisé par QuotaAllocator pour départager les fractions
     * résiduelles égales lors du largest-remainder. L'ordre est :
     * recognition > deceptive_trap > reasoning.
     */
    'stable_tiebreak_order' => [
        'recognition',
        'deceptive_trap',
        'reasoning',
    ],

    /*
     * Tolérance autorisée par cognitive_type au global ET par manche, en
     * questions. ±1 = la composition réelle peut s'écarter de 1 unité de la
     * cible théorique sur chaque cognitive_type.
     */
    'composition_tolerance' => 1,

    /*
     * Langues de fallback si une traduction manque pour un question_group.
     * On essaie le français d'abord, puis l'anglais.
     */
    'translation_fallback_chain' => ['fr', 'en'],
];

