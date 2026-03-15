<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\UserQuestProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuestService
{
    public function __construct(
        private CoinLedgerService $coinLedgerService
    ) {}

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

                    if (($quest->reward_coins ?? 0) > 0) {
                        $this->coinLedgerService->credit(
                            $user,
                            (int) $quest->reward_coins,
                            'quest_reward',
                            'quest',
                            $quest->id,
                            $quest->coin_type ?? 'competence'
                        );
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

                    if (($quest->reward_coins ?? 0) > 0) {
                        $this->coinLedgerService->credit(
                            $user,
                            (int) $quest->reward_coins,
                            'quest_reward',
                            'quest',
                            $quest->id,
                            $quest->coin_type ?? 'competence'
                        );
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
                $hour = $context['match_hour'] ?? (int) Carbon::now()->format('G');
                return ($context['match_completed'] ?? ($context['won'] ?? false))
                    && $hour >= 6 && $hour < 9;

            case 'play_late_night':
                $hour = $context['match_hour'] ?? (int) Carbon::now()->format('G');
                return ($context['match_completed'] ?? ($context['won'] ?? false))
                    && ($hour >= 22 || $hour < 2);

            case 'night_owl':
                $hour = $context['match_hour'] ?? (int) Carbon::now()->format('G');
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
            case 'help_teammate_1':
            case 'help_teammate_3':
            case 'socialize_5':
            case 'share_result':
            case 'receive_help_1':
                return false;

            // join_team : hook action_done depuis PlayerGroupController
            case 'join_team':
                return ($context['action_done'] ?? false) === true;

            // trick_question_1 : répondre correctement à une question piège
            case 'trick_question_1':
                return ($context['is_trick_question'] ?? false) === true
                    && ($context['answer_correct'] ?? false) === true;

            // Détection par mots-clés thème (approximation — sans tag par question)
            case 'monuments_10':
                $target = (int) ($params['count'] ?? 10);
                return $this->handleThemeKeywordCount($progress, $context, $target, 'monuments', [
                    'monument', 'patrimoine', 'architecture', 'musée', 'musee', 'château', 'chateau',
                ]);

            case 'oceans_3':
                $target = (int) ($params['count'] ?? 3);
                return $this->handleThemeKeywordCount($progress, $context, $target, 'oceans', [
                    'océan', 'ocean', 'mer', 'maritime', 'marin', 'sous-marin',
                ]);

            case 'all_themes_completed':
                // Complétée quand le joueur a joué dans 20 thèmes différents
                $data   = $progress->progress ?? [];
                $played = $data['themes_played'] ?? [];
                $currentTheme = trim($context['theme'] ?? '');
                if ($currentTheme !== '' && $currentTheme !== 'Général' && !in_array($currentTheme, $played, true)) {
                    $played[] = $currentTheme;
                    $data['themes_played'] = $played;
                    $progress->progress = $data;
                    $progress->save();
                }
                return count($played) >= 20;

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

            // ─────────────────────────────────────────────────────────────
            // NOUVELLES QUÊTES RARE / ÉPIQUE / LÉGENDAIRE / MAÎTRE
            // ─────────────────────────────────────────────────────────────

            // Win-streak variants (helpers existants, seuils différents)
            case 'consecutive_wins':       // ID 96 – 3 victoires consécutives
            case 'undefeated_streak':      // ID 102 – 10 matchs sans perdre
            case 'win_streak_epique':      // ID 148 – 25 victoires
            case 'win_streak_legendaire':  // ID 161 – 100 victoires
            case 'win_streak_titan':       // ID 183 – 200 victoires
                $target = (int) ($params['count'] ?? 3);
                return $this->handleWinStreak($progress, $context, $target);

            // Streak correct variants
            case 'consecutive_correct':          // ID 99 – 20 consécutives
            case 'perfect_accuracy_epique':      // ID 140 – 100 % sur 50
            case 'perfect_accuracy_legendaire':  // ID 163 – 100 % sur 500
                $target = (int) ($params['count'] ?? 20);
                return $this->handleCorrectStreak($progress, $context, $target);

            // Réponses rapides variants
            case 'fast_answers':  // ID 97 – 5 réponses < 2 s
                $threshold = (float) ($params['max_time'] ?? 2.0);
                $target    = (int) ($params['count'] ?? 5);
                return $this->handleFastAnswerCount($progress, $context, $threshold, $target, 'fast_answers_5');

            case 'ultra_fast_answers_epique':  // ID 147 – 20 réponses < 1 s (partage le compteur 'ultra_fast')
                $threshold = (float) ($params['max_time'] ?? 1.0);
                $target    = (int) ($params['count'] ?? 20);
                return $this->handleFastAnswerCount($progress, $context, $threshold, $target, 'ultra_fast');

            case 'ultra_fast_answers_legendaire':  // ID 168 – 100 réponses < 0,5 s
                $threshold = (float) ($params['max_time'] ?? 0.5);
                $target    = (int) ($params['count'] ?? 100);
                return $this->handleFastAnswerCount($progress, $context, $threshold, $target, 'ultra_fast_0_5');

            // Match count variant
            case 'total_matches_legendaire':  // ID 169 – 5000 matchs
                $target = (int) ($params['count'] ?? 5000);
                return $this->handleMatchCount($progress, $context, $target);

            // Duo wins variant
            case 'duo_wins':  // ID 100 – 5 victoires Duo
                $target = (int) ($params['count'] ?? 5);
                return $this->handleDuoWinCount($progress, $context, $target);

            // Skills used variant
            case 'skill_usage':  // ID 107 – 20 compétences (total)
                $target = (int) ($params['count'] ?? 20);
                return $this->handleSkillsUsedCount($progress, $context, $target);

            // Boss defeat variant
            case 'boss_defeat':  // ID 110 – 1 boss
                $target = (int) ($params['count'] ?? 1);
                return $this->handleBossDefeatCount($progress, $context, $target);

            // Avatars unlocked variants
            case 'avatar_collection_epique':  // ID 146 – 30 avatars
                $target = (int) ($params['count'] ?? 30);
                return ($context['unlocked_avatars_count'] ?? 0) >= $target;

            case 'all_avatars_unlocked':  // ID 165 – tous les avatars (90)
                $target = (int) ($params['count'] ?? 90);
                return ($context['unlocked_avatars_count'] ?? 0) >= $target;

            // Coins thresholds
            case 'coins_accumulated':           // ID 155 – 1 000 000 pièces
            case 'coins_accumulated_legendaire': // ID 166 – 10 000 000 pièces
                $required = (int) ($params['amount'] ?? 1000000);
                return ($context['user_coins'] ?? 0) >= $required;

            // Division diamond (Duo)
            case 'duo_rank_diamond':  // ID 143
                $division = strtolower($context['division'] ?? '');
                return ($context['mode'] ?? '') === 'duo'
                    && in_array($division, ['diamant', 'diamond'], true);

            // Tous les thèmes joués
            case 'all_themes_played':  // ID 142
                return $this->handleAllThemesPlayed($progress, $context);

            // Matchs nocturnes
            case 'night_matches':   // ID 111 – 5 matchs entre 0h et 6h
            case 'night_marathon':  // ID 145 – 50 matchs entre 22h et 6h
                $target    = (int) ($params['count'] ?? 5);
                $startHour = (int) ($params['start_hour'] ?? 0);
                $endHour   = (int) ($params['end_hour'] ?? 6);
                return $this->handleNightMatchCount($progress, $context, $target, $startHour, $endHour);

            // Victoires dans un même thème
            case 'theme_wins':  // ID 101 – 10 victoires même thème
                $target = (int) ($params['count'] ?? 10);
                return $this->handleThemeWinCount($progress, $context, $target);

            // Maîtrise multi-thèmes : N victoires × M thèmes
            case 'multi_theme_mastery':  // ID 153 – 50 victoires × 5 thèmes
                $winsPerTheme = (int) ($params['wins_per_theme'] ?? 50);
                $targetThemes = (int) ($params['themes'] ?? 5);
                return $this->handleMultiThemeMastery($progress, $context, $winsPerTheme, $targetThemes);

            case 'multi_theme_wins_rare':  // ID 181 – 100 victoires réparties sur 10 thèmes
                $totalWins    = (int) ($params['wins'] ?? 100);
                $targetThemes = (int) ($params['themes'] ?? 10);
                return $this->handleMultiThemeWins($progress, $context, $totalWins, $targetThemes);

            // Matchs dans un même thème
            case 'theme_dedication':  // ID 112 – 50 matchs même thème
                $target = (int) ($params['matches'] ?? 50);
                return $this->handlePerThemeMatchCount($progress, $context, $target);

            // Achats boutique
            case 'shop_purchases':        // ID 109 – 5 achats
            case 'shop_purchases_epique': // ID 149 – 20 achats
                $target = (int) ($params['count'] ?? 5);
                return $this->handleShopPurchaseCount($progress, $context, $target);

            // Victoires comeback
            case 'comeback_victory':              // ID 113 – 1 comeback
            case 'comeback_victories_epique':     // ID 157 – 10 comebacks
            case 'comeback_victories_legendaire': // ID 174 – 50 comebacks
                $target = (int) ($params['count'] ?? 1);
                return $this->handleComebackWinCount($progress, $context, $target);

            // Premier à buzzer (total cumulatif)
            case 'first_buzz_total':            // ID 160 – 500
            case 'first_buzz_total_legendaire': // ID 182 – 1000
                $target = (int) ($params['count'] ?? 500);
                return $this->handleFirstBuzzTotalCount($progress, $context, $target);

            // Victoires en Ligue Individuelle
            case 'league_wins':  // ID 152 – 50 victoires Ligue
                $target = (int) ($params['count'] ?? 50);
                return $this->handleLeagueWinCount($progress, $context, $target);

            // Parties organisées Maître du Jeu
            case 'master_games_hosted_epique':    // ID 158 – 50
            case 'master_games_hosted_legendaire': // ID 171 – 500
            case 'master_games_hosted_maitre':    // ID 179 – 1000
                $target = (int) ($params['count'] ?? 50);
                return $this->handleMasterGamesHostedCount($progress, $context, $target);

            // Compétences uniques utilisées
            case 'unique_skills_used':  // ID 150 – 10 compétences distinctes
                $target = (int) ($params['count'] ?? 10);
                return $this->handleUniqueSkillsUsed($progress, $context, $target);

            // Toutes les compétences utilisées
            case 'all_skills_used':  // ID 173
                return $this->handleAllSkillsUsed($progress, $context);

            // Méta-quête : toutes les quêtes complétées
            case 'all_quests_completed':  // ID 176
                return $this->handleAllQuestsCompleted($progress, $context);

            // ─────────────────────────────────────────────────────────────
            // BOT QUESTS (programmatic trigger, auto_complete = false)
            // ─────────────────────────────────────────────────────────────
            case 'bot_first_selection':
                return ($context['action_done'] ?? false) === true;

            case 'bot_first_win':
                return ($context['action_done'] ?? false) === true;

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
     * Compteur de bonnes réponses sur des thèmes contenant des mots-clés.
     * Détection approximative par le nom du thème.
     */
    protected function handleThemeKeywordCount(
        UserQuestProgress $progress,
        array $context,
        int $target,
        string $progressKey,
        array $keywords
    ): bool {
        if ($progress->completed_at !== null) {
            return false;
        }

        $theme   = strtolower($context['theme'] ?? '');
        $correct = $context['answer_correct'] ?? false;

        $matches = false;
        foreach ($keywords as $kw) {
            if (strpos($theme, strtolower($kw)) !== false) {
                $matches = true;
                break;
            }
        }

        $data    = $progress->progress ?? [];
        $current = $data[$progressKey . '_count'] ?? 0;

        if ($matches && $correct) {
            $current++;
            $data[$progressKey . '_count'] = $current;
            $progress->progress = $data;
            $progress->save();
        }

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
    // NOUVEAUX HELPERS — quêtes Rare / Épique / Légendaire / Maître
    // ─────────────────────────────────────────────────────────────────────

    protected function handleNightMatchCount(UserQuestProgress $progress, array $context, int $target, int $startHour = 0, int $endHour = 6): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        if (!($context['match_completed'] ?? false) && !isset($context['won'])) {
            return false;
        }

        $hour = (int) ($context['match_hour'] ?? (int) Carbon::now()->format('G'));
        $isNight = $startHour < $endHour
            ? ($hour >= $startHour && $hour < $endHour)
            : ($hour >= $startHour || $hour < $endHour);

        $data    = $progress->progress ?? [];
        $current = $data['night_match_count'] ?? 0;

        if ($isNight) {
            $current++;
            $data['night_match_count'] = $current;
            $progress->progress = $data;
            $progress->save();
        }

        return $current >= $target;
    }

    protected function handleThemeWinCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $theme = trim($context['theme'] ?? '');
        $data  = $progress->progress ?? [];
        $wins  = $data['theme_wins'] ?? [];

        if ($theme !== '' && ($context['won'] ?? false)) {
            $wins[$theme] = ($wins[$theme] ?? 0) + 1;
            $data['theme_wins'] = $wins;
            $progress->progress = $data;
            $progress->save();
        }

        return !empty($wins) && max($wins) >= $target;
    }

    protected function handleMultiThemeMastery(UserQuestProgress $progress, array $context, int $winsPerTheme, int $targetThemes): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $theme = trim($context['theme'] ?? '');
        $data  = $progress->progress ?? [];
        $wins  = $data['theme_wins_mastery'] ?? [];

        if ($theme !== '' && ($context['won'] ?? false)) {
            $wins[$theme] = ($wins[$theme] ?? 0) + 1;
            $data['theme_wins_mastery'] = $wins;
            $progress->progress = $data;
            $progress->save();
        }

        $qualified = count(array_filter($wins, fn($w) => $w >= $winsPerTheme));
        return $qualified >= $targetThemes;
    }

    protected function handleMultiThemeWins(UserQuestProgress $progress, array $context, int $totalWinsTarget, int $targetThemes): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $theme = trim($context['theme'] ?? '');
        $data  = $progress->progress ?? [];
        $wins  = $data['theme_wins_spread'] ?? [];

        if ($theme !== '' && ($context['won'] ?? false)) {
            $wins[$theme] = ($wins[$theme] ?? 0) + 1;
            $data['theme_wins_spread'] = $wins;
            $progress->progress = $data;
            $progress->save();
        }

        return count($wins) >= $targetThemes && array_sum($wins) >= $totalWinsTarget;
    }

    protected function handlePerThemeMatchCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $theme = trim($context['theme'] ?? '');
        if ($theme === '') {
            return false;
        }

        if (!($context['match_completed'] ?? false) && !isset($context['won'])) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $matches = $data['theme_matches'] ?? [];
        $matches[$theme] = ($matches[$theme] ?? 0) + 1;
        $data['theme_matches'] = $matches;
        $progress->progress = $data;
        $progress->save();

        return max($matches) >= $target;
    }

    protected function handleShopPurchaseCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $current = $data['shop_purchase_count'] ?? 0;

        if ($context['shop_purchase'] ?? false) {
            $current++;
            $data['shop_purchase_count'] = $current;
            $progress->progress = $data;
            $progress->save();
        }

        return $current >= $target;
    }

    protected function handleComebackWinCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $current = $data['comeback_win_count'] ?? 0;

        if ($context['comeback_win'] ?? false) {
            $current++;
            $data['comeback_win_count'] = $current;
            $progress->progress = $data;
            $progress->save();
        }

        return $current >= $target;
    }

    protected function handleFirstBuzzTotalCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $current = $data['first_buzz_total'] ?? 0;

        if ($context['first_buzz'] ?? false) {
            $current++;
            $data['first_buzz_total'] = $current;
            $progress->progress = $data;
            $progress->save();
        }

        return $current >= $target;
    }

    protected function handleLeagueWinCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $current = $data['league_win_count'] ?? 0;

        $isLeague = in_array($context['mode'] ?? '', ['league_individual', 'league_team'], true);
        if ($isLeague && ($context['won'] ?? false)) {
            $current++;
            $data['league_win_count'] = $current;
            $progress->progress = $data;
            $progress->save();
        }

        return $current >= $target;
    }

    protected function handleMasterGamesHostedCount(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $data    = $progress->progress ?? [];
        $current = $data['master_games_hosted'] ?? 0;

        if ($context['master_game_hosted'] ?? false) {
            $current++;
            $data['master_games_hosted'] = $current;
            $progress->progress = $data;
            $progress->save();
        }

        return $current >= $target;
    }

    protected function handleUniqueSkillsUsed(UserQuestProgress $progress, array $context, int $target): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $skillSlug = $context['skill_slug'] ?? null;
        $data      = $progress->progress ?? [];
        $skills    = $data['unique_skill_slugs'] ?? [];

        if ($skillSlug !== null && !in_array($skillSlug, $skills, true)) {
            $skills[] = $skillSlug;
            $data['unique_skill_slugs'] = $skills;
            $progress->progress = $data;
            $progress->save();
        }

        return count($skills) >= $target;
    }

    protected function handleAllSkillsUsed(UserQuestProgress $progress, array $context): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $allSlugs = [
            'feather', 'illuminate_numbers', 'acidify_error', 'see_opponent_choice',
            'replay', 'ai_suggestion', 'eliminate_two', 'knowledge_without_time',
            'reduce_time', 'shuffle_answers', 'freeze_time', 'double_points',
            'second_chance', 'time_bomb', 'mirror', 'shield',
            'steal_points', 'fog_of_war', 'lucky_guess', 'oracle',
            'turbo_buzz', 'blind', 'nullify', 'swap',
            'hint', 'critical_buzz', 'reversal', 'echo',
            'sabotage', 'dodge',
        ];

        $skillSlug = $context['skill_slug'] ?? null;
        $data      = $progress->progress ?? [];
        $skills    = $data['all_skill_slugs'] ?? [];

        if ($skillSlug !== null && !in_array($skillSlug, $skills, true)) {
            $skills[] = $skillSlug;
            $data['all_skill_slugs'] = $skills;
            $progress->progress = $data;
            $progress->save();
        }

        return empty(array_diff($allSlugs, $skills));
    }

    protected function handleAllThemesPlayed(UserQuestProgress $progress, array $context): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $allThemes = ['general', 'geographie', 'histoire', 'art', 'cinema', 'sport', 'cuisine', 'faune', 'sciences'];
        $theme     = strtolower(trim($context['theme'] ?? ''));
        $data      = $progress->progress ?? [];
        $played    = $data['all_themes_played'] ?? [];

        if ($theme !== '' && !in_array($theme, $played, true)) {
            $played[] = $theme;
            $data['all_themes_played'] = $played;
            $progress->progress = $data;
            $progress->save();
        }

        return empty(array_diff($allThemes, $played));
    }

    protected function handleAllQuestsCompleted(UserQuestProgress $progress, array $context): bool
    {
        if ($progress->completed_at !== null) {
            return false;
        }

        $userId   = $progress->user_id;
        $totalAC  = Quest::where('auto_complete', true)->where('id', '!=', $progress->quest_id)->count();
        $completed = UserQuestProgress::where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->where('rewarded', true)
            ->where('quest_id', '!=', $progress->quest_id)
            ->count();

        return $completed >= $totalAC;
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
        } elseif (isset($params['amount'])) {
            $max = $params['amount'];
        } elseif (isset($params['streak'])) {
            $max = $params['streak'];
        } elseif (isset($params['wins_per_theme'])) {
            $max = $params['wins_per_theme'];
        }

        if ($progressRecord && $progressRecord->progress) {
            $data = $progressRecord->progress;
            foreach ([
                'current', 'win_streak', 'match_count', 'correct_streak',
                'ultra_fast', 'ultra_fast_buzz', 'fast_answers', 'buzz_fast',
                'fast_answers_5', 'ultra_fast_0_5',
                'first_buzzes', 'fast_buzzes', 'perfect_score_count',
                'duo_win_count', 'skills_used_count', 'boss_defeat_count',
                'correct_no_buzz', 'math_streak',
                'night_match_count', 'shop_purchase_count', 'comeback_win_count',
                'first_buzz_total', 'league_win_count', 'master_games_hosted',
                'count',
            ] as $key) {
                if (isset($data[$key])) {
                    $current = $data[$key];
                    break;
                }
            }

            if (isset($data['themes_played']) && is_array($data['themes_played'])) {
                $current = count($data['themes_played']);
            }
            if (isset($data['all_themes_played']) && is_array($data['all_themes_played'])) {
                $current = count($data['all_themes_played']);
            }
            if (isset($data['theme_wins']) && is_array($data['theme_wins']) && !empty($data['theme_wins'])) {
                $current = max($data['theme_wins']);
            }
            if (isset($data['theme_wins_mastery']) && is_array($data['theme_wins_mastery'])) {
                $current = count(array_filter($data['theme_wins_mastery'], fn($w) => $w >= 1));
            }
            if (isset($data['theme_wins_spread']) && is_array($data['theme_wins_spread'])) {
                $current = array_sum($data['theme_wins_spread']);
            }
            if (isset($data['theme_matches']) && is_array($data['theme_matches']) && !empty($data['theme_matches'])) {
                $current = max($data['theme_matches']);
            }
            if (isset($data['unique_skill_slugs']) && is_array($data['unique_skill_slugs'])) {
                $current = count($data['unique_skill_slugs']);
            }
            if (isset($data['all_skill_slugs']) && is_array($data['all_skill_slugs'])) {
                $current = count($data['all_skill_slugs']);
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

            if (($quest->reward_coins ?? 0) > 0) {
                $this->coinLedgerService->credit(
                    $user,
                    (int) $quest->reward_coins,
                    'quest_reward',
                    'quest',
                    $quest->id,
                    $quest->coin_type ?? 'competence'
                );
            }
        });
    }
}
