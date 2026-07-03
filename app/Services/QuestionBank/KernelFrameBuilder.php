<?php

namespace App\Services\QuestionBank;

use App\Services\QuestionBank\ReadingBandConfig;
use App\Models\QuestionIntent;

/**
 * KernelFrameBuilder
 *
 * Construit le Kernel Blueprint Frame complet — contenant vide du noyau mère.
 * Pure function — lit le QuestionIntent, retourne un array. N'écrit jamais en DB.
 *
 * ══ ARCHITECTURE NOYAU MÈRE (2026-07-03) ══════════════════════════════════════
 *
 * Le frame est organisé en deux couches :
 *
 *   1. BLUEPRINT FRAME (nouvelle architecture)
 *      Tous les slots du noyau mère avec leurs règles, mécanismes, contraintes,
 *      statuts et traces. Toutes les valeurs = null / [] à la construction.
 *      C'est le contenant. Rien n'est sélectionné ici.
 *
 *      Chaque slot expose le même contrat mécanique :
 *        created_by    — KernelFrameBuilder (toujours ce fichier)
 *        filled_by     — brique propriétaire (seule habilitée à écrire)
 *        read_by       — briques autorisées à lire
 *        write_access  — propriétaire unique d'écriture
 *        locked_after  — événement de verrouillage
 *        transmitted_to— brique(s) destinataire(s) après remplissage
 *        forbidden     — ce qui est explicitement interdit
 *        expected_content — ce que le slot doit contenir une fois rempli
 *        status_initial— valeur de status à la construction
 *        traces        — historique append-only des décisions sur ce slot
 *
 *   2. KERNEL CORE LEGACY (conservé — compatibilité pipeline existant)
 *      kernel_core, translation_constraints, variants
 *      Utilisés par KernelFrameValidator, KernelContentBuilder, QualityGuards.
 *      Ne pas supprimer tant que la migration vers cognitive_slots n'est pas complète.
 *
 * ══ RÈGLE FONDAMENTALE ════════════════════════════════════════════════════════
 *
 * KernelFrameBuilder NE choisit rien :
 *   - Pas de sélection Depth
 *   - Pas de sélection Domaine
 *   - Pas de génération de sujet
 *   - Pas de génération d'idée dominante
 *   - Pas de génération de question
 *
 * Il crée uniquement la structure vide + les règles de remplissage.
 * Sans ce frame, KernelRotationPlanner n'a rien à taguer.
 */
class KernelFrameBuilder
{
    // ─── Langues de traduction (9 — sans 'en' qui est la source) ────────────
    private const TRANSLATION_LANGS = ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];

    // ─── Caps réponses / saviez-vous ─────────────────────────────────────────
    private const A_MAX     = 60;
    private const A_MAX_ZH  = 30;
    private const A_MAX_AR  = 40;
    private const SV_MAX    = 220;
    private const SV_MAX_ZH = 100;
    private const SV_MAX_AR = 140;
    private const SV_MIN    = 30;

    // ─── 7 variants : [question_type, cognitive_type] ────────────────────────
    private const VARIANTS = [
        'qcm_recognition'      => ['qcm',        'recognition'],
        'qcm_reasoning'        => ['qcm',        'reasoning'],
        'qcm_deceptive_trap'   => ['qcm',        'deceptive_trap'],
        'tf_recognition_true'  => ['true_false', 'recognition'],
        'tf_recognition_false' => ['true_false', 'recognition'],
        'tf_reasoning_true'    => ['true_false', 'reasoning'],
        'tf_reasoning_false'   => ['true_false', 'reasoning'],
    ];

    // ─── Nombre max de sujets dans l'inventaire ──────────────────────────────
    private const SUBJECTS_INVENTORY_MAX = 50;

    // ─── Nombre d'idées dominantes pour le sujet actif ───────────────────────
    private const DOMINANT_IDEAS_MAX = 5;

    // ─── Nombre de cognitifs attendus ────────────────────────────────────────
    private const COGNITIVE_COUNT = 7;

    // ═════════════════════════════════════════════════════════════════════════
    // Point d'entrée public
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Construit le Kernel Blueprint Frame complet.
     *
     * Retourne un array prêt à être encodé en JSON et stocké dans frame_en.
     * Toutes les valeurs à remplir ultérieurement sont null ou [].
     * Si l'intent fournit déjà domain/depth (pipeline legacy), ils sont pré-remplis
     * avec selection_source='legacy_intent' et status='filled'.
     */
    public function buildSkeleton(QuestionIntent $intent): array
    {
        $band = ReadingBandConfig::defaultBandForDepth((int) $intent->difficulty_depth);

        return [
            // ══ BLUEPRINT FRAME — nouvelle architecture ══════════════════════
            'kernel_code'          => $this->buildKernelCodeSlot(),
            'depth_slot'           => $this->buildDepthSlot($intent),
            'domain_slot'          => $this->buildDomainSlot($intent),
            'sub_domain_slot'      => $this->buildSubDomainSlot($intent),
            'subjects_inventory'   => $this->buildSubjectsInventory(),
            'active_subject'       => $this->buildActiveSubjectSlot(),
            'dominant_ideas'       => $this->buildDominantIdeasSlot(),
            'active_dominant_idea' => $this->buildActiveDominantIdeaSlot(),
            'cognitive_slots'      => $this->buildCognitiveSlots($band),
            'object_contracts'     => $this->buildObjectContracts(),
            'relation_map'         => $this->buildRelationMap(),
            'rules'                => $this->buildRules(),
            'mechanisms'           => $this->buildMechanisms(),
            'constraints'          => $this->buildConstraints(),
            'statuses'             => $this->buildStatuses(),
            'traces'               => [],

            // ══ KERNEL CORE LEGACY — compatibilité pipeline existant ═════════
            'kernel_core'             => $this->buildKernelCore($intent, $band),
            'translation_constraints' => $this->buildTranslationConstraints($band),
            'variants'                => $this->buildVariants(),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Blueprint Frame — slots principaux
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * kernel_code — Identifiant unique du noyau mère au format yy-xx-xxx-xxx-xxx-zz.
     *
     * Construction PROGRESSIVE — 5 propriétaires successifs :
     *   Step 1 — KernelFrameBuilder   : réserve le slot (value=null)
     *   Step 2 — KernelRotationPlanner: écrit le préfixe yy-xx (Depth+Domain)
     *   Step 3 — Taxonomy             : écrit le segment xxx-xxx-xxx (SubDomain+Sujet+Idée)
     *   Step 4 — KEY_STRUCTURE        : valide le pré-code + ks_hash
     *   Step 5 — KLD                  : complète le suffixe zz + kld_hash → verrouille
     *
     * depends_on    : depth_slot, domain_slot, sub_domain_slot, active_subject, active_dominant_idea
     * read_by       : QuestionIntent, Phase1, Phase2, Phase3, Phase4, READY_BANK, tous
     * locked_after  : validation KLD (kld_hash posé) — le code devient immutable
     * transmitted_to: QuestionIntent, READY_BANK
     * forbidden     : Immutable après verrouillage KLD. Aucun composant ne peut modifier ni écraser.
     * expected_content: VARCHAR(32) — ex: "06-03-012-007-003-04"
     * status_initial: 'empty'
     */
    private function buildKernelCodeSlot(): array
    {
        return [
            'value'   => null,
            'format'  => 'yy-xx-xxx-xxx-xxx-zz',
            'status'  => 'EMPTY',
            'locked'  => false,
            'owners'  => [
                ['step' => 1, 'component' => 'KernelFrameBuilder',       'action' => 'réserve le slot (value=null)'],
                ['step' => 2, 'component' => 'KernelRotationPlanner',     'action' => 'écrit le préfixe yy-xx (Depth+Domain)'],
                ['step' => 3, 'component' => 'Taxonomy (TaxonomyReader)', 'action' => 'écrit le segment xxx-xxx-xxx (SubDomain+Sujet+Idée)'],
                ['step' => 4, 'component' => 'KEY_STRUCTURE',             'action' => 'valide le pré-code + pose ks_hash'],
                ['step' => 5, 'component' => 'KLD',                       'action' => 'complète le suffixe zz + pose kld_hash → verrouille'],
            ],
            'rules'   => [
                'creator'          => 'KernelFrameBuilder',
                'construction'     => 'progressive — 5 composants successifs (voir owners)',
                'depends_on'       => ['depth_slot', 'domain_slot', 'sub_domain_slot', 'active_subject', 'active_dominant_idea'],
                'read_by'          => ['QuestionIntent', 'Phase1', 'Phase2', 'Phase3', 'Phase4', 'READY_BANK'],
                'write_access'     => 'chaque owner écrit son segment dans l\'ordre défini dans owners[]',
                'locked_after'     => 'KLD pose kld_hash — le code devient immutable',
                'transmitted_to'   => 'QuestionIntent, READY_BANK',
                'forbidden'        => 'Immutable après verrouillage KLD. Aucun composant ne peut modifier ni écraser.',
                'expected_content' => 'VARCHAR(32) — ex: "06-03-012-007-003-04"',
            ],
            'traces'  => [],
        ];
    }

    /**
     * depth_slot — Contenant structurel du Depth du noyau.
     *
     * created_by    : KernelFrameBuilder
     * filled_by     : KernelRotationPlanner via DepthNeedMatrix
     * read_by       : Taxonomy, KEY_STRUCTURE, QuestionIntent, Phase1, READY_BANK
     * write_access  : KernelRotationPlanner uniquement
     * locked_after  : remplissage par KernelRotationPlanner
     * transmitted_to: Taxonomy
     * forbidden     : Aucune autre brique ne peut modifier depth_slot
     * expected_content: requested_depth=int 1–10, actual_depth=int 1–10 (peut diverger si fallback)
     * status_initial: 'empty'
     * traces        : append-only par KernelRotationPlanner
     */
    private function buildDepthSlot(QuestionIntent $intent): array
    {
        $hasDepth = $intent->difficulty_depth !== null;
        $depth    = $hasDepth ? (int) $intent->difficulty_depth : null;

        return [
            'requested_depth'  => $depth,
            'actual_depth'     => $depth,
            'selection_source' => $hasDepth ? 'legacy_intent' : null,
            'filled_at'        => null,
            'status'           => $hasDepth ? 'FILLED' : 'EMPTY',
            'locked'           => $hasDepth,
            'rules'            => [
                'creator'          => 'KernelFrameBuilder',
                'filler'           => 'KernelRotationPlanner',
                'driven_by'        => 'DepthNeedMatrix',
                'depends_on'       => [],
                'allowed_values'   => '1–10',
                'read_by'          => ['Taxonomy', 'KEY_STRUCTURE', 'QuestionIntent', 'Phase1', 'READY_BANK'],
                'write_access'     => 'KernelRotationPlanner uniquement',
                'locked_after'     => 'remplissage par KernelRotationPlanner',
                'transmitted_to'   => 'Taxonomy',
                'forbidden'        => 'Aucune autre brique ne peut modifier depth_slot.',
                'expected_content' => 'requested_depth et actual_depth = entier 1–10',
            ],
            'traces' => [],
        ];
    }

    /**
     * domain_slot — Contenant structurel du Domaine du noyau.
     *
     * created_by    : KernelFrameBuilder
     * filled_by     : KernelRotationPlanner via DomainCycle
     * read_by       : Taxonomy, KEY_STRUCTURE, QuestionIntent, Phase1, READY_BANK
     * write_access  : KernelRotationPlanner uniquement
     * locked_after  : remplissage par KernelRotationPlanner
     * transmitted_to: Taxonomy
     * forbidden     : Aucune autre brique ne peut modifier domain_slot
     * expected_content: libellé domaine issu de taxonomy.json (ex: "Géographie")
     * status_initial: 'empty'
     * traces        : append-only par KernelRotationPlanner
     */
    private function buildDomainSlot(QuestionIntent $intent): array
    {
        $hasDomain = $intent->domain !== null && $intent->domain !== '';
        $domain    = $hasDomain ? $intent->domain : null;

        return [
            'requested_domain' => $domain,
            'actual_domain'    => $domain,
            'selection_source' => $hasDomain ? 'legacy_intent' : null,
            'filled_at'        => null,
            'status'           => $hasDomain ? 'FILLED' : 'EMPTY',
            'locked'           => $hasDomain,
            'rules'            => [
                'creator'          => 'KernelFrameBuilder',
                'filler'           => 'KernelRotationPlanner',
                'driven_by'        => 'DomainCycle',
                'depends_on'       => ['depth_slot'],
                'allowed_values'   => 'domaines valides dans taxonomy.json',
                'read_by'          => ['Taxonomy', 'KEY_STRUCTURE', 'QuestionIntent', 'Phase1', 'READY_BANK'],
                'write_access'     => 'KernelRotationPlanner uniquement',
                'locked_after'     => 'remplissage par KernelRotationPlanner',
                'transmitted_to'   => 'Taxonomy',
                'forbidden'        => 'Aucune autre brique ne peut modifier domain_slot.',
                'expected_content' => 'libellé domaine issu de taxonomy.json — ex: "Géographie"',
            ],
            'traces' => [],
        ];
    }

    /**
     * sub_domain_slot — Contenant structurel du Sous-domaine du noyau.
     *
     * created_by    : KernelFrameBuilder
     * filled_by     : Taxonomy (TaxonomyReader)
     * read_by       : KEY_STRUCTURE, QuestionIntent, Phase1, READY_BANK, subjects_inventory
     * write_access  : Taxonomy (TaxonomyReader) uniquement
     * locked_after  : remplissage par Taxonomy
     * transmitted_to: subjects_inventory, KEY_STRUCTURE
     * forbidden     : Aucune autre brique ne peut modifier sub_domain_slot
     * expected_content: libellé sous-domaine valide pour domain+depth dans taxonomy.json
     * status_initial: 'empty'
     * traces        : append-only par Taxonomy
     */
    private function buildSubDomainSlot(QuestionIntent $intent): array
    {
        $hasSubDomain = $intent->sub_domain !== null && $intent->sub_domain !== '';
        $subDomain    = $hasSubDomain ? $intent->sub_domain : null;

        return [
            'requested_sub_domain' => $subDomain,
            'actual_sub_domain'    => $subDomain,
            'selection_source'     => $hasSubDomain ? 'legacy_intent' : null,
            'filled_at'            => null,
            'status'               => $hasSubDomain ? 'FILLED' : 'EMPTY',
            'locked'               => $hasSubDomain,
            'rules'                => [
                'creator'          => 'KernelFrameBuilder',
                'filler'           => 'Taxonomy (TaxonomyReader)',
                'depends_on'       => ['depth_slot', 'domain_slot'],
                'allowed_values'   => 'sous-domaines valides pour domain+depth dans taxonomy.json',
                'read_by'          => ['subjects_inventory', 'KEY_STRUCTURE', 'QuestionIntent', 'Phase1', 'READY_BANK'],
                'write_access'     => 'Taxonomy (TaxonomyReader) uniquement',
                'locked_after'     => 'remplissage par Taxonomy',
                'transmitted_to'   => 'subjects_inventory, KEY_STRUCTURE',
                'forbidden'        => 'Aucune autre brique ne peut modifier sub_domain_slot.',
                'expected_content' => 'libellé sous-domaine — ex: "Capitales"',
            ],
            'traces' => [],
        ];
    }

    /**
     * subjects_inventory — 50 coquilles de sujets du sous-domaine actif.
     *
     * created_by    : KernelFrameBuilder (50 coquilles vides)
     * filled_by     : Taxonomy (TaxonomyReader) — remplit les labels
     * read_by       : active_subject, KEY_STRUCTURE
     * write_access  : Taxonomy (TaxonomyReader) uniquement
     * locked_after  : sujet marqué 'active' et dominant_ideas générées pour lui
     * transmitted_to: active_subject
     * forbidden     : Les dominant_ideas ne sont générées QUE pour le sujet actif.
     *                 Un sujet non actif ne peut pas avoir d'idées dominantes.
     * expected_content: label = libellé du sujet (ex: "Nairobi"), status = 'available'|'active'|'consumed'
     * status_initial: 'available' (coquille prête à recevoir un label)
     * traces        : append-only par Taxonomy
     */
    private function buildSubjectsInventory(): array
    {
        $inventory = [];

        for ($i = 1; $i <= self::SUBJECTS_INVENTORY_MAX; $i++) {
            $inventory[] = [
                'index'     => $i,
                'label'     => null,
                'filled_at' => null,
                'status'    => 'EMPTY',
                'locked'    => false,
                'rules'     => [
                    'creator'          => 'KernelFrameBuilder',
                    'filler'           => 'Taxonomy (TaxonomyReader)',
                    'depends_on'       => ['sub_domain_slot'],
                    'max_slots'        => self::SUBJECTS_INVENTORY_MAX,
                    'read_by'          => ['active_subject', 'KEY_STRUCTURE'],
                    'write_access'     => 'Taxonomy (TaxonomyReader) uniquement',
                    'locked_after'     => 'sujet marqué active et dominant_ideas générées pour lui',
                    'transmitted_to'   => 'active_subject',
                    'forbidden'        => 'dominant_ideas générées uniquement pour le sujet actif.',
                    'expected_content' => 'label = libellé sujet (ex: "Nairobi")',
                ],
                'traces' => [],
            ];
        }

        return $inventory;
    }

    /**
     * active_subject — Pointeur vers le sujet courant du noyau.
     *
     * created_by    : KernelFrameBuilder (slot vide)
     * filled_by     : Taxonomy (TaxonomyReader)
     * read_by       : dominant_ideas, active_dominant_idea, KEY_STRUCTURE, QuestionIntent
     * write_access  : Taxonomy (TaxonomyReader) uniquement
     * locked_after  : dominant_ideas générées pour ce sujet
     * transmitted_to: dominant_ideas
     * forbidden     : Un seul sujet peut être actif à la fois.
     *                 Aucune autre brique ne peut modifier active_subject.
     * expected_content: subject_index = int (index dans subjects_inventory), subject_label = string
     * status_initial: 'empty'
     * traces        : append-only par Taxonomy
     */
    private function buildActiveSubjectSlot(): array
    {
        return [
            'subject_index' => null,
            'subject_label' => null,
            'set_at'        => null,
            'status'        => 'EMPTY',
            'locked'        => false,
            'rules'         => [
                'creator'          => 'KernelFrameBuilder',
                'filler'           => 'Taxonomy (TaxonomyReader)',
                'depends_on'       => ['subjects_inventory'],
                'read_by'          => ['dominant_ideas', 'active_dominant_idea', 'KEY_STRUCTURE', 'QuestionIntent'],
                'write_access'     => 'Taxonomy (TaxonomyReader) uniquement',
                'locked_after'     => 'jamais de verrouillage définitif — le sujet actif peut changer quand ses idées sont épuisées',
                'rotation'         => 'Sujet 1 → idées 1–5 épuisées par QuestionIntent → Taxonomy active Sujet 2 → dominant_ideas rechargées',
                'transmitted_to'   => 'dominant_ideas',
                'forbidden'        => 'Un seul sujet peut être actif à la fois. Aucune autre brique ne peut modifier active_subject.',
                'expected_content' => 'subject_index = int (index dans subjects_inventory), subject_label = libellé sujet',
            ],
            'traces' => [],
        ];
    }

    /**
     * dominant_ideas — 5 idées du sujet actif uniquement.
     *
     * created_by    : KernelFrameBuilder (conteneur vide)
     * filled_by     : Taxonomy (TaxonomyReader)
     * read_by       : active_dominant_idea, KEY_STRUCTURE, QuestionIntent
     * write_access  : Taxonomy (TaxonomyReader) uniquement
     * locked_after  : QuestionIntent verrouillé
     * transmitted_to: active_dominant_idea, KEY_STRUCTURE
     * forbidden     : Les idées sont générées UNIQUEMENT pour active_subject.
     *                 Aucune idée pour un sujet non actif. Max 5 idées.
     * expected_content: ideas = [{index, label, filled_at}] — max 5 entrées
     * status_initial: 'empty'
     * traces        : append-only par Taxonomy
     */
    private function buildDominantIdeasSlot(): array
    {
        return [
            'ideas'  => [],
            'status' => 'EMPTY',
            'locked' => false,
            'rules'  => [
                'creator'             => 'KernelFrameBuilder',
                'filler'              => 'Taxonomy (TaxonomyReader)',
                'max_ideas'           => self::DOMINANT_IDEAS_MAX,
                'scope'               => 'active_subject uniquement — aucune idée pour un sujet non actif',
                'depends_on'          => ['active_subject'],
                'read_by'             => ['active_dominant_idea', 'KEY_STRUCTURE', 'QuestionIntent'],
                'write_access'        => 'Taxonomy (TaxonomyReader) uniquement',
                'locked_after'        => 'jamais de verrouillage définitif — dominant_ideas se recharge quand active_subject change',
                'status_progression'  => 'EMPTY → FILLED (exactement 5 idées chargées) → EMPTY à nouveau si active_subject change',
                'locked_semantics'    => 'LOCKED = verrouillé pour le cycle courant uniquement. Taxonomy peut réactiver quand active_subject change.',
                'rotation'            => 'Quand active_subject change → Taxonomy recharge dominant_ideas avec les 5 idées du nouveau sujet',
                'transmitted_to'      => 'active_dominant_idea, KEY_STRUCTURE',
                'forbidden'           => 'Idées générées uniquement pour active_subject. Max 5. Aucune autre brique ne peut modifier dominant_ideas.',
                'expected_content'    => 'ideas = [{index, label, filled_at}] — exactement 5 entrées quand FILLED, liées au sujet actif',
            ],
            'traces' => [],
        ];
    }

    /**
     * active_dominant_idea — Pointeur vers l'idée dominante courante du noyau.
     *
     * created_by    : KernelFrameBuilder (slot vide)
     * filled_by     : Taxonomy (TaxonomyReader)
     * read_by       : KEY_STRUCTURE, QuestionIntent, Phase1
     * write_access  : Taxonomy (TaxonomyReader) uniquement
     * locked_after  : QuestionIntent verrouillé
     * transmitted_to: KEY_STRUCTURE, QuestionIntent
     * forbidden     : Une seule idée peut être active à la fois.
     *                 Aucune autre brique ne peut modifier active_dominant_idea.
     * expected_content: idea_index = int (index dans dominant_ideas.ideas), idea_label = string
     * status_initial: 'empty'
     * traces        : append-only par Taxonomy
     */
    private function buildActiveDominantIdeaSlot(): array
    {
        return [
            'idea_index' => null,
            'idea_label' => null,
            'set_at'     => null,
            'status'     => 'EMPTY',
            'locked'     => false,
            'rules'      => [
                'creator'          => 'KernelFrameBuilder',
                'filler'           => 'Taxonomy (TaxonomyReader)',
                'depends_on'       => ['dominant_ideas'],
                'read_by'          => ['KEY_STRUCTURE', 'QuestionIntent', 'Phase1'],
                'write_access'     => 'Taxonomy (TaxonomyReader) uniquement',
                'locked_after'     => 'QuestionIntent consomme la PAIRE (active_subject + active_dominant_idea) — la paire est ensuite marquée consommée',
                'pair_lock'        => 'QuestionIntent verrouille la PAIRE sujet+idée uniquement, pas le bloc Taxonomy. Taxonomy peut pointer vers la prochaine idée disponible.',
                'rotation'         => 'Idée consommée → Taxonomy sélectionne la prochaine idée dans dominant_ideas. Toutes épuisées → Sujet 2 actif.',
                'transmitted_to'   => 'KEY_STRUCTURE, QuestionIntent',
                'forbidden'        => 'Une seule idée active à la fois. Aucune autre brique ne peut modifier active_dominant_idea.',
                'expected_content' => 'idea_index = int (index dans dominant_ideas.ideas), idea_label = libellé idée dominante',
            ],
            'traces' => [],
        ];
    }

    /**
     * cognitive_slots — 7 cognitifs × {question/réponses/SV/traduction/statut/traces}.
     * Toutes les valeurs null. Rempli par Phase1 (KernelContentBuilder).
     */
    private function buildCognitiveSlots(string $band): array
    {
        $slots = [];

        foreach (self::VARIANTS as $variantKey => [$questionType, $cognitiveType]) {
            $isTf = ($questionType === 'true_false');

            $slots[$variantKey] = [
                'question_type'  => $questionType,
                'cognitive_type' => $cognitiveType,

                // ── question_slot ─────────────────────────────────────────
                // created_by: KernelFrameBuilder | filled_by: Phase1 (KernelContentBuilder)
                // read_by: Phase2, Phase3, READY_BANK | locked_after: Phase2 validation
                // transmitted_to: Phase2 (validation), Phase3 (source traduction)
                // forbidden: Aucune autre brique ne peut modifier question_slot
                'question_slot' => [
                    'value'     => null,
                    'filled_at' => null,
                    'status'    => 'EMPTY',
                    'locked'    => false,
                    'rules'     => [
                        'creator'          => 'KernelFrameBuilder',
                        'filler'           => 'Phase1 (KernelContentBuilder)',
                        'language'         => 'en',
                        'max_chars'        => ReadingBandConfig::resolveForLang($band, 'en')['soft'] ?? 280,
                        'depends_on'       => ['QuestionIntent'],
                        'read_by'          => ['Phase2', 'Phase3', 'READY_BANK'],
                        'write_access'     => 'Phase1 (KernelContentBuilder) uniquement',
                        'locked_after'     => 'Phase2 validation',
                        'transmitted_to'   => 'Phase2 (validation source EN), Phase3 (source traduction)',
                        'forbidden'        => 'Aucune autre brique ne peut modifier question_slot.',
                        'expected_content' => 'question EN — chaîne de max max_chars caractères',
                    ],
                    'traces' => [],
                ],

                // ── answer_slots ──────────────────────────────────────────
                // created_by: KernelFrameBuilder | filled_by: Phase1
                // read_by: Phase2, Phase3, READY_BANK | locked_after: Phase2 validation
                // transmitted_to: Phase3 (source traduction)
                // forbidden: Aucune autre brique ne peut modifier answer_slots
                'answer_slots' => $this->buildAnswerSlots($isTf),

                // ── correct_answer_key ────────────────────────────────────
                'correct_answer_key' => null,

                // ── sv_slot (saviez-vous) ─────────────────────────────────
                // created_by: KernelFrameBuilder | filled_by: Phase1 (KernelContentBuilder)
                // read_by: Phase2, Phase3, READY_BANK | locked_after: Phase2 validation
                // transmitted_to: Phase2 (validation), Phase3 (source traduction), READY_BANK
                // forbidden: Aucune autre brique ne peut modifier sv_slot
                'sv_slot' => [
                    'value'     => null,
                    'filled_at' => null,
                    'status'    => 'EMPTY',
                    'locked'    => false,
                    'rules'     => [
                        'creator'          => 'KernelFrameBuilder',
                        'filler'           => 'Phase1 (KernelContentBuilder)',
                        'language'         => 'en',
                        'min_chars'        => self::SV_MIN,
                        'max_chars'        => self::SV_MAX,
                        'depends_on'       => ['QuestionIntent'],
                        'read_by'          => ['Phase2', 'Phase3', 'READY_BANK'],
                        'write_access'     => 'Phase1 (KernelContentBuilder) uniquement',
                        'locked_after'     => 'Phase2 validation',
                        'transmitted_to'   => 'Phase2 (validation), Phase3 (source traduction), READY_BANK',
                        'forbidden'        => 'Aucune autre brique ne peut modifier sv_slot.',
                        'expected_content' => '"Saviez-vous" EN — ' . self::SV_MIN . '–' . self::SV_MAX . ' chars',
                    ],
                    'traces' => [],
                ],

                // ── translation_slots (9 langues) ─────────────────────────
                // created_by: KernelFrameBuilder | filled_by: Phase3 (KernelTranslator)
                // validated_by: Phase4 | read_by: Phase4, READY_BANK, Gameplay
                // locked_after: Phase4 validation | transmitted_to: READY_BANK, Gameplay
                // forbidden: Aucune autre brique ne peut modifier translation_slots
                'translation_slots' => $this->buildCognitiveTranslationSlots($isTf),

                // ── statut et traces du cognitif ──────────────────────────
                'status' => 'EMPTY',
                'rules'  => [
                    'creator'          => 'KernelFrameBuilder',
                    'filler'           => 'Phase1 (KernelContentBuilder)',
                    'depends_on'       => 'QuestionIntent verrouillé',
                    'read_by'          => ['Phase2', 'Phase3', 'Phase4', 'READY_BANK'],
                    'write_access'     => 'Phase1 (KernelContentBuilder) uniquement',
                    'locked_after'     => 'Phase2 validation (toutes questions/réponses/SV EN validés)',
                    'transmitted_to'   => 'Phase2, Phase3 (source traduction), READY_BANK',
                    'forbidden'        => 'Aucune autre brique ne peut remplir ce slot cognitif. Master = qcm_recognition.',
                    'expected_content' => 'question + réponses + sv EN validés — status = validated_ok',
                ],
                'traces' => [],
            ];
        }

        return $slots;
    }

    /**
     * answer_slots — 4 pour QCM, 2 pour TF.
     *
     * created_by    : KernelFrameBuilder
     * filled_by     : Phase1 (KernelContentBuilder)
     * read_by       : Phase2, Phase3, READY_BANK
     * write_access  : Phase1 (KernelContentBuilder) uniquement
     * locked_after  : Phase2 validation
     * transmitted_to: Phase3 (source traduction)
     * forbidden     : Aucune autre brique ne peut modifier answer_slots
     * expected_content: réponse EN — chaîne ≤ max_chars
     */
    private function buildAnswerSlots(bool $isTf): array
    {
        $answerRules = [
            'creator'          => 'KernelFrameBuilder',
            'filler'           => 'Phase1 (KernelContentBuilder)',
            'language'         => 'en',
            'max_chars'        => self::A_MAX,
            'depends_on'       => ['QuestionIntent'],
            'read_by'          => ['Phase2', 'Phase3', 'READY_BANK'],
            'write_access'     => 'Phase1 (KernelContentBuilder) uniquement',
            'locked_after'     => 'Phase2 validation',
            'transmitted_to'   => 'Phase3 (source traduction)',
            'forbidden'        => 'Aucune autre brique ne peut modifier answer_slots.',
            'expected_content' => 'réponse EN — ≤ ' . self::A_MAX . ' chars',
        ];

        $answerSlot = fn() => [
            'value'     => null,
            'filled_at' => null,
            'status'    => 'EMPTY',
            'locked'    => false,
            'rules'     => $answerRules,
            'traces'    => [],
        ];

        if ($isTf) {
            return [
                'answer_a' => $answerSlot(),
                'answer_b' => $answerSlot(),
            ];
        }

        return [
            'answer_a' => $answerSlot(),
            'answer_b' => $answerSlot(),
            'answer_c' => $answerSlot(),
            'answer_d' => $answerSlot(),
        ];
    }

    /**
     * translation_slots — 9 langues × {question/réponses/SV/statut/traces}.
     *
     * created_by    : KernelFrameBuilder
     * filled_by     : Phase3 (KernelTranslator)
     * validated_by  : Phase4
     * read_by       : Phase4, READY_BANK, Gameplay
     * write_access  : Phase3 (KernelTranslator) uniquement
     * locked_after  : Phase4 validation
     * transmitted_to: READY_BANK, Gameplay
     * forbidden     : Aucune autre brique ne peut modifier translation_slots
     * expected_content: traduction complète (question + réponses + sv) dans la langue cible
     */
    private function buildCognitiveTranslationSlots(bool $isTf): array
    {
        $slots = [];

        foreach (self::TRANSLATION_LANGS as $lang) {
            $isZh = ($lang === 'zh');
            $isAr = ($lang === 'ar');

            $answerMax = $isZh ? self::A_MAX_ZH : ($isAr ? self::A_MAX_AR : self::A_MAX);
            $svMax     = $isZh ? self::SV_MAX_ZH : ($isAr ? self::SV_MAX_AR : self::SV_MAX);

            $slots[$lang] = [
                'status'             => 'EMPTY',
                'filled_at'          => null,
                'locked'             => false,
                'question_text'      => null,
                'answer_a'           => null,
                'answer_b'           => null,
                'answer_c'           => $isTf ? 'n/a' : null,
                'answer_d'           => $isTf ? 'n/a' : null,
                'correct_answer_key' => null,
                'explanation'        => null,
                'saviez_vous'        => null,
                'rules'              => [
                    'creator'          => 'KernelFrameBuilder',
                    'filler'           => 'Phase3 (KernelTranslator)',
                    'validator'        => 'Phase4',
                    'source'           => 'question_slot EN (Phase1 output)',
                    'language'         => $lang,
                    'depends_on'       => ['Phase2_validation'],
                    'answer_max'       => $answerMax,
                    'sv_max'           => $svMax,
                    'sv_min'           => self::SV_MIN,
                    'read_by'          => ['Phase4', 'READY_BANK', 'Gameplay'],
                    'write_access'     => 'Phase3 (KernelTranslator) uniquement',
                    'locked_after'     => 'Phase4 validation',
                    'transmitted_to'   => 'READY_BANK, Gameplay',
                    'forbidden'        => 'Aucune autre brique ne peut modifier translation_slots.',
                    'expected_content' => "traduction complète {$lang} — question + réponses + sv",
                ],
                'traces' => [],
            ];
        }

        return $slots;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Blueprint Frame — méta-slots (rules, mechanisms, constraints, statuses)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * object_contracts — Contrat de données (champs + types + sémantique) de chaque
     * type de slot utilisé dans le frame. Définit ce qu'il y a RÉELLEMENT dans chaque
     * objet quand il est rempli par son propriétaire.
     *
     * Objectif : aucun moteur (Taxonomy, KEY_STRUCTURE, KLD, Phase1…) n'a besoin
     * d'inventer la structure — tout est déjà spécifié ici.
     */
    private function buildObjectContracts(): array
    {
        return [

            // ── KernelFrame ─────────────────────────────────────────────────
            'KernelFrame' => [
                'description' => 'Objet racine du noyau mère — conteneur complet de tous les slots',
                'fields' => [
                    'kernel_code'          => 'KernelCodeSlot   — identifiant unique yy-xx-xxx-xxx-xxx-zz',
                    'depth_slot'           => 'DepthSlot        — profondeur cognitive (1-10)',
                    'domain_slot'          => 'DomainSlot       — domaine principal (ex: Géographie)',
                    'sub_domain_slot'      => 'SubDomainSlot    — sous-domaine (ex: Capitales)',
                    'subjects_inventory'   => 'SubjectSlot[50]  — inventaire des 50 sujets du sous-domaine',
                    'active_subject'       => 'ActiveSubjectRef — pointeur vers le sujet actif courant',
                    'dominant_ideas'       => 'DominantIdeasSlot — les 5 idées du sujet actif',
                    'active_dominant_idea' => 'ActiveIdeaRef    — pointeur vers l\'idée dominante active',
                    'cognitive_slots'      => 'CognitiveSlot[7] — 7 cognitifs (qcm×3 + tf×4)',
                    'object_contracts'     => 'ObjectContracts  — contrats de données (ce fichier)',
                    'relation_map'         => 'RelationMap      — chaîne 1→50→1→5→1→7',
                    'rules'                => 'Rules            — règles de remplissage',
                    'mechanisms'           => 'Mechanisms       — 11 étapes pipeline',
                    'constraints'          => 'Constraints      — invariants non négociables',
                    'statuses'             => 'Statuses[10]     — avancement pipeline par composant',
                    'traces'               => 'Trace[]          — historique global append-only',
                ],
                'owner' => 'KernelFrameBuilder (création) → pipeline (remplissage progressif)',
            ],

            // ── SubjectSlot ──────────────────────────────────────────────────
            'SubjectSlot' => [
                'description' => 'Un sujet dans l\'inventaire des 50 sujets du sous-domaine actif',
                'filled_by'   => 'Taxonomy (TaxonomyReader)',
                'fields' => [
                    'index'                => 'int (1..50)          — position dans l\'inventaire',
                    'label'                => 'string|null          — libellé du sujet (ex: "Paris", "Rome")',
                    'subject_id'           => 'string|null          — identifiant DB Taxonomy',
                    'subject_code'         => 'string|null          — code unique dans le sous-domaine (ex: "CAP-007")',
                    'active'               => 'bool                 — true = c\'est l\'active_subject courant',
                    'exhausted'            => 'bool                 — true = toutes les idées dominantes consommées',
                    'dominant_ideas_count' => 'int (0..5)           — nombre d\'idées confirmées pour ce sujet',
                    'status'               => 'EMPTY|FILLED|VALIDATED_OK|LOCKED|REJECTED|CORRECTION_NEEDED',
                    'locked'               => 'bool                 — slot verrouillé pour ce cycle',
                    'filled_at'            => 'timestamp|null       — horodatage remplissage par Taxonomy',
                    'traces'               => 'Trace[]              — historique append-only (décisions Taxonomy)',
                    'rules'                => 'SlotRules            — contrat de remplissage (voir subjects_inventory)',
                ],
                'initial_state' => [
                    'label'                => null,
                    'subject_id'           => null,
                    'subject_code'         => null,
                    'active'               => false,
                    'exhausted'            => false,
                    'dominant_ideas_count' => 0,
                    'status'               => 'EMPTY',
                    'locked'               => false,
                    'filled_at'            => null,
                ],
            ],

            // ── DominantIdeaSlot ─────────────────────────────────────────────
            'DominantIdeaSlot' => [
                'description' => 'Une idée dominante dans la liste des 5 idées du sujet actif',
                'filled_by'   => 'Taxonomy (TaxonomyReader)',
                'capacity'    => 5,
                'fields' => [
                    'index'     => 'int (1..5)    — position dans dominant_ideas.ideas',
                    'idea'      => 'string|null   — libellé de l\'idée dominante (ex: "Superficie")',
                    'idea_id'   => 'string|null   — identifiant DB Taxonomy',
                    'idea_code' => 'string|null   — code unique dans le sujet (ex: "IDEA-003")',
                    'active'    => 'bool          — true = c\'est l\'active_dominant_idea courante',
                    'consumed'  => 'bool          — paire (sujet+idée) déjà consommée par QuestionIntent',
                    'rejected'  => 'bool          — rejetée par KEY_STRUCTURE ou KLD',
                    'validated' => 'bool          — validée (VALIDATED_OK) pour usage gameplay',
                    'status'    => 'EMPTY|FILLED|VALIDATED_OK|LOCKED|REJECTED|CORRECTION_NEEDED',
                    'filled_at' => 'timestamp|null — horodatage remplissage',
                ],
                'initial_state' => [
                    'idea'      => null,
                    'idea_id'   => null,
                    'idea_code' => null,
                    'active'    => false,
                    'consumed'  => false,
                    'rejected'  => false,
                    'validated' => false,
                    'status'    => 'EMPTY',
                    'filled_at' => null,
                ],
                'lifecycle' => 'EMPTY → FILLED (Taxonomy) → VALIDATED_OK (KEY_STRUCTURE) → LOCKED (consommée par QuestionIntent) | REJECTED (KLD/KEY_STRUCTURE)',
            ],

            // ── CognitiveSlot ────────────────────────────────────────────────
            'CognitiveSlot' => [
                'description' => 'Un cognitif complet (question + réponses + SV + traductions) pour un variant donné',
                'filled_by'   => 'Phase1 (KernelContentBuilder) + Phase3 (KernelTranslator)',
                'count'       => self::COGNITIVE_COUNT,
                'fields' => [
                    'variant_key'       => 'string              — clé du variant (ex: "qcm_recognition")',
                    'cognitive_family'  => '"qcm"|"true_false"  — famille cognitive',
                    'cognitive_form'    => '"recognition"|"reasoning"|"deceptive_trap" — forme cognitive',
                    'question_slot'     => 'QuestionSlot        — question EN',
                    'answer_slots'      => 'AnswerSlot[2|4]     — réponses EN (2 pour TF, 4 pour QCM)',
                    'correct_answer_key'=> '"answer_a"|"answer_b"|"answer_c"|"answer_d"|null',
                    'sv_slot'           => 'SVSlot              — saviez-vous EN',
                    'translation_slots' => 'TranslationSlot[9]  — {fr,es,de,it,pt,ru,zh,ar,el}',
                    'status'            => 'EMPTY|FILLED|VALIDATED_OK|LOCKED|REJECTED|CORRECTION_NEEDED',
                    'traces'            => 'Trace[]             — historique append-only',
                ],
                'initial_state' => [
                    'correct_answer_key' => null,
                    'status'             => 'EMPTY',
                ],
            ],

            // ── QuestionSlot ─────────────────────────────────────────────────
            'QuestionSlot' => [
                'description' => 'Slot de la question en langue source (anglais)',
                'filled_by'   => 'Phase1 (KernelContentBuilder)',
                'language'    => 'en',
                'fields' => [
                    'value'                => 'string|null        — texte de la question EN',
                    'gameplay_constraints' => [
                        'question_type'  => '"qcm"|"true_false"',
                        'max_chars'      => 'int — longueur maximale du texte question',
                        'timing_weight'  => 'float — coefficient temps de lecture (profondeur × lisibilité)',
                    ],
                    'validation_state' => 'EMPTY|FILLED|VALIDATED_OK|REJECTED|CORRECTION_NEEDED',
                    'consumed'         => 'bool      — question déjà exposée en gameplay (phase de résolution vue)',
                    'language'         => '"en"      — toujours la langue source',
                    'filled_at'        => 'timestamp|null',
                    'status'           => 'EMPTY|FILLED|VALIDATED_OK|LOCKED|REJECTED|CORRECTION_NEEDED',
                    'locked'           => 'bool',
                    'traces'           => 'Trace[]',
                    'rules'            => 'SlotRules',
                ],
                'initial_state' => [
                    'value'            => null,
                    'consumed'         => false,
                    'validation_state' => 'EMPTY',
                    'status'           => 'EMPTY',
                    'locked'           => false,
                    'filled_at'        => null,
                ],
            ],

            // ── AnswerSlot ───────────────────────────────────────────────────
            'AnswerSlot' => [
                'description' => 'Un slot de réponse (answer_a à answer_d, ou answer_a/b pour TF)',
                'filled_by'   => 'Phase1 (KernelContentBuilder)',
                'language'    => 'en',
                'fields' => [
                    'value'      => 'string|null        — texte de la réponse EN',
                    'is_correct' => 'bool               — true si c\'est la bonne réponse',
                    'answer_key' => '"answer_a"|"answer_b"|"answer_c"|"answer_d" — clé de ce slot',
                    'language'   => '"en"               — toujours la langue source',
                    'filled_at'  => 'timestamp|null',
                    'status'     => 'EMPTY|FILLED|VALIDATED_OK|LOCKED|REJECTED|CORRECTION_NEEDED',
                    'locked'     => 'bool',
                    'traces'     => 'Trace[]',
                    'rules'      => 'SlotRules',
                ],
                'initial_state' => [
                    'value'      => null,
                    'is_correct' => false,
                    'answer_key' => null,
                    'status'     => 'EMPTY',
                    'locked'     => false,
                    'filled_at'  => null,
                ],
                'constraint' => 'Exactement 1 slot has is_correct=true par CognitiveSlot',
            ],

            // ── SVSlot ───────────────────────────────────────────────────────
            'SVSlot' => [
                'description' => 'Slot "Saviez-vous que..." en langue source (anglais)',
                'filled_by'   => 'Phase1 (KernelContentBuilder)',
                'language'    => 'en',
                'fields' => [
                    'value'     => 'string|null  — texte EN "Did you know that..."',
                    'min_chars' => 'int          — longueur minimale',
                    'max_chars' => 'int          — longueur maximale (220 pour EN, 100 pour ZH, 140 pour AR)',
                    'language'  => '"en"         — toujours la langue source',
                    'filled_at' => 'timestamp|null',
                    'status'    => 'EMPTY|FILLED|VALIDATED_OK|LOCKED|REJECTED|CORRECTION_NEEDED',
                    'locked'    => 'bool',
                    'traces'    => 'Trace[]',
                    'rules'     => 'SlotRules',
                ],
                'initial_state' => [
                    'value'     => null,
                    'status'    => 'EMPTY',
                    'locked'    => false,
                    'filled_at' => null,
                ],
            ],

            // ── TranslationSlot ──────────────────────────────────────────────
            'TranslationSlot' => [
                'description' => 'Traduction complète d\'un cognitif dans une langue cible (9 langues)',
                'filled_by'   => 'Phase3 (KernelTranslator)',
                'validated_by'=> 'Phase4 (KernelTranslator validation)',
                'languages'   => self::TRANSLATION_LANGS,
                'fields' => [
                    'question_text'      => 'string|null — texte traduit de la question',
                    'answer_a'           => 'string|null — texte traduit réponse A',
                    'answer_b'           => 'string|null — texte traduit réponse B',
                    'answer_c'           => 'string|null — texte traduit réponse C (null pour TF)',
                    'answer_d'           => 'string|null — texte traduit réponse D (null pour TF)',
                    'correct_answer_key' => '"answer_a"|"answer_b"|"answer_c"|"answer_d"|null',
                    'explanation'        => 'string|null — explication traduite (optionnel)',
                    'saviez_vous'        => 'string|null — texte traduit "Saviez-vous que..."',
                    'answer_max'         => 'int         — cap réponse pour cette langue',
                    'sv_max'             => 'int         — cap SV pour cette langue',
                    'status'             => 'EMPTY|FILLED|VALIDATED_OK|LOCKED|REJECTED|CORRECTION_NEEDED',
                    'locked'             => 'bool',
                    'filled_at'          => 'timestamp|null',
                    'traces'             => 'Trace[]',
                    'rules'              => 'SlotRules',
                ],
                'initial_state' => [
                    'question_text'      => null,
                    'answer_a'           => null,
                    'answer_b'           => null,
                    'answer_c'           => null,
                    'answer_d'           => null,
                    'correct_answer_key' => null,
                    'explanation'        => null,
                    'saviez_vous'        => null,
                    'status'             => 'EMPTY',
                    'locked'             => false,
                    'filled_at'          => null,
                ],
                'char_caps' => [
                    'default_answer_max' => self::A_MAX,
                    'zh_answer_max'      => self::A_MAX_ZH,
                    'ar_answer_max'      => self::A_MAX_AR,
                    'default_sv_max'     => self::SV_MAX,
                    'zh_sv_max'          => self::SV_MAX_ZH,
                    'ar_sv_max'          => self::SV_MAX_AR,
                ],
            ],
        ];
    }

    /**
     * relation_map — Schéma des relations hiérarchiques entre objets du noyau mère.
     * Indique la cardinalité et la nature de chaque liaison.
     */
    private function buildRelationMap(): array
    {
        return [
            'description' => 'Chaîne de relations KernelFrame → objets contenus (cardinalités et nature)',
            'chain'        => '1 KernelFrame → 50 SubjectSlot → 1 actif → 5 DominantIdeaSlot → 1 active → 7 CognitiveSlot',
            'relations'    => [
                [
                    'from'        => 'KernelFrame',
                    'to'          => 'SubjectSlot',
                    'cardinality' => '1 → 50',
                    'slot'        => 'subjects_inventory',
                    'note'        => 'Inventaire complet des sujets du sous-domaine actif',
                ],
                [
                    'from'        => 'SubjectSlot[]',
                    'to'          => 'active_subject',
                    'cardinality' => '50 → 1',
                    'slot'        => 'active_subject',
                    'note'        => 'Un seul sujet actif à la fois — pointeur vers subjects_inventory[i]',
                ],
                [
                    'from'        => 'active_subject',
                    'to'          => 'DominantIdeaSlot',
                    'cardinality' => '1 → 5',
                    'slot'        => 'dominant_ideas.ideas',
                    'note'        => 'Les 5 idées dominantes du sujet actif — rechargées à chaque changement de sujet',
                ],
                [
                    'from'        => 'DominantIdeaSlot[]',
                    'to'          => 'active_dominant_idea',
                    'cardinality' => '5 → 1',
                    'slot'        => 'active_dominant_idea',
                    'note'        => 'Une seule idée active à la fois — pointeur vers dominant_ideas.ideas[i]',
                ],
                [
                    'from'        => 'active_dominant_idea',
                    'to'          => 'CognitiveSlot',
                    'cardinality' => '1 → 7',
                    'slot'        => 'cognitive_slots',
                    'note'        => '7 cognitifs générés pour la paire (sujet actif + idée active)',
                ],
                [
                    'from'        => 'CognitiveSlot',
                    'to'          => 'QuestionSlot',
                    'cardinality' => '1 → 1',
                    'slot'        => 'cognitive_slots.{variant}.question_slot',
                    'note'        => 'Question EN — seule langue source, remplie Phase1',
                ],
                [
                    'from'        => 'CognitiveSlot',
                    'to'          => 'AnswerSlot',
                    'cardinality' => '1 → 2|4',
                    'slot'        => 'cognitive_slots.{variant}.answer_slots',
                    'note'        => '4 réponses pour QCM, 2 pour TF — exactement 1 is_correct=true',
                ],
                [
                    'from'        => 'CognitiveSlot',
                    'to'          => 'SVSlot',
                    'cardinality' => '1 → 1',
                    'slot'        => 'cognitive_slots.{variant}.sv_slot',
                    'note'        => 'Saviez-vous EN — rempli Phase1',
                ],
                [
                    'from'        => 'CognitiveSlot',
                    'to'          => 'TranslationSlot',
                    'cardinality' => '1 → 9',
                    'slot'        => 'cognitive_slots.{variant}.translation_slots',
                    'note'        => '9 langues cibles — {fr,es,de,it,pt,ru,zh,ar,el} — remplies Phase3',
                ],
            ],
            'total_cognitifs_per_noyau'     => self::COGNITIVE_COUNT,
            'total_translations_per_noyau'  => self::COGNITIVE_COUNT * count(self::TRANSLATION_LANGS),
            'total_subjects_inventory'      => self::SUBJECTS_INVENTORY_MAX,
            'total_ideas_per_subject'       => self::DOMINANT_IDEAS_MAX,
        ];
    }

    /**
     * rules — Index des règles de remplissage de chaque slot du noyau.
     */
    private function buildRules(): array
    {
        return [
            'kernel_code_format'              => 'yy-xx-xxx-xxx-xxx-zz',
            'kernel_code_builder'             => 'KernelRotationPlanner(yy-xx) + Taxonomy(xxx-xxx-xxx) + KEY_STRUCTURE(validation) + KLD(zz)',
            'kernel_code_frozen_after'        => 'KLD validation (kld_hash posé)',
            'subjects_inventory_max'          => self::SUBJECTS_INVENTORY_MAX,
            'dominant_ideas_max'              => self::DOMINANT_IDEAS_MAX,
            'dominant_ideas_scope'            => 'active_subject only — rechargé à chaque changement de sujet actif',
            'cognitive_count'                 => self::COGNITIVE_COUNT,
            'ready_bank_unit'                 => 'noyau_mere_encoded — pas une banque de cognitifs isolés',
            'frame_is_container_only'         => 'KernelFrameBuilder ne choisit rien — structure vide uniquement',
            'legacy_fields_preserved'         => 'kernel_core/variants/translation_constraints conservés pour compatibilité pipeline existant',

            // ── Hiérarchie des statuts ─────────────────────────────────────
            'statuses_hierarchy'              => [
                'kernel_level'   => 'statuses{} — 10 étapes pipeline (rotation/taxonomy/ks/kld/intent/phase1-4/ready_bank), chacune null|pending|ok|failed|skipped',
                'slot_level'     => 'chaque slot expose son propre status — depth_slot.status, domain_slot.status, question_slot.status, translation_slot.status, etc.',
                'slot_enum'      => ['EMPTY', 'FILLED', 'VALIDATED_OK', 'LOCKED', 'REJECTED', 'CORRECTION_NEEDED'],
                'locked_semantics' => 'LOCKED ≠ verrou définitif global. Certains slots sont LOCKED pour une étape donnée mais peuvent être réactivés par leur propriétaire officiel selon le cycle du noyau. Ex: active_subject devient LOCKED après activation, mais Taxonomy peut le changer quand les 5 idées sont épuisées.',
                'rule'           => 'kernel_level = avancement global du noyau ; slot_level = état local du slot. Les deux coexistent.',
            ],

            // ── Hiérarchie des traces ──────────────────────────────────────
            'traces_hierarchy'                => [
                'root_level'   => 'traces[] racine — historique chronologique global du noyau (tous composants)',
                'slot_level'   => 'chaque slot possède ses propres traces[] — décisions locales sur ce slot uniquement',
                'rule'         => 'les deux niveaux coexistent. Append-only. Aucune suppression ni modification d\'entrée existante.',
            ],

            // ── Graphe de dépendances (ordre de remplissage) ──────────────
            'dependency_graph'                => [
                'depth_slot'           => [],
                'domain_slot'          => ['depth_slot'],
                'sub_domain_slot'      => ['depth_slot', 'domain_slot'],
                'subjects_inventory'   => ['sub_domain_slot'],
                'active_subject'       => ['subjects_inventory'],
                'dominant_ideas'       => ['active_subject'],
                'active_dominant_idea' => ['dominant_ideas'],
                'kernel_code'          => ['depth_slot', 'domain_slot', 'sub_domain_slot', 'active_subject', 'active_dominant_idea'],
                'QuestionIntent'       => ['active_subject', 'active_dominant_idea'],
                'Phase1'               => ['QuestionIntent'],
                'Phase2'               => ['Phase1'],
                'Phase3'               => ['Phase2'],
                'Phase4'               => ['Phase3'],
                'READY_BANK'           => ['Phase4'],
            ],
        ];
    }

    /**
     * mechanisms — Qui remplit quoi et quand (11 étapes pipeline).
     */
    private function buildMechanisms(): array
    {
        return [
            'step_1_blueprint_frame'   => 'KernelFrameBuilder — crée le contenant vide (ce fichier)',
            'step_2_depth_domain'      => 'KernelRotationPlanner (DepthNeedMatrix + DomainCycle) — remplit depth_slot + domain_slot + début kernel_code yy-xx',
            'step_3_taxonomy'          => 'TaxonomyReader via KernelRotationPlanner — remplit sub_domain_slot + subjects_inventory + active_subject + dominant_ideas + active_dominant_idea + milieu kernel_code xxx-xxx-xxx',
            'step_4_key_structure'     => 'IntentKeyBuilder.KEY_STRUCTURE — valide égrainage + cohérence + ks_hash + précode yy-xx-xxx-xxx-xxx',
            'step_5_kld'               => 'IntentKeyBuilder.KLD — anti-doublon directionnel + kld_hash + suffixe zz + verrouille kernel_code complet',
            'step_6_question_intent'   => 'QuestionIntent — verrouille intent_key + semantic_key + prépare 7 variant_keys',
            'step_7_phase1'            => 'KernelContentBuilder — remplit cognitive_slots (question_slot + answer_slots + sv_slot EN)',
            'step_8_phase2'            => 'KernelFrameValidator + VariantAlignmentChecker — valide contenu EN',
            'step_9_phase3'            => 'KernelTranslator — remplit translation_slots des cognitive_slots (9 langues)',
            'step_10_phase4'           => 'KernelTranslator validation — vérifie qualité traductions',
            'step_11_ready_bank'       => 'KernelExporter — post_review_status = ready_bank (noyau mère encodé complet)',
        ];
    }

    /**
     * constraints — Invariants non négociables du noyau mère.
     */
    private function buildConstraints(): array
    {
        return [
            'frame_builder_no_selection'            => true,
            'frame_builder_no_question_generation'  => true,
            'dominant_ideas_only_for_active_subject' => true,
            'kernel_code_immutable_after_kld'        => true,
            'ready_bank_stores_encoded_noyau'        => true,
            'no_isolated_cognitive_in_ready_bank'    => true,
            'cognitive_consumed_by_gameplay'         => 'Gameplay consomme les cognitifs internes — READY_BANK stocke le noyau entier',
            'backward_compat_variants_preserved'     => 'Ne pas supprimer kernel_core/variants tant que migration non complète',
        ];
    }

    /**
     * statuses — Statut pipeline de chaque composant (10 étapes).
     *
     * created_by    : KernelFrameBuilder (tous null)
     * updated_by    : chaque composant met à jour SA clé uniquement
     * read_by       : KernelExporter, READY_BANK, tous les composants
     * write_access  : chaque composant écrit UNIQUEMENT sa propre clé
     * locked_after  : ready_bank = 'validated_ok' (noyau complet)
     * forbidden     : Un composant ne peut pas modifier le statut d'un autre composant.
     * expected_values: null | 'pending' | 'ok' | 'failed' | 'skipped'
     */
    private function buildStatuses(): array
    {
        return [
            'rotation'               => null,
            'taxonomy'               => null,
            'key_structure'          => null,
            'kld'                    => null,
            'question_intent'        => null,
            'phase1_content'         => null,
            'phase2_validation'      => null,
            'phase3_translation'     => null,
            'phase4_translation_val' => null,
            'ready_bank'             => null,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Kernel Core Legacy — conservé pour compatibilité pipeline existant
    // ═════════════════════════════════════════════════════════════════════════

    private function buildKernelCore(QuestionIntent $intent, string $band): array
    {
        return [
            'domain'               => $intent->domain,
            'sub_domain'           => $intent->sub_domain,
            'difficulty_depth'     => (int) $intent->difficulty_depth,
            'default_reading_band' => $band,
            'concept_family'       => $intent->concept_family,
            'semantic_key'         => $intent->semantic_key,
            'subject'              => $intent->subject,
            'angle_large'          => $intent->angle_large,
            'micro_angle'          => $intent->micro_angle,
            'answer_target'        => $intent->answer_target,
            'potential_trap'       => $intent->potential_trap,
            'pedagogical_intent'   => null,
        ];
    }

    private function buildTranslationConstraints(string $band): array
    {
        $constraints = [];

        foreach (self::TRANSLATION_LANGS as $lang) {
            $constraints[$lang] = [
                'question_max_length' => $this->qMax($lang, $band),
                'answer_max_length'   => $this->aMax($lang),
                'funFact_max_length'  => $this->svMax($lang),
                'funFact_min_length'  => self::SV_MIN,
            ];
        }

        return $constraints;
    }

    private function qMax(string $lang, string $band): int
    {
        return ReadingBandConfig::resolveForLang($band, $lang)['soft'];
    }

    private function aMax(string $lang): int
    {
        return match ($lang) {
            'zh'    => self::A_MAX_ZH,
            'ar'    => self::A_MAX_AR,
            default => self::A_MAX,
        };
    }

    private function svMax(string $lang): int
    {
        return match ($lang) {
            'zh'    => self::SV_MAX_ZH,
            'ar'    => self::SV_MAX_AR,
            default => self::SV_MAX,
        };
    }

    private function buildVariants(): array
    {
        $variants = [];

        foreach (self::VARIANTS as $variantKey => [$questionType, $cognitiveType]) {
            $variants[$variantKey] = $this->buildVariant($variantKey, $questionType, $cognitiveType);
        }

        return $variants;
    }

    private function buildVariant(string $variantKey, string $questionType, string $cognitiveType): array
    {
        return [
            'question_type'         => $questionType,
            'cognitive_type'        => $cognitiveType,
            'reading_band_override' => ReadingBandConfig::defaultForVariant($variantKey),
            'question_text'         => null,
            'answer_a'              => null,
            'answer_b'              => null,
            'answer_c'              => null,
            'answer_d'              => null,
            'correct_answer_key'    => null,
            'explanation'           => null,
            'saviez_vous'           => null,
            'cognitive_contract'    => $this->buildCognitiveContract($variantKey),
            'gameplay_constraints'  => $this->buildGameplayConstraints($questionType),
            'translation_slots'     => $this->buildTranslationSlots(),
            'status'                => 'EMPTY',
        ];
    }

    private function buildCognitiveContract(string $variantKey): array
    {
        $contracts = [
            'qcm_recognition'      => ['type' => 'recognition', 'trap_allowed' => false, 'min_wrong_answers' => 3],
            'qcm_reasoning'        => ['type' => 'reasoning',   'trap_allowed' => false, 'min_wrong_answers' => 3],
            'qcm_deceptive_trap'   => ['type' => 'deceptive',   'trap_allowed' => true,  'min_wrong_answers' => 3],
            'tf_recognition_true'  => ['type' => 'recognition', 'expected_truth' => true,  'binary' => true],
            'tf_recognition_false' => ['type' => 'recognition', 'expected_truth' => false, 'binary' => true],
            'tf_reasoning_true'    => ['type' => 'reasoning',   'expected_truth' => true,  'binary' => true],
            'tf_reasoning_false'   => ['type' => 'reasoning',   'expected_truth' => false, 'binary' => true],
        ];

        return $contracts[$variantKey] ?? [];
    }

    private function buildGameplayConstraints(string $questionType): array
    {
        return [
            'display_mode'   => $questionType === 'true_false' ? 'binary' : 'quad',
            'time_limit_sec' => 30,
            'buzz_eligible'  => true,
        ];
    }

    private function buildTranslationSlots(): array
    {
        $slots = [];

        foreach (self::TRANSLATION_LANGS as $lang) {
            $slots[$lang] = [
                'question_text'      => null,
                'answer_a'           => null,
                'answer_b'           => null,
                'answer_c'           => null,
                'answer_d'           => null,
                'correct_answer_key' => null,
                'explanation'        => null,
                'saviez_vous'        => null,
                'status'             => 'EMPTY',
            ];
        }

        return $slots;
    }
}
