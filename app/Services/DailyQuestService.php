<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\User;
use App\Models\UserDailyQuest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyQuestService
{
    private const DAILY_IDS_MIN = 76;
    private const DAILY_IDS_MAX = 95;
    private const QUESTS_PER_DAY = 3;

    private CoinLedgerService $coinLedgerService;

    public function __construct(CoinLedgerService $coinLedgerService)
    {
        $this->coinLedgerService = $coinLedgerService;
    }

    // ─────────────────────────────────────────────────────────────────────
    // ROTATION — assignation / récupération
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Retourne les 3 quêtes quotidiennes actives pour aujourd'hui.
     * Si aucune n'a encore été assignée, on en sélectionne 3 au hasard
     * parmi les 20 disponibles.
     */
    public function getOrAssignDailyQuests(User $user): array
    {
        $today = Carbon::today()->toDateString();

        $existing = UserDailyQuest::where('user_id', $user->id)
            ->where('quest_date', $today)
            ->with('quest')
            ->get();

        if ($existing->count() >= self::QUESTS_PER_DAY) {
            return $this->formatDailyQuests($existing, $user);
        }

        // Assigner de nouvelles quêtes pour aujourd'hui
        $pool = Quest::whereBetween('id', [self::DAILY_IDS_MIN, self::DAILY_IDS_MAX])
            ->inRandomOrder()
            ->limit(self::QUESTS_PER_DAY)
            ->get();

        $created = [];
        foreach ($pool as $quest) {
            try {
                $record = UserDailyQuest::firstOrCreate([
                    'user_id'    => $user->id,
                    'quest_id'   => $quest->id,
                    'quest_date' => $today,
                ], [
                    'progress' => [],
                    'rewarded' => false,
                ]);
                $record->setRelation('quest', $quest);
                $created[] = $record;
            } catch (\Throwable $e) {
                Log::warning('DailyQuestService: failed to assign quest ' . $quest->id . ': ' . $e->getMessage());
            }
        }

        return $this->formatDailyQuests(collect($created), $user);
    }

    /**
     * Formate les enregistrements UserDailyQuest pour l'API / Inertia.
     */
    private function formatDailyQuests($records, User $user): array
    {
        return $records->map(function (UserDailyQuest $record) {
            $quest    = $record->quest;
            $params   = (array) ($quest->detection_params ?? []);
            $max      = (int) ($params['count'] ?? $params['amount'] ?? 1);
            $progress = (array) ($record->progress ?? []);
            $current  = (int) ($progress['current'] ?? 0);

            return [
                'id'            => $record->id,
                'quest_id'      => $quest->id,
                'name'          => $quest->name,
                'badge_emoji'   => $quest->badge_emoji,
                'badge_description' => $quest->badge_description,
                'reward_coins'  => $quest->reward_coins,
                'detection_code'=> $quest->detection_code,
                'rarity'        => $quest->rarity,
                'current'       => min($current, $max),
                'max'           => $max,
                'completed'     => $record->completed_at !== null,
                'rewarded'      => $record->rewarded,
            ];
        })->values()->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────
    // COMPLÉTION — vérification & récompense
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Vérifie et complète une quête quotidienne spécifique par detection_code.
     * Retourne true si la quête vient d'être complétée.
     */
    public function checkAndCompleteDailyQuest(User $user, string $detectionCode, array $context): bool
    {
        $today = Carbon::today()->toDateString();

        $record = UserDailyQuest::where('user_id', $user->id)
            ->where('quest_date', $today)
            ->whereHas('quest', fn($q) => $q->where('detection_code', $detectionCode))
            ->with('quest')
            ->first();

        if (!$record || $record->completed_at !== null) {
            return false;
        }

        return DB::transaction(function () use ($record, $context, $user) {
            $record = UserDailyQuest::where('id', $record->id)->lockForUpdate()->first();

            if ($record->completed_at !== null) {
                return false;
            }

            $record->load('quest');
            $quest  = $record->quest;
            $params = (array) ($quest->detection_params ?? []);
            $max    = (int) ($params['count'] ?? $params['amount'] ?? 1);

            $isCompleted = $this->evaluateDailyCondition($quest->detection_code, $record, $context, $max);

            if ($isCompleted) {
                $record->completed_at = now();
                $record->rewarded     = true;
                $record->save();

                // Créditer les pièces de Compétence via le ledger (idempotent)
                $rewardAmount = (int) ($quest->reward_coins ?? 0);
                if ($rewardAmount > 0) {
                    $this->coinLedgerService->creditOnce(
                        $user,
                        $rewardAmount,
                        'daily_quest_reward',
                        'UserDailyQuest',
                        $record->id,
                        'competence'
                    );
                }

                Log::info("DailyQuest completed: user={$user->id}, code={$quest->detection_code}, reward={$quest->reward_coins}");
                return true;
            }

            return false;
        });
    }

    /**
     * Déclenche la vérification sur toutes les quêtes quotidiennes actives
     * d'un utilisateur avec le contexte fourni.
     * Utilisé en fin de match ou lors d'une action.
     */
    public function fireDailyQuestChecks(User $user, array $context): array
    {
        $today   = Carbon::today()->toDateString();
        $records = UserDailyQuest::where('user_id', $user->id)
            ->where('quest_date', $today)
            ->whereNull('completed_at')
            ->with('quest')
            ->get();

        $completed = [];

        foreach ($records as $record) {
            try {
                $completed_now = $this->checkAndCompleteDailyQuest(
                    $user,
                    $record->quest->detection_code,
                    $context
                );
                if ($completed_now) {
                    $completed[] = $record->quest->detection_code;
                }
            } catch (\Throwable $e) {
                Log::warning('DailyQuestService::fireDailyQuestChecks error: ' . $e->getMessage());
            }
        }

        return $completed;
    }

    // ─────────────────────────────────────────────────────────────────────
    // ÉVALUATION des conditions — par detection_code
    // ─────────────────────────────────────────────────────────────────────

    private function evaluateDailyCondition(string $code, UserDailyQuest $record, array $context, int $max): bool
    {
        $data    = (array) ($record->progress ?? []);
        $current = (int) ($data['current'] ?? 0);

        switch ($code) {
            // ── Par réponse ──────────────────────────────────────────────

            case 'daily_first_buzz_3':
                // Être premier à buzzer $max fois dans la journée
                if ($context['first_buzz'] ?? false) {
                    $current++;
                }
                break;

            case 'daily_answer_fast_1_5sec':
                // Répondre en < 1.5 s
                $time = (float) ($context['answer_time'] ?? 999);
                if ($time < 1.5 && ($context['is_correct'] ?? false)) {
                    $current++;
                }
                break;

            case 'daily_finish_5_fast':
                // Finir 5 questions en < 3 s correctement
                $time = (float) ($context['answer_time'] ?? 999);
                if ($time < 3.0 && ($context['is_correct'] ?? false)) {
                    $current++;
                }
                break;

            case 'daily_use_skill':
                // Utiliser une compétence
                if ($context['skill_used'] ?? false) {
                    $current++;
                }
                break;

            case 'daily_play_general_5':
                // Répondre à 5 questions de thème "général"
                $theme = strtolower($context['theme'] ?? '');
                if (str_contains($theme, 'général') || str_contains($theme, 'general') || $theme === 'g') {
                    $current++;
                }
                break;

            // ── En fin de match ──────────────────────────────────────────

            case 'daily_wins_no_buzz':
                // Gagner sans buzzer ($max fois)
                if (($context['match_completed'] ?? false) && ($context['won'] ?? false) && ($context['total_buzzes'] ?? 1) === 0) {
                    $current++;
                }
                break;

            case 'daily_perfect_10':
                // Score parfait (100 % de bonnes réponses sur toute la partie)
                if (($context['match_completed'] ?? false) && ($context['perfect_score'] ?? false)) {
                    $current++;
                }
                break;

            case 'daily_different_theme':
                // Jouer un thème différent d'hier
                if ($context['match_completed'] ?? false) {
                    $yesterday  = Carbon::yesterday()->toDateString();
                    $todayTheme = $context['theme'] ?? '';
                    // Vérifier si l'utilisateur a joué ce thème hier
                    // Heuristique simple : si le thème est défini, on valide
                    if ($todayTheme !== '') {
                        $current = $max; // considéré accompli
                    }
                }
                break;

            case 'daily_evening_play':
                // Jouer en soirée (18h–23h)
                $hour = (int) ($context['match_hour'] ?? (int) Carbon::now()->format('G'));
                if (($context['match_completed'] ?? false) && $hour >= 18 && $hour < 23) {
                    $current = $max;
                }
                break;

            case 'daily_win_duo':
                // Gagner une partie Duo
                $mode = $context['mode'] ?? '';
                if (($context['match_completed'] ?? false) && ($context['won'] ?? false) && $mode === 'duo') {
                    $current++;
                }
                break;

            case 'daily_finish_custom_4plus':
                // Terminer un match custom avec 4+ thèmes joués (mode solo)
                $themesCount = (int) ($context['themes_count'] ?? 0);
                if (($context['match_completed'] ?? false) && $themesCount >= 4) {
                    $current++;
                }
                break;

            case 'daily_league_solo':
                // Jouer une partie Ligue ou Solo
                $mode = $context['mode'] ?? '';
                if (($context['match_completed'] ?? false) && in_array($mode, ['solo', 'league_individual', 'league_team'], true)) {
                    $current++;
                }
                break;

            // ── Actions utilisateur ──────────────────────────────────────

            case 'daily_invite_player':
                // Inviter un joueur (action déclenchée par le contrôleur)
                if ($context['player_invited'] ?? false) {
                    $current = $max;
                }
                break;

            case 'daily_change_avatar':
                // Changer d'avatar (action profil)
                if ($context['avatar_changed'] ?? false) {
                    $current = $max;
                }
                break;

            case 'daily_create_ai_question':
                // Créer une question IA
                if ($context['ai_question_created'] ?? false) {
                    $current = $max;
                }
                break;

            case 'daily_share_result':
                // Partager un résultat
                if ($context['result_shared'] ?? false) {
                    $current = $max;
                }
                break;

            case 'daily_visit_shop':
                // Visiter la boutique
                if ($context['visited_shop'] ?? false) {
                    $current = $max;
                }
                break;

            case 'daily_read_avatar_desc':
                // Lire la description d'un avatar
                if ($context['avatar_desc_read'] ?? false) {
                    $current = $max;
                }
                break;

            case 'daily_buy_item':
                // Acheter un article en boutique
                if ($context['shop_purchase'] ?? false) {
                    $current = $max;
                }
                break;

            case 'daily_help_player':
                // Aider un joueur (utiliser skill see_opponent_choice ou partage)
                if (($context['skill_slug'] ?? '') === 'see_opponent_choice' || ($context['helped_player'] ?? false)) {
                    $current = $max;
                }
                break;

            default:
                return false;
        }

        // Persister la progression
        $data['current']    = $current;
        $record->progress   = $data;
        $record->save();

        return $current >= $max;
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS — nettoyage
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Supprime les enregistrements de quêtes quotidiennes de plus de 7 jours.
     * Appelé par la commande daily:rotate.
     */
    public function pruneOldDailyQuests(int $keepDays = 7): int
    {
        return UserDailyQuest::where('quest_date', '<', Carbon::today()->subDays($keepDays)->toDateString())
            ->delete();
    }
}
