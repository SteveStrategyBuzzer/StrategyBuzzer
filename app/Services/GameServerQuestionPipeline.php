<?php

namespace App\Services;

use App\Jobs\GenerateGameServerQuestionsJob;
use App\Services\QuestionBank\QuestionBankPicker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GameServerQuestionPipeline
{
    private const CACHE_TTL = 1800; // 30 minutes - Cache is accelerator only
    private const QUESTIONS_PER_ROUND = 10;
    private const BONUS_SKILL_QUESTIONS = 5;
    private const TIEBREAKER_QUESTIONS = 5;
    private const DEFAULT_BLOCK_SIZE = 4;

    private FirebaseService $firebase;
    private MatchQuestionPlanner $planner;
    private QuestionBankPicker $bankPicker;

    public function __construct()
    {
        // #88: live gameplay never instantiates QuestionService here — the
        // off-plan path now uses the bank picker directly + a deterministic
        // stub. No HTTP path can be reached from this pipeline.
        $this->firebase = FirebaseService::getInstance();
        $this->planner = new MatchQuestionPlanner();
        $this->bankPicker = app(QuestionBankPicker::class);
    }

    private function getCacheKey(string $roomId, string $suffix): string
    {
        return "game_server_match:{$roomId}:{$suffix}";
    }

    private function getFirestoreDocPath(string $roomId): string
    {
        return "questionPools/{$roomId}";
    }

    private function getFirestoreItemsPath(string $roomId): string
    {
        return "questionPools/{$roomId}/items";
    }

    public function getTotalNeeded(int $maxRounds): int
    {
        return ($maxRounds * self::QUESTIONS_PER_ROUND) 
            + self::BONUS_SKILL_QUESTIONS 
            + self::TIEBREAKER_QUESTIONS;
    }

    public function initMatch(string $roomId, string $theme, int $niveau, string $language, int $maxRounds): ?array
    {
        Log::info('[GameServerQuestionPipeline] Initializing match', [
            'room_id' => $roomId,
            'theme' => $theme,
            'niveau' => $niveau,
            'language' => $language,
            'max_rounds' => $maxRounds,
        ]);

        $totalNeeded = $this->getTotalNeeded($maxRounds);

        // Construit UN plan unique pour la partie. Tous les joueurs présents
        // dans la salle reçoivent exactement la même séquence de slots —
        // groupes (rendus à la volée via renderGroupForLanguage), ou
        // questions seed pré-formatées si la banque ne couvre pas un slot.
        // Aucun appel IA synchrone n'est fait sur un slot du plan.
        $planResult = $this->buildMatchPlanFor($roomId, $theme, $niveau, $language, $maxRounds);
        $orderedGroupIds = $planResult['ordered_group_ids'] ?? [];
        $orderedPlanSlots = $planResult['ordered_plan_slots'] ?? [];
        $planId = $planResult['plan_id'] ?? null;

        $config = [
            'theme' => $theme,
            'niveau' => $niveau,
            'language' => $language,
            'maxRounds' => $maxRounds,
            'totalNeeded' => $totalNeeded,
            'planId' => $planId,
            'orderedGroupIds' => $orderedGroupIds,
            'orderedPlanSlots' => $orderedPlanSlots,
        ];

        $poolData = [
            'config' => $config,
            'usedIds' => [],
            'usedTextHashes' => [],
            'nextIndex' => 2,
            'createdAt' => microtime(true),
        ];
        
        $firestoreWriteSuccess = $this->firebase->createDocument('questionPools', $roomId, $poolData);
        
        if (!$firestoreWriteSuccess) {
            Log::warning('[GameServerQuestionPipeline] Failed to write to Firestore, continuing with cache only', [
                'room_id' => $roomId,
            ]);
        }

        Cache::put($this->getCacheKey($roomId, 'used_ids'), [], self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'used_texts'), [], self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'questions'), [], self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'next_index'), 2, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'config'), [
            'theme' => $theme,
            'niveau' => $niveau,
            'language' => $language,
            'max_rounds' => $maxRounds,
            'total_needed' => $totalNeeded,
            'plan_id' => $planId,
            'ordered_group_ids' => $orderedGroupIds,
            'ordered_plan_slots' => $orderedPlanSlots,
        ], self::CACHE_TTL);

        // 1. Question 1 : on rend la question 1 depuis le plan (slot complet).
        $firstQuestion = $this->renderFromPlanOrFallback(
            $orderedPlanSlots,
            1,
            $language,
            $theme,
            $niveau,
            [],
            []
        );

        if (!$firstQuestion) {
            Log::error('[GameServerQuestionPipeline] Failed to generate first question', [
                'room_id' => $roomId,
            ]);
            return null;
        }

        $formattedQuestion = $this->formatQuestion($firstQuestion, 1);
        
        $this->addUsedQuestion($roomId, $formattedQuestion['id'], $formattedQuestion['text']);
        
        $this->storeQuestionToFirestore($roomId, 1, $formattedQuestion);
        
        Cache::put($this->getCacheKey($roomId, 'questions'), [$formattedQuestion], self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'delivered_count'), 1, self::CACHE_TTL);

        Log::info('[GameServerQuestionPipeline] First question generated, dispatching block job', [
            'room_id' => $roomId,
            'question_id' => $formattedQuestion['id'],
        ]);

        GenerateGameServerQuestionsJob::dispatch(
            $roomId,
            $theme,
            $niveau,
            $language,
            $totalNeeded,
            2,
            self::DEFAULT_BLOCK_SIZE
        );

        return $formattedQuestion;
    }

    public function generateNextBlock(string $roomId, int $blockSize = 4): int
    {
        $config = $this->getMatchConfig($roomId);
        if (!$config) {
            Log::warning('[GameServerQuestionPipeline] No config found for room', ['room_id' => $roomId]);
            return 0;
        }

        $nextIndex = $this->getNextIndex($roomId);
        $usedIds = $this->getUsedQuestionIds($roomId);
        $usedTexts = $this->getUsedTextHashes($roomId);
        $questions = $this->getAllQuestionsFromStore($roomId);

        $totalNeeded = $config['total_needed'] ?? $config['totalNeeded'] ?? 0;
        $endIndex = min($nextIndex + $blockSize - 1, $totalNeeded);
        $generatedCount = 0;

        Log::info('[GameServerQuestionPipeline] Generating block', [
            'room_id' => $roomId,
            'start_index' => $nextIndex,
            'end_index' => $endIndex,
            'block_size' => $blockSize,
        ]);

        // On lit en priorité les slots COMPLETS du plan ; on garde
        // ordered_group_ids comme source de compatibilité ascendante (anciens
        // configs de salles déjà actives qui n'ont pas encore les slots).
        $orderedPlanSlots = $config['ordered_plan_slots'] ?? [];
        $orderedGroupIds = $config['ordered_group_ids'] ?? [];

        for ($questionNumber = $nextIndex; $questionNumber <= $endIndex; $questionNumber++) {
            try {
                // Chemin nominal : on rend la question N depuis le plan partagé.
                // Si le slot existe (= N est dans la longueur du plan), on
                // sert STRICTEMENT depuis le plan — groupe rendu dans la
                // langue, ou question seed pré-rendue, ou stub. Aucun appel
                // IA n'est jamais fait sur un slot couvert par le plan.
                // Le fallback historique (qui peut appeler l'IA) ne se
                // déclenche QUE pour les indices hors plan : bonus skill et
                // tiebreaker, qui ne sont pas planifiés par le planner.
                $question = $this->renderFromPlanOrFallback(
                    !empty($orderedPlanSlots) ? $orderedPlanSlots : $orderedGroupIds,
                    $questionNumber,
                    $config['language'],
                    $config['theme'],
                    $config['niveau'] ?? $config['level'] ?? 1,
                    $usedIds,
                    $usedTexts
                );

                if ($question) {
                    $formattedQuestion = $this->formatQuestion($question, $questionNumber);
                    
                    $usedIds[] = $formattedQuestion['id'];
                    $textHash = md5($formattedQuestion['text']);
                    $usedTexts[] = $textHash;
                    $questions[] = $formattedQuestion;
                    $generatedCount++;

                    $this->storeQuestionToFirestore($roomId, $questionNumber, $formattedQuestion);

                    Log::debug('[GameServerQuestionPipeline] Generated question', [
                        'room_id' => $roomId,
                        'question_number' => $questionNumber,
                        'question_id' => $formattedQuestion['id'],
                    ]);
                }

                usleep(50000);
            } catch (\Exception $e) {
                Log::error('[GameServerQuestionPipeline] Failed to generate question', [
                    'room_id' => $roomId,
                    'question_number' => $questionNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->updatePoolMetadata($roomId, [
            'usedIds' => $usedIds,
            'usedTextHashes' => $usedTexts,
            'nextIndex' => $endIndex + 1,
        ]);

        Cache::put($this->getCacheKey($roomId, 'used_ids'), $usedIds, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'used_texts'), $usedTexts, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'questions'), $questions, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'next_index'), $endIndex + 1, self::CACHE_TTL);

        Log::info('[GameServerQuestionPipeline] Block generation completed', [
            'room_id' => $roomId,
            'generated_count' => $generatedCount,
            'total_ready' => count($questions),
            'next_index' => $endIndex + 1,
        ]);

        return $generatedCount;
    }

    public function getNextQuestions(string $roomId, int $count = 4): array
    {
        $questions = $this->getAllQuestionsFromStore($roomId);
        $deliveredKey = $this->getCacheKey($roomId, 'delivered_count');
        $deliveredCount = (int) Cache::get($deliveredKey, 1);
        $slice = array_slice($questions, $deliveredCount, $count);

        Cache::put($deliveredKey, $deliveredCount + count($slice), self::CACHE_TTL);

        return $slice;
    }

    public function getQuestionByIndex(string $roomId, int $index): ?array
    {
        $cacheKey = $this->getCacheKey($roomId, "question_{$index}");
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $questions = Cache::get($this->getCacheKey($roomId, 'questions'), []);
        if (isset($questions[$index])) {
            return $questions[$index];
        }

        $question = $this->getQuestionFromFirestore($roomId, $index + 1);
        if ($question) {
            Cache::put($cacheKey, $question, self::CACHE_TTL);
        }
        
        return $question;
    }

    public function getQuestionCount(string $roomId): int
    {
        $questions = $this->getAllQuestionsFromStore($roomId);
        
        return count($questions);
    }

    public function addUsedQuestion(string $roomId, string $questionId, string $questionText): void
    {
        $usedIds = $this->getUsedQuestionIds($roomId);
        $usedTexts = $this->getUsedTextHashes($roomId);

        if (!in_array($questionId, $usedIds)) {
            $usedIds[] = $questionId;
        }

        $textHash = md5($questionText);
        if (!in_array($textHash, $usedTexts)) {
            $usedTexts[] = $textHash;
        }

        $this->updatePoolMetadata($roomId, [
            'usedIds' => $usedIds,
            'usedTextHashes' => $usedTexts,
        ]);

        Cache::put($this->getCacheKey($roomId, 'used_ids'), $usedIds, self::CACHE_TTL);
        Cache::put($this->getCacheKey($roomId, 'used_texts'), $usedTexts, self::CACHE_TTL);
    }

    public function getUsedQuestionIds(string $roomId): array
    {
        $cached = Cache::get($this->getCacheKey($roomId, 'used_ids'));
        if ($cached !== null) {
            return $cached;
        }

        $poolData = $this->getPoolDataFromFirestore($roomId);
        $usedIds = $poolData['usedIds'] ?? [];
        
        Cache::put($this->getCacheKey($roomId, 'used_ids'), $usedIds, self::CACHE_TTL);
        
        return $usedIds;
    }

    public function cleanup(string $roomId): void
    {
        Log::info('[GameServerQuestionPipeline] Cleaning up match', ['room_id' => $roomId]);

        $this->deletePoolFromFirestore($roomId);

        Cache::forget($this->getCacheKey($roomId, 'used_ids'));
        Cache::forget($this->getCacheKey($roomId, 'used_texts'));
        Cache::forget($this->getCacheKey($roomId, 'questions'));
        Cache::forget($this->getCacheKey($roomId, 'delivered_count'));
        Cache::forget($this->getCacheKey($roomId, 'next_index'));
        Cache::forget($this->getCacheKey($roomId, 'config'));
    }

    public function getMatchConfig(string $roomId): ?array
    {
        $cached = Cache::get($this->getCacheKey($roomId, 'config'));
        if ($cached !== null) {
            return $cached;
        }

        $poolData = $this->getPoolDataFromFirestore($roomId);
        if (!$poolData || !isset($poolData['config'])) {
            return null;
        }

        $config = $poolData['config'];
        $normalizedConfig = [
            'theme' => $config['theme'] ?? '',
            'niveau' => $config['niveau'] ?? 1,
            'language' => $config['language'] ?? 'fr',
            'max_rounds' => $config['maxRounds'] ?? $config['max_rounds'] ?? 3,
            'total_needed' => $config['totalNeeded'] ?? $config['total_needed'] ?? 0,
        ];
        
        Cache::put($this->getCacheKey($roomId, 'config'), $normalizedConfig, self::CACHE_TTL);
        
        return $normalizedConfig;
    }

    public function shouldGenerateMore(string $roomId): bool
    {
        $config = $this->getMatchConfig($roomId);
        if (!$config) {
            return false;
        }

        $nextIndex = $this->getNextIndex($roomId);
        $totalNeeded = $config['total_needed'] ?? 0;
        
        return $nextIndex <= $totalNeeded;
    }

    private function formatQuestion(array $question, int $questionNumber): array
    {
        // Map PHP/question-api type strings to game server constants
        $phpType = $question['type'] ?? 'multiple';
        $gsType  = match ($phpType) {
            'true_false' => 'TRUE_FALSE',
            'text'       => 'TEXT',
            default      => 'MCQ',   // 'multiple' → 'MCQ'
        };

        // Filter null/empty answers and re-map correct index accordingly
        $rawAnswers     = $question['answers'] ?? [];
        $rawCorrectIdx  = (int) ($question['correct_id'] ?? $question['correct_index'] ?? 0);
        $choices        = [];
        $correctIndex   = 0;
        $newIdx         = 0;
        foreach ($rawAnswers as $oldIdx => $answer) {
            if ($answer !== null && $answer !== '') {
                if ((int) $oldIdx === $rawCorrectIdx) {
                    $correctIndex = $newIdx;
                }
                $choices[] = $answer;
                $newIdx++;
            }
        }

        return [
            'id'           => $question['id'] ?? uniqid('gsq_'),
            'number'       => $questionNumber,
            'text'         => $question['question_text'] ?? $question['text'] ?? '',
            // camelCase for TypeScript/game-server consumption
            'choices'      => $choices,
            'correctIndex' => $correctIndex,
            'type'         => $gsType,
            'funFact'      => $question['explanation'] ?? null,
            'subCategory'  => $question['sub_theme'] ?? '',
            'category'     => $question['theme'] ?? '',
            'difficulty'   => 3,
            'timeLimitMs'  => 8000,
            // snake_case aliases kept for any PHP consumers
            'answers'      => $choices,
            'correct_index' => $correctIndex,
            'fun_fact'     => $question['explanation'] ?? null,
        ];
    }

    private function getNextIndex(string $roomId): int
    {
        $cached = Cache::get($this->getCacheKey($roomId, 'next_index'));
        if ($cached !== null) {
            return (int)$cached;
        }

        $poolData = $this->getPoolDataFromFirestore($roomId);
        $nextIndex = $poolData['nextIndex'] ?? 1;
        
        Cache::put($this->getCacheKey($roomId, 'next_index'), $nextIndex, self::CACHE_TTL);
        
        return (int)$nextIndex;
    }

    private function getUsedTextHashes(string $roomId): array
    {
        $cached = Cache::get($this->getCacheKey($roomId, 'used_texts'));
        if ($cached !== null) {
            return $cached;
        }

        $poolData = $this->getPoolDataFromFirestore($roomId);
        $usedTexts = $poolData['usedTextHashes'] ?? [];
        
        Cache::put($this->getCacheKey($roomId, 'used_texts'), $usedTexts, self::CACHE_TTL);
        
        return $usedTexts;
    }

    private function getAllQuestionsFromStore(string $roomId): array
    {
        $cached = Cache::get($this->getCacheKey($roomId, 'questions'));
        if ($cached !== null && !empty($cached)) {
            return $cached;
        }

        $questions = $this->getAllQuestionsFromFirestore($roomId);
        
        if (!empty($questions)) {
            Cache::put($this->getCacheKey($roomId, 'questions'), $questions, self::CACHE_TTL);
        }
        
        return $questions;
    }

    private function storeQuestionToFirestore(string $roomId, int $questionIndex, array $question): bool
    {
        try {
            return $this->firebase->createDocument(
                $this->getFirestoreItemsPath($roomId),
                (string)$questionIndex,
                $question
            );
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to store question to Firestore', [
                'room_id' => $roomId,
                'question_index' => $questionIndex,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getQuestionFromFirestore(string $roomId, int $questionIndex): ?array
    {
        try {
            $items = $this->firebase->getCollection($this->getFirestoreItemsPath($roomId));
            return $items[(string)$questionIndex] ?? null;
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to get question from Firestore', [
                'room_id' => $roomId,
                'question_index' => $questionIndex,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getAllQuestionsFromFirestore(string $roomId): array
    {
        try {
            $items = $this->firebase->getCollection($this->getFirestoreItemsPath($roomId));
            
            uksort($items, function($a, $b) {
                return (int)$a - (int)$b;
            });
            
            return array_values($items);
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to get questions from Firestore', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function getPoolDataFromFirestore(string $roomId): ?array
    {
        try {
            return $this->firebase->getDocument('questionPools', $roomId);
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to get pool data from Firestore', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function updatePoolMetadata(string $roomId, array $updates): bool
    {
        try {
            $poolData = $this->getPoolDataFromFirestore($roomId);
            if (!$poolData) {
                return false;
            }

            $mergedData = array_merge($poolData, $updates);
            $mergedData['updatedAt'] = microtime(true);

            return $this->firebase->createDocument('questionPools', $roomId, $mergedData);
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to update pool metadata in Firestore', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function deletePoolFromFirestore(string $roomId): bool
    {
        try {
            $items = $this->firebase->getCollection($this->getFirestoreItemsPath($roomId));
            $deletedCount = 0;
            
            foreach (array_keys($items) as $itemId) {
                $deleted = $this->firebase->deleteDocument(
                    $this->getFirestoreItemsPath($roomId),
                    (string)$itemId
                );
                if ($deleted) {
                    $deletedCount++;
                }
            }
            
            $mainDeleted = $this->firebase->deleteDocument('questionPools', $roomId);
            
            Log::info('[GameServerQuestionPipeline] Pool cleanup completed', [
                'room_id' => $roomId,
                'items_deleted' => $deletedCount,
                'main_document_deleted' => $mainDeleted,
            ]);
            
            return $mainDeleted;
        } catch (\Exception $e) {
            Log::error('[GameServerQuestionPipeline] Failed to delete pool from Firestore', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Construit (ou tente de construire) un plan de match unique via
     * MatchQuestionPlanner. Si la combinaison (theme, niveau) n'est pas
     * supportée par les profils déclaratifs, retourne un résultat vide
     * et le pipeline retombera sur l'ancien chemin par-question.
     *
     * Le plan est persisté en BDD (table match_question_plans) avec son
     * match_id = roomId, ce qui garantit la propriété "même
     * ordered_group_ids pour tous les joueurs de la salle".
     *
     * @return array{plan_id: string|null, ordered_group_ids: array<int, int|null>, ordered_plan_slots: array<int, array>}
     */
    private function buildMatchPlanFor(
        string $roomId,
        string $theme,
        int $niveau,
        string $language,
        int $maxRounds
    ): array {
        try {
            $params = $this->derivePlannerParams($theme, $niveau, $maxRounds);
            if (!$params) {
                Log::info('[GameServerQuestionPipeline] no planner profile for params, falling back to per-question flow', [
                    'room_id' => $roomId,
                    'theme'   => $theme,
                    'niveau'  => $niveau,
                ]);
                return ['plan_id' => null, 'ordered_group_ids' => [], 'ordered_plan_slots' => []];
            }
            $plan = $this->planner->buildPlan(
                $params['mode'],
                $params['level_or_division'],
                $params['total'],
                $params['rounds'],
                $language,
                [
                    'domain'   => $params['domain'],
                    'match_id' => $roomId,
                ]
            );

            $orderedGroupIds = array_values(array_map(
                fn ($id) => (is_int($id) || ctype_digit((string) $id)) ? (int) $id : null,
                $plan['ordered_group_ids'] ?? []
            ));

            // On persiste les SLOTS COMPLETS du plan (groupes formatés OU
            // questions seed pré-rendues OU stubs de shortage), pas seulement
            // les ids de groupe. C'est cette structure qui sert de source de
            // vérité au runtime : tout slot couvert par le plan est servi
            // depuis le plan, JAMAIS via une génération IA en cours de partie.
            $orderedPlanSlots = array_values($plan['ordered_questions'] ?? []);

            Log::info('[GameServerQuestionPipeline] match plan built', [
                'room_id'        => $roomId,
                'plan_id'        => $plan['plan_id'] ?? null,
                'mode'           => $params['mode'],
                'level'          => $params['level_or_division'],
                'planned_count'  => count($orderedGroupIds),
                'slots_count'    => count($orderedPlanSlots),
                'shortages'      => count($plan['shortages'] ?? []),
            ]);

            return [
                'plan_id'            => $plan['plan_id'] ?? null,
                'ordered_group_ids'  => $orderedGroupIds,
                'ordered_plan_slots' => $orderedPlanSlots,
            ];
        } catch (\Throwable $e) {
            Log::warning('[GameServerQuestionPipeline] planner failed, falling back to per-question flow', [
                'room_id' => $roomId,
                'error'   => $e->getMessage(),
            ]);
            return ['plan_id' => null, 'ordered_group_ids' => [], 'ordered_plan_slots' => []];
        }
    }

    /**
     * Mappe (theme legacy, niveau, maxRounds) vers les inputs du planner :
     *  - mode (solo|boss)
     *  - level_or_division (int Solo, ou int boss)
     *  - total questions à planifier (= QUESTIONS_PER_ROUND × maxRounds ;
     *    bonus + tiebreaker restent gérés en hors-plan)
     *  - rounds
     *  - domain canonique
     *
     * Retourne null si aucun profil n'est applicable.
     *
     * @return array{mode:string, level_or_division:int, total:int, rounds:int, domain:string}|null
     */
    private function derivePlannerParams(string $theme, int $niveau, int $maxRounds): ?array
    {
        if ($niveau < 1 || $niveau > 100) {
            return null;
        }
        $mode = ($niveau % 10 === 0 && $niveau >= 10 && $niveau <= 100) ? 'boss' : 'solo';
        if ($mode === 'boss') {
            $bossLevels = array_keys(config('question_bank_profiles.boss_profiles', []));
            if (!in_array($niveau, $bossLevels, true)) {
                return null;
            }
        }
        $domain = $this->canonicalDomain($theme);
        $total = $maxRounds * self::QUESTIONS_PER_ROUND;
        return [
            'mode'              => $mode,
            'level_or_division' => $niveau,
            'total'             => $total,
            'rounds'            => $maxRounds,
            'domain'            => $domain,
        ];
    }

    private function canonicalDomain(string $theme): string
    {
        $allowed = config('question_bank_profiles.domains', ['general']);
        $normalized = strtolower(trim($theme));
        $aliases = [
            'culture générale'   => 'general',
            'culture generale'   => 'general',
            'general'            => 'general',
            'général'            => 'general',
            'histoire'           => 'histoire',
            'sport'              => 'sport',
            'sports'             => 'sport',
            'géographie'         => 'geographie',
            'geographie'         => 'geographie',
            'art'                => 'art',
            'cuisine'            => 'cuisine',
            'science'            => 'science',
            'sciences'           => 'science',
            'cinéma'             => 'cinema',
            'cinema'             => 'cinema',
            'faune'              => 'faune',
        ];
        $candidate = $aliases[$normalized] ?? $normalized;
        return in_array($candidate, $allowed, true) ? $candidate : 'general';
    }

    /**
     * Sert la question N pour la salle.
     *
     * Règle stricte (cf. cahier des charges du planner) :
     *   - SI le slot est COUVERT par le plan (= index dans la longueur du
     *     plan), on sert STRICTEMENT depuis le plan : groupe rendu dans la
     *     langue, ou question seed pré-rendue, ou stub de shortage. Aucun
     *     appel IA synchrone n'est jamais fait sur un slot du plan, même en
     *     cas de shortage — c'est exactement la garantie qu'on doit aux
     *     joueurs (pas d'attente IA pendant la partie).
     *   - SI l'index est HORS plan (typiquement bonus skill ou tiebreaker,
     *     que le planner ne planifie pas), on tente une pioche directe dans
     *     la banque persistante (QuestionBankPicker), puis on retombe sur
     *     un stub déterministe. AUCUN appel IA n'est fait ici (#88) — la
     *     banque alimentée par le worker offline est la seule source de
     *     contenu, et un stub garantit que la partie ne bloque jamais.
     *
     * Le tableau `$orderedSlots` peut être :
     *   - une liste de slots COMPLETS (format planner ordered_questions :
     *     groupes formatés / seed / stubs) — chemin nominal.
     *   - une liste d'IDs de groupe (compat ascendante : configs cache
     *     antérieures à la persistance des slots complets).
     */
    private function renderFromPlanOrFallback(
        array $orderedSlots,
        int $questionNumber,
        string $language,
        string $theme,
        int $niveau,
        array $usedIds,
        array $usedTexts
    ): ?array {
        $idx = $questionNumber - 1;
        $isPlannedSlot = array_key_exists($idx, $orderedSlots);

        if ($isPlannedSlot) {
            $slot = $orderedSlots[$idx];

            // Cas 1 — slot = ID de groupe (compat ascendante).
            if (is_int($slot) || (is_string($slot) && ctype_digit($slot))) {
                $rendered = $this->planner->renderGroupForLanguage((int) $slot, $language);
                if ($rendered) {
                    return $rendered;
                }
                // Le groupe a été supprimé / la traduction n'existe pas et
                // l'arborescence de fallback FR→EN n'a rien : on rend un
                // stub plutôt que d'appeler l'IA.
                Log::warning('[GameServerQuestionPipeline] plan group_id slot unrenderable, serving stub', [
                    'question_number' => $questionNumber,
                    'group_id'        => $slot,
                    'language'        => $language,
                ]);
                return $this->buildPlanStubPayload($questionNumber, $language);
            }

            // Cas 2 — slot = payload complet (format planner).
            if (is_array($slot)) {
                if (!empty($slot['group_id'])) {
                    // Re-rendu dans la langue du joueur (utile pour rooms
                    // multilingues : le slot a été initialement rendu dans la
                    // langue d'init, mais un autre joueur peut demander une
                    // autre langue).
                    $rendered = $this->planner->renderGroupForLanguage((int) $slot['group_id'], $language);
                    if ($rendered) {
                        return $rendered;
                    }
                    // Si le re-rendu échoue, on sert le slot tel quel
                    // (déjà formaté par le planner avec fallback FR→EN).
                    if (!empty($slot['question_text']) || !empty($slot['text'])) {
                        return $slot;
                    }
                    return $this->buildPlanStubPayload($questionNumber, $language);
                }

                // Slot seed pré-rendu, ou stub de shortage. Dans les deux
                // cas on le sert tel quel — on ne tente PAS l'IA.
                if (!empty($slot['from_seed']) || !empty($slot['question_text']) || !empty($slot['text'])) {
                    return $slot;
                }

                // Stub de shortage sans texte exploitable : on rend un stub
                // déterministe plutôt que d'appeler l'IA.
                return $this->buildPlanStubPayload($questionNumber, $language, $slot);
            }

            // Slot inattendu (null, etc.) couvert par le plan : on rend un
            // stub. L'IA reste interdite ici.
            return $this->buildPlanStubPayload($questionNumber, $language);
        }

        // Index HORS plan (bonus skill, tiebreaker, ou plan vide parce
        // qu'aucun profil ne s'applique). #88 : on n'appelle JAMAIS l'IA en
        // cours de partie. On tente le bank picker direct, puis on retombe
        // sur un stub déterministe — exactement comme un slot planifié non
        // rendable. Aucun chemin runtime ici ne peut produire un appel HTTP
        // sortant vers l'AI router.
        try {
            $bankPick = $this->bankPicker->pickOne(
                $theme,
                $niveau,
                $language,
                $usedIds
            );
            if (is_array($bankPick)) {
                return $bankPick;
            }
        } catch (\Throwable $e) {
            Log::warning('[GameServerQuestionPipeline] off-plan bank pick threw, serving stub (#88, no AI)', [
                'question_number' => $questionNumber,
                'language'        => $language,
                'error'           => $e->getMessage(),
            ]);
        }
        return $this->buildPlanStubPayload($questionNumber, $language);
    }

    /**
     * Construit un payload de question minimal et déterministe pour les
     * slots planifiés qu'on n'a pas pu rendre depuis la banque NI depuis le
     * seed pool. Sert à garantir qu'une partie ne bloque jamais sur un
     * slot du plan même dans le pire cas (banque vide + seed pool vide), et
     * cela SANS jamais appeler l'IA en cours de match.
     */
    private function buildPlanStubPayload(int $questionNumber, string $language, array $hint = []): array
    {
        $stubText = '[Question indisponible]';
        return [
            'id'             => 'plan_stub_' . $questionNumber,
            'group_id'       => null,
            'type'           => 'multiple',
            'question_text'  => $stubText,
            'text'           => $stubText,
            'answers'        => ['—', '—', '—', '—'],
            'correct_index'  => 0,
            'correct_id'     => 0,
            'explanation'    => null,
            'theme'          => $hint['sub_domain'] ?? 'general',
            'sub_theme'      => $hint['sub_domain'] ?? null,
            'cognitive_type' => $hint['cognitive_type'] ?? null,
            'language'       => $language,
            'shortage'       => true,
            'from_plan_stub' => true,
        ];
    }
}
