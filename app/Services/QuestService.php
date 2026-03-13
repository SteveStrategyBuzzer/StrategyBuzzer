<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\UserQuestProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuestService
{
    /**
     * Point d'entrée unifié pour les événements de fin de match.
     * Charge toutes les quêtes auto_complete=true non complétées en une seule requête
     * et les vérifie contre le contexte fourni.
     *
     * @param User   $user
     * @param string $mode    'solo' | 'duo' | 'league_individual'
     * @param array  $context Données du match :
     *   - won                (bool)   victoire ?
     *   - total_questions    (int)    questions jouées
     *   - user_correct       (int)    bonnes réponses joueur
     *   - opponent_score     (int)    score adversaire (pour perfect_10_0, comeback)
     *   - theme              (string) thème joué
     *   - skills_used        (int)    compétences utilisées dans ce match
     *   - lives_remaining    (int)    vies restantes (Solo)
     *   - had_timeout        (bool)   y-a-t-il eu des questions non répondues ?
     *   - boss_defeated      (bool)   boss vaincu ce match ?
     *   - division           (string) division actuelle après le match
     *   - user_level         (int)    niveau actuel
     *   - user_coins         (int)    solde actuel de pièces de Compétence
     *   - themes_played      (array)  thèmes uniques joués (pour themes_5/10)
     *   - player_score       (int)    score joueur dans ce match
     * @return array  Quêtes complétées
     */
    public function fireMatchEndQuests(User $user, string $mode, array $context): array
    {
        $context['mode'] = $mode;

        $alreadyCompleted = UserQuestProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('rewarded', true)
            ->pluck('quest_id')
            ->toArray();

        $quests = Quest::where('auto_complete', true)
            ->whereNotIn('id', empty($alreadyCompleted) ? [0] : $alreadyCompleted)
            ->get();

        $completedQuests = [];

        foreach ($quests as $quest) {
            $completed = DB::transaction(function () use ($user, $quest, $context) {
                $progress = UserQuestProgress::firstOrCreate(
                    ['user_id' => $user->id, 'quest_id' => $quest->id],
                    ['progress' => [], 'rewarded' => false]
                );
                $progress = UserQuestProgress::where('id', $progress->id)->lockForUpdate()->first();

                if ($progress->completed_at !== null && $progress->rewarded) {
                    return false;
                }

                $isCompleted = $this->isQuestConditionMet($quest, $progress, $context);

                if ($isCompleted) {
                    $progress->completed_at = now();
                    $progress->rewarded = true;
                    $progress->save();

                    $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                    if ($lockedUser) {
                        $lockedUser->competence_coins = ($lockedUser->competence_coins ?? 0) + $quest->reward_coins;
                        $lockedUser->save();
                    }

                    return true;
                }

                return false;
            });

            if ($completed) {
                $completedQuests[] = $quest;
            }
        }

        return $completedQuests;
    }

    /**
     * Vérifier et compléter les quêtes basées sur un événement spécifique.
     * Utilisé pour les événements par action (achat, changement d'avatar, etc.)
     * et pour les hooks par réponse (buzz rapide, streak correct, etc.)
     */
    public function checkAndCompleteQuests(User $user, string $eventCode, array $context = []): array
    {
        $completedQuests = [];

        $quests = Quest::where('detection_code', $eventCode)
            ->where('auto_complete', true)
            ->get();

        foreach ($quests as $quest) {
            $completed = DB::transaction(function () use ($user, $quest, $context) {
                $progress = UserQuestProgress::firstOrCreate(
                    ['user_id' => $user->id, 'quest_id' => $quest->id],
                    ['progress' => [], 'rewarded' => false]
                );

                $progress = UserQuestProgress::where('id', $progress->id)->lockForUpdate()->first();

                if ($progress->completed_at !== null && $progress->rewarded) {
                    return false;
                }

                $isCompleted = $this->isQuestConditionMet($quest, $progress, $context);

                if ($isCompleted) {
                    $progress->completed_at = now();
                    $progress->rewarded = true;
                    $progress->save();

                    $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
                    if ($lockedUser) {
                        $lockedUser->competence_coins = ($lockedUser->competence_coins ?? 0) + $quest->reward_coins;
                        $lockedUser->save();
                    }

                    return true;
                }

                return false;
            });

            if ($completed) {
                $completedQuests[] = $quest;
            }
        }

        return $completedQuests;
    }

    /**
     * Vérifier si les conditions de la quête sont remplies.
     * Toutes les mises à jour de progression (compteurs JSON) se font ici,
     * à l'intérieur de la transaction atomique de l'appelant.
     */
    protected function isQuestConditionMet(Quest $quest, UserQuestProgress $progress, array $context): bool
    {
        $params = is_array($quest->detection_params) ? $quest->detection_params : [];
        $code   = $quest->detection_code;

        switch ($code) {

            // ─────────────────────────────────────────────────────────────
            // ONE-SHOT : première fois / condition unique du match
            // ─────────────────────────────────────────────────────────────

            case 'first_match_10q':
                return ($context['match_completed'] ?? false) === true
                    && ($context['total_questions'] ?? 0) >= 10;

            case 'perfect_score':
                return isset($context['user_correct'], $context['total_questions'])
                    && $context['total_questions'] >= 10
                    && $context['user_correct'] === $context['total_questions']
                    && ($context['won'] ?? false);

            case 'win_with_1_life':
                return ($context['won'] ?? false)
                    && ($context['lives_remaining'] ?? 99) === 1;

            case 'lose_3_win_4th':
                $data   = $progress->progress ?? [];
                $losses = $data['consecutive_losses'] ?? 0;

                if (!($context['won'] ?? false)) {
                    $data['consecutive_losses'] = $losses + 1;
                    $progress->progress = $data;
                    $progress->save();
                    return false;
                }

                $eligible = $losses >= 3;
                $data['consecutive_losses'] = 0;
                $progress->progress = $data;
                $progress->save();
                return $eligible;

            case 'round_no_timeout':
                return ($context['won'] ?? false)
                    && ($context['had_timeout'] ?? true) === false;

            case 'win_no_sound':
                return ($context['won'] ?? false)
                    && ($context['sound_disabled'] ?? false) === true;

            case 'play_early_morning':
                $hour = (int) Carbon::now()->format('G');
                return ($context['match_completed'] ?? ($context['won'] ?? false))
                    && $hour >= 6 && $hour < 9;

            case 'play_late_night':
                $hour = (int) Carbon::now()->format('G');
                return ($context['match_completed'] ?? ($context['won'] ?? false))
                    && ($hour >= 22 || $hour < 0);

            case 'night_owl':
                $hour = (int) Carbon::now()->format('G');
                return ($context['match_completed'] ?? ($context['won'] ?? false))
                    && ($hour >= 0 && $hour < 6);

            case 'perfect_10_0':
                $playerScore   = $context['player_score'] ?? 0;
                $opponentScore = $context['opponent_score'] ?? 1;
                return ($context['won'] ?? false)
                    && $opponentScore === 0
                    && $playerScore > 0;

            case 'comeback_0_5':
                $deficit = (int) ($params['deficit'] ?? 5);
                return ($context['won'] ?? false)
                    && ($context['max_deficit_recovered'] ?? 0) >= $deficit;

            case 'win_40q_no_skill':
                return ($context['won'] ?? false)
                    && ($context['total_questions'] ?? 0) >= 40
                    && ($context['skills_used'] ?? 1) === 0;

            case 'foreign_language_60':
                $theme = strtolower($context['theme'] ?? '');
                if (strpos($theme, 'langue') === false && strpos($theme, 'language') === false) {
                    return false;
                }
                $total   = $context['total_questions'] ?? 0;
                $correct = $context['user_correct'] ?? 0;
                return $total > 0 && ($correct / $total) >= 0.60;

            case 'play_geography':
                $theme = strtolower($context['theme'] ?? '');
                return ($context['match_completed'] ?? ($context['won'] ?? false))
                    && (strpos($theme, 'géo') !== false || strpos($theme, 'geo') !== false);

            case 'play_history':
                $theme = strtolower($context['theme'] ?? '');
                return ($context['match_completed'] ?? ($context['won'] ?? false))
                    && (strpos($theme, 'histo') !== false);

            case 'win_cuisine':
                $theme = strtolower($context['theme'] ?? '');
                return ($context['won'] ?? false)
                    && (strpos($theme, 'cuisine') !== false || strpos($theme, 'gastrono') !== false);

            case 'league_participate':
                return ($context['mode'] ?? '') === 'league_individual'
                    && ($context['match_completed'] ?? ($context['action_done'] ?? false));

            case 'view_history_1':
                return ($context['action_done'] ?? false) === true;

            case 'chat_participate':
                return ($context['action_done'] ?? false) === true;

            case 'buy_buzzer_sound':
                return ($context['action_done'] ?? false) === true
                    && ($context['kind'] ?? '') === 'buzzer';

            case 'avatars_different_2':
                return ($context['different_avatars_count'] ?? 0) >= 2;

            // Stubs sociaux — systèmes non encore implémentés
            case 'invite_friend':
            case 'join_team':
            case 'help_teammate_1':
            case 'help_teammate_3':
            case 'socialize_5':
            case 'share_result':
            case 'receive_help_1':
                return false;

            // Thèmes impossibles à détecter sans tag par question
            case 'all_themes_completed':
            case 'monuments_10':
            case 'oceans_3':
            case 'trick_question_1':
                return false;

            // ─────────────────────────────────────────────────────────────
            // SEUILS PONCTUELS : niveau, pièces, division
            // ─────────────────────────────────────────────────────────────

            case 'level_25':
            case 'level_50':
            case 'level_75':
            case 'level_100':
                $required = (int) ($params['level'] ?? 1);
                return ($context['user_level'] ?? 0) >= $required;

            case 'coins_1000':
            case 'coins_5000':
                $required = (int) ($params['coins'] ?? 0);
                return ($context['user_coins'] ?? 0) >= $required;

            case 'division_silver':
                $division = strtolower($context['division'] ?? '');
                return in_array($division, ['argent', 'silver', 'or', 'gold', 'platine', 'diamant', 'legende'], true);

            case 'division_gold':
                $division = strtolower($context['division'] ?? '');
                return in_array($division, ['or', 'gold', 'platine', 'diamant', 'legende'], true);

            case 'division_legend':
                $division = strtolower($context['division'] ?? '');
                return in_array($division, ['legende', 'legend', 'légende'], true);

            // ─────────────────────────────────────────────────────────────
            // COMPTEURS CUMULATIFS (progression JSON cross-sessions)
            // ─────────────────────────────────────────────────────────────

            case 'win_streak_3':
            case 'win_streak_5':
            case 'win_streak_10':
                $target = (int) ($params['streak'] ?? 3);
                return $this->handleWinStreak($progress, $context, $target);

            case 'play_50_matches':
            case 'play_100_matches':
            case 'play_250_matches':
            case 'play_500_matches':
                $target = (int) ($params['matches'] ?? 50);
                return $this->handleMatchCount($progress, $context, $target);

            case 'correct_streak_25':
            case 'correct_streak_50':
                $target = (int) ($params['streak'] ?? 25);
                return $this->handleCorrectStreak($progress, $context, $target);

            case 'ultra_fast_answers_10':
                $threshold = (float) ($params['threshold'] ?? 1.0);
                $target    = (int) ($params['count'] ?? 10);
                return $this->handleFastAnswerCount($progress, $context, $threshold, $target, 'ultra_fast');

            case 'ultra_fast_buzz_20':
                $threshold = (float) ($params['threshold'] ?? 1.0);
                $target    = (int) ($params['count'] ?? 20);
                return $this->handleFastAnswerCount($progress, $context, $threshold, $target, 'ultra_fast_buzz');

            case 'fast_answers_10':
                $threshold = (float) ($params['time'] ?? 2.0);
                $target    = (int) ($params['count'] ?? 10);
                return $this->handleFastAnswerCount($progress, $context, $threshold, $target, 'fast_answers');

            case 'buzz_fast_10':
                $threshold = (float) ($params['time'] ?? 1.0);
                $target    = (int) ($params['count'] ?? 10);
                return $this->handleFastAnswerCount($progress, $context, $threshold, $target, 'buzz_fast');

            case 'first_buzz_10':
                return $this->checkFirstBuzz10($progress, $context);

            case 'perfect_score_3':
            case 'perfect_score_10':
            case 'perfect_score_25':
                $target = (int) ($params['count'] ?? 3);
                return $this->handlePerfectScoreCount($progress, $context, $target);

            case 'themes_5':
            case 'themes_10':
                $target = (int) ($params['themes'] ?? 5);
                return $this->handleThemeCount($progress, $context, $target);

            case 'duo_wins_10':
                $target = (int) ($params['wins'] ?? 10);
                return $this->handleDuoWinCount($progress, $context, $target);

            case 'skills_used_50':
                $target = (int) ($params['count'] ?? 50);
                return $this->handleSkillsUsedCount($progress, $context, $target);

            case 'skill_used':
                return $this->handleSkillUsedOnce($progress, $context);

            case 'avatars_unlocked_10':
            case 'avatars_unlocked_25':
                $target = (int) ($params['count'] ?? 10);
                return ($context['unlocked_avatars_count'] ?? 0) >= $target;

            case 'boss_defeats_5':
            case 'boss_defeats_10':
                $target = (int) ($params['count'] ?? 5);
                return $this->handleBossDefeatCount($progress, $context, $target);

            case 'correct_no_buzz':
                $target = (int) ($params['count'] ?? 3);
                return $this->handleCorrectNoBuzzCount($progress, $context, $target);

            case 'math_streak':
                $target = (int) ($params['count'] ?? 5);
                return $this->handleMathStreak($progress, $context, $target);

            default:
                return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS — compteurs JSON cumulatifs
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Gère les win streaks. Incrémente si won=true, remet à 0 sinon.
     * La clé 'win_streak' dans le JSON progress est partagée entre
     * win_streak_3, win_streak_5, win_streak_10 — chacun lit le même compteur.
     */
    protected function handleWinStreak(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $current = $data['win_streak'] ?? 0;

        if ($context['won'] ?? false) {
            $current++;
        } else {
            $current = 0;
        }

        $data['win_streak'] = $current;
        $progress->progress = $data;
        $progress->save();

        return $current >= $target;
    }

    /**
     * Incrémente le compteur de matchs joués (toutes modes confondues).
     */
    protected function handleMatchCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        if (!($context['match_completed'] ?? ($context['won'] ?? false)) && !isset($context['won'])) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $current = ($data['match_count'] ?? 0) + 1;
        $data['match_count'] = $current;
        $progress->progress = $data;
        $progress->save();

        return $current >= $target;
    }

    /**
     * Gère le streak de bonnes réponses consécutives (cross-sessions).
     * Remet à 0 si la réponse est incorrecte (context answer_wrong = true).
     */
    protected function handleCorrectStreak(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $current = $data['correct_streak'] ?? 0;

        if ($context['answer_correct'] ?? false) {
            $current++;
        } elseif ($context['answer_wrong'] ?? false) {
            $current = 0;
        } else {
            return $current >= $target;
        }

        $data['correct_streak'] = $current;
        $progress->progress = $data;
        $progress->save();

        return $current >= $target;
    }

    /**
     * Compteur de réponses rapides (ultra_fast_answers, fast_answers, buzz_fast).
     */
    protected function handleFastAnswerCount(
        UserQuestProgress $progress,
        array $context,
        float $threshold,
        int $target,
        string $progressKey
    ): bool {
        if ($progress->completed_at !== null) {
            return false;
        }

        $answerTime = $context['answer_time'] ?? $context['buzz_time'] ?? null;
        if ($answerTime === null || (float) $answerTime >= $threshold) {
            $data    = $progress->progress ?? [];
            $current = $data[$progressKey] ?? 0;
            return $current >= $target;
        }

        if (!($context['is_correct'] ?? $context['answer_correct'] ?? false)) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $current = ($data[$progressKey] ?? 0) + 1;
        $data[$progressKey] = $current;
        $progress->progress = $data;
        $progress->save();

        return $current >= $target;
    }

    /**
     * Compteur de scores parfaits (toutes bonnes réponses).
     */
    protected function handlePerfectScoreCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $total   = $context['total_questions'] ?? 0;
        $correct = $context['user_correct'] ?? 0;
        $won     = $context['won'] ?? false;

        $isPerfect = $won && $total >= 10 && $correct === $total;
        if (!$isPerfect) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $current = ($data['perfect_score_count'] ?? 0) + 1;
        $data['perfect_score_count'] = $current;
        $progress->progress = $data;
        $progress->save();

        return $current >= $target;
    }

    /**
     * Compteur de thèmes uniques joués.
     */
    protected function handleThemeCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $currentTheme = trim($context['theme'] ?? '');
        if ($currentTheme === '' || $currentTheme === 'Général') {
            return false;
        }

        $data   = $progress->progress ?? [];
        $played = $data['themes_played'] ?? [];

        if (!in_array($currentTheme, $played, true)) {
            $played[] = $currentTheme;
        }

        $data['themes_played'] = $played;
        $progress->progress = $data;
        $progress->save();

        return count($played) >= $target;
    }

    /**
     * Victoires en mode Duo.
     */
    protected function handleDuoWinCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        if (($context['mode'] ?? '') !== 'duo' || !($context['won'] ?? false)) {
            $data    = $progress->progress ?? [];
            $current = $data['duo_win_count'] ?? 0;
            return $current >= $target;
        }

        $data    = $progress->progress ?? [];
        $current = ($data['duo_win_count'] ?? 0) + 1;
        $data['duo_win_count'] = $current;
        $progress->progress = $data;
        $progress->save();

        return $current >= $target;
    }

    /**
     * Compétences d'avatar utilisées (compteur global).
     */
    protected function handleSkillsUsedCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        if (!($context['skill_used'] ?? false)) {
            $data    = $progress->progress ?? [];
            $current = $data['skills_used_count'] ?? 0;
            return $current >= $target;
        }

        $data    = $progress->progress ?? [];
        $current = ($data['skills_used_count'] ?? 0) + 1;
        $data['skills_used_count'] = $current;
        $progress->progress = $data;
        $progress->save();

        return $current >= $target;
    }

    /**
     * Première utilisation de compétence (one-shot).
     */
    protected function handleSkillUsedOnce(UserQuestProgress $progress, array $context): bool
    {
        return ($context['skill_used'] ?? false) === true;
    }

    /**
     * Défaites de boss (Solo).
     */
    protected function handleBossDefeatCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        if (!($context['boss_defeated'] ?? false)) {
            $data    = $progress->progress ?? [];
            $current = $data['boss_defeat_count'] ?? 0;
            return $current >= $target;
        }

        $data    = $progress->progress ?? [];
        $current = ($data['boss_defeat_count'] ?? 0) + 1;
        $data['boss_defeat_count'] = $current;
        $progress->progress = $data;
        $progress->save();

        return $current >= $target;
    }

    /**
     * Bonnes réponses sans avoir buzzé (second à buzzer ou sans buzz).
     */
    protected function handleCorrectNoBuzzCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $isCorrectNoBuzz = ($context['answer_correct'] ?? false)
            && !($context['player_buzzed'] ?? true);

        if (!$isCorrectNoBuzz) {
            $data    = $progress->progress ?? [];
            $current = $data['correct_no_buzz'] ?? 0;
            return $current >= $target;
        }

        $data    = $progress->progress ?? [];
        $current = ($data['correct_no_buzz'] ?? 0) + 1;
        $data['correct_no_buzz'] = $current;
        $progress->progress = $data;
        $progress->save();

        return $current >= $target;
    }

    /**
     * Streak de bonnes réponses consécutives en Math.
     */
    protected function handleMathStreak(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $isMathTheme  = strpos(strtolower($context['theme'] ?? ''), 'math') !== false;
        $isCorrect    = $context['answer_correct'] ?? false;
        $isWrong      = $context['answer_wrong'] ?? false;

        $data    = $progress->progress ?? [];
        $current = $data['math_streak'] ?? 0;

        if ($isMathTheme && $isCorrect) {
            $current++;
        } elseif ($isWrong || (!$isMathTheme && ($isCorrect || $isWrong))) {
            $current = 0;
        }

        $data['math_streak'] = $current;
        $progress->progress = $data;
        $progress->save();

        return $current >= $target;
    }

    /**
     * Legacy — checkFirstBuzz10 (gardé pour compatibilité).
     */
    protected function checkFirstBuzz10(UserQuestProgress $progress, array $context): bool
    {
        if (!($context['first_buzz'] ?? false)) {
            return false;
        }

        if ($progress->completed_at !== null) {
            return false;
        }

        $progressData = $progress->progress ?? [];
        $firstBuzzes  = $progressData['first_buzzes'] ?? $progressData['fast_buzzes'] ?? 0;

        if ($firstBuzzes >= 10) {
            return true;
        }

        $firstBuzzes++;
        unset($progressData['fast_buzzes']);
        $progress->progress = array_merge($progressData, ['first_buzzes' => $firstBuzzes]);
        $progress->save();

        return $firstBuzzes >= 10;
    }

    // ─────────────────────────────────────────────────────────────────────
    // PROGRESSION — affichage quêtes
    // ─────────────────────────────────────────────────────────────────────

    protected function getQuestProgression(Quest $quest, $progressRecord, bool $isCompleted): array
    {
        $params = is_array($quest->detection_params) ? $quest->detection_params : [];

        $max     = 1;
        $current = $isCompleted ? 1 : 0;

        if (isset($params['count'])) {
            $max = $params['count'];
        } elseif (isset($params['wins'])) {
            $max = $params['wins'];
        } elseif (isset($params['matches'])) {
            $max = $params['matches'];
        } elseif (isset($params['themes'])) {
            $max = $params['themes'];
        } elseif (isset($params['level'])) {
            $max = $params['level'];
        } elseif (isset($params['coins'])) {
            $max = $params['coins'];
        } elseif (isset($params['streak'])) {
            $max = $params['streak'];
        }

        if ($progressRecord && $progressRecord->progress) {
            $data = $progressRecord->progress;
            foreach ([
                'current', 'win_streak', 'match_count', 'correct_streak',
                'ultra_fast', 'ultra_fast_buzz', 'fast_answers', 'buzz_fast',
                'first_buzzes', 'fast_buzzes', 'perfect_score_count',
                'duo_win_count', 'skills_used_count', 'boss_defeat_count',
                'correct_no_buzz', 'math_streak', 'count',
            ] as $key) {
                if (isset($data[$key])) {
                    $current = $data[$key];
                    break;
                }
            }

            if (isset($data['themes_played']) && is_array($data['themes_played'])) {
                $current = count($data['themes_played']);
            }
        }

        return [
            'current' => min($current, $max),
            'max'     => $max,
        ];
    }

    public function getUserQuests(User $user, ?string $rarity = null)
    {
        $query = Quest::query();

        if ($rarity) {
            $query->where('rarity', $rarity);
        }

        $quests = $query->get();

        return $quests->map(function ($quest) use ($user) {
            $progressRecord = $quest->getUserProgress($user->id);
            $isCompleted    = $quest->isCompletedBy($user->id);

            $progression     = $this->getQuestProgression($quest, $progressRecord, $isCompleted);
            $currentProgress = $progression['current'];
            $totalProgress   = $progression['max'];

            if ($isCompleted) {
                $currentProgress = $totalProgress;
            }

            return [
                'quest'            => $quest,
                'is_completed'     => $isCompleted,
                'has_progress'     => $currentProgress > 0 && !$isCompleted,
                'progress_current' => $currentProgress,
                'progress_total'   => $totalProgress,
                'completed_at'     => $progressRecord ? $progressRecord->completed_at : null,
            ];
        });
    }

    public function getUnnotifiedCompletedQuests(User $user): array
    {
        return [];
    }

    public function getDailyQuests()
    {
        $today = now()->toDateString();

        $rotation = DB::table('daily_quest_rotation')
            ->where('rotation_date', $today)
            ->first();

        if (!$rotation) {
            $this->rotateDailyQuests();
            $rotation = DB::table('daily_quest_rotation')
                ->where('rotation_date', $today)
                ->first();
        }

        if (!$rotation) {
            return collect([]);
        }

        $questIds = json_decode($rotation->quest_ids, true);
        return Quest::whereIn('id', $questIds)->get();
    }

    public function rotateDailyQuests()
    {
        $today = now()->toDateString();

        $availableQuests = Quest::where('rarity', 'Quotidiennes')->get();

        if ($availableQuests->count() < 3) {
            return;
        }

        $selectedQuests = $availableQuests->random(min(3, $availableQuests->count()));
        $questIds       = $selectedQuests->pluck('id')->toArray();

        DB::table('daily_quest_rotation')->where('rotation_date', $today)->delete();
        DB::table('daily_quest_rotation')->insert([
            'rotation_date' => $today,
            'quest_ids'     => json_encode($questIds),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function scanAndUnlockRetroactiveQuests(User $user): array
    {
        $unlockedQuests = [];

        $allQuests = Quest::all();

        foreach ($allQuests as $quest) {
            if ($quest->isCompletedBy($user->id)) {
                continue;
            }

            $isMet = $this->checkRetroactiveCondition($user, $quest);

            if ($isMet) {
                $this->completeQuestRetroactive($user, $quest);
                $unlockedQuests[] = $quest;
            }
        }

        return $unlockedQuests;
    }

    protected function checkRetroactiveCondition(User $user, Quest $quest): bool
    {
        $code   = $quest->detection_code;
        $params = is_array($quest->detection_params) ? $quest->detection_params : [];

        $totalMatches = $this->getTotalMatchesCount($user);

        switch ($code) {
            case 'first_match_10q':
            case 'play_50_matches':
            case 'play_100_matches':
            case 'play_250_matches':
            case 'play_500_matches':
                $required = $params['matches'] ?? 1;
                return $totalMatches >= $required;

            case 'win_streak_3':
            case 'win_streak_5':
            case 'win_streak_10':
                return false;

            case 'perfect_score':
            case 'perfect_score_3':
            case 'perfect_score_10':
            case 'perfect_score_25':
                $required = $params['count'] ?? 1;
                return $this->getPerfectScoresCount($user) >= $required;

            case 'level_25':
            case 'level_50':
            case 'level_75':
            case 'level_100':
                $required = $params['level'] ?? 1;
                return ($user->level ?? 0) >= $required;

            case 'coins_1000':
            case 'coins_5000':
                $required = $params['coins'] ?? 0;
                return ($user->competence_coins ?? 0) >= $required;

            case 'division_silver':
            case 'division_gold':
            case 'division_legend':
                return $this->checkDivisionReached($user, $code);

            case 'duo_wins_10':
                $required = $params['wins'] ?? 10;
                return $this->getDuoWinsCount($user) >= $required;

            case 'boss_defeats_5':
            case 'boss_defeats_10':
                return false;

            case 'avatars_unlocked_10':
            case 'avatars_unlocked_25':
                $required = $params['count'] ?? 1;
                $settings = (array) ($user->profile_settings ?? []);
                $unlocked = array_merge(
                    (array) ($settings['unlocked_avatars'] ?? []),
                    (array) ($settings['unlocked'] ?? [])
                );
                return count(array_unique($unlocked)) >= $required;

            case 'themes_5':
            case 'themes_10':
                $required = $params['themes'] ?? 1;
                return $this->getUniqueThemesCount($user) >= $required;

            default:
                return false;
        }
    }

    protected function getTotalMatchesCount(User $user): int
    {
        $duo = DB::table('duo_matches')
            ->where(fn ($q) => $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id))
            ->where('status', 'completed')
            ->count();

        $league = DB::table('league_individual_matches')
            ->where(fn ($q) => $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id))
            ->where('status', 'completed')
            ->count();

        return $duo + $league;
    }

    protected function getPerfectScoresCount(User $user): int
    {
        return DB::table('duo_matches')
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('player1_id', $user->id)->whereRaw('player1_score >= 10'))
                ->orWhere(fn ($q2) => $q2->where('player2_id', $user->id)->whereRaw('player2_score >= 10'))
            )
            ->where('status', 'completed')
            ->count();
    }

    protected function getDuoWinsCount(User $user): int
    {
        return DB::table('duo_matches')
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('player1_id', $user->id)->whereRaw('player1_score > player2_score'))
                ->orWhere(fn ($q2) => $q2->where('player2_id', $user->id)->whereRaw('player2_score > player1_score'))
            )
            ->where('status', 'completed')
            ->count();
    }

    protected function checkDivisionReached(User $user, string $code): bool
    {
        $divisionMap = [
            'division_silver' => ['argent', 'or', 'platine', 'diamant', 'legende'],
            'division_gold'   => ['or', 'platine', 'diamant', 'legende'],
            'division_legend' => ['legende'],
        ];

        $required = $divisionMap[$code] ?? [];

        $duoDivision    = strtolower($user->duo_division ?? 'bronze');
        $leagueDivision = strtolower($user->league_division ?? 'bronze');

        return in_array($duoDivision, $required, true) || in_array($leagueDivision, $required, true);
    }

    protected function getUniqueThemesCount(User $user): int
    {
        $themes = DB::table('duo_matches')
            ->where(fn ($q) => $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id))
            ->where('status', 'completed')
            ->whereNotNull('theme')
            ->distinct()
            ->pluck('theme')
            ->toArray();

        return count($themes);
    }

    protected function completeQuestRetroactive(User $user, Quest $quest): void
    {
        DB::transaction(function () use ($user, $quest) {
            $progress = UserQuestProgress::firstOrCreate(
                ['user_id' => $user->id, 'quest_id' => $quest->id],
                ['progress' => [], 'rewarded' => false]
            );

            $progress = UserQuestProgress::where('id', $progress->id)->lockForUpdate()->first();

            if ($progress->completed_at !== null) {
                return;
            }

            $progress->completed_at = now();
            $progress->rewarded     = true;
            $progress->save();

            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            if ($lockedUser) {
                $lockedUser->competence_coins = ($lockedUser->competence_coins ?? 0) + $quest->reward_coins;
                $lockedUser->save();
            }
        });
    }
}
