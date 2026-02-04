<?php

namespace App\Services;

use App\Models\User;
use App\Models\LeagueIndividualStat;
use App\Models\LeagueIndividualMatch;
use App\Models\PlayerDuoStat;
use App\Models\PlayerDivision;

class LeagueIndividualService
{
    public function __construct(
        private DivisionService $divisionService,
        private GameStateService $gameStateService
    ) {}

    /**
     * Initialise la Ligue Individuel pour un joueur
     * Le niveau initial est basé sur le niveau Duo du joueur
     */
    public function initializeLeague(User $user): LeagueIndividualStat
    {
        $stats = LeagueIndividualStat::firstOrCreate(
            ['user_id' => $user->id],
            ['level' => 1, 'initialized' => false]
        );

        if (!$stats->initialized) {
            $duoStats = PlayerDuoStat::where('user_id', $user->id)->first();
            $duoLevel = $duoStats ? $duoStats->level : 1;

            $stats->initializeFromDuo($duoLevel);

            $division = $this->divisionService->getOrCreateDivision($user, 'league_individual');
            $division->level = $duoLevel;
            $division->division = 'bronze';
            $division->points = 0;
            $division->save();
        }

        return $stats;
    }

    /**
     * Vérifie si la Ligue est initialisée pour un joueur
     */
    public function isInitialized(User $user): bool
    {
        $stats = LeagueIndividualStat::where('user_id', $user->id)->first();
        return $stats && $stats->initialized;
    }

    /**
     * Trouve un adversaire aléatoire dans la même division
     */
    public function findRandomOpponent(User $user): ?User
    {
        $userDivision = $this->divisionService->getOrCreateDivision($user, 'league_individual');
        
        return User::whereHas('playerDivisions', function ($query) use ($userDivision, $user) {
            $query->where('mode', 'league_individual')
                  ->where('division', $userDivision->division)
                  ->where('user_id', '!=', $user->id);
        })
        ->whereHas('leagueIndividualStat', function ($query) {
            $query->where('initialized', true);
        })
        ->inRandomOrder()
        ->first();
    }

    /**
     * Crée un match Ligue Individuel
     */
    public function createMatch(User $player1, User $player2): LeagueIndividualMatch
    {
        $stats1 = $this->getOrCreateStats($player1);
        $stats2 = $this->getOrCreateStats($player2);

        $gameState = $this->gameStateService->initializeGame([
            'mode' => 'league_individual',
            'theme' => 'culture',
            'nb_questions' => 10,
            'nb_rounds' => 3,
            'niveau' => 1,
            'players' => [
                ['id' => 'player'],
                ['id' => 'opponent']
            ]
        ]);

        return LeagueIndividualMatch::create([
            'player1_id' => $player1->id,
            'player2_id' => $player2->id,
            'player1_level' => $stats1->level,
            'player2_level' => $stats2->level,
            'status' => 'playing',
            'game_state' => $gameState,
        ]);
    }

    /**
     * Termine un match et met à jour les statistiques
     */
    public function finishMatch(LeagueIndividualMatch $match, array $matchResult): void
    {
        $player1 = $match->player1;
        $player2 = $match->player2;

        $player1Won = $matchResult['player_won'] ?? false;
        $winnerId = $player1Won ? $player1->id : $player2->id;

        $div1 = $this->divisionService->getOrCreateDivision($player1, 'league_individual');
        $div2 = $this->divisionService->getOrCreateDivision($player2, 'league_individual');

        $p1IsTemp = $this->divisionService->hasActiveTemporaryAccess($player1, $div1->division);
        $p2IsTemp = $this->divisionService->hasActiveTemporaryAccess($player2, $div2->division);

        $p1Strength = $this->divisionService->determineOpponentStrength(
            $div1->division,
            $div2->division,
            $div1->initial_efficiency ?? 0,
            $div2->initial_efficiency ?? 0,
            $p1IsTemp
        );

        $p2Strength = $this->divisionService->determineOpponentStrength(
            $div2->division,
            $div1->division,
            $div2->initial_efficiency ?? 0,
            $div1->initial_efficiency ?? 0,
            $p2IsTemp
        );

        $player1PointsEarned = $this->divisionService->calculatePoints($p1Strength, $player1Won);
        $player2PointsEarned = $this->divisionService->calculatePoints($p2Strength, !$player1Won);

        $p1CoinReward = $this->divisionService->calculateVictoryReward(
            $div1->division,
            $p1Strength,
            $player1Won,
            $p1IsTemp
        );

        $p2CoinReward = $this->divisionService->calculateVictoryReward(
            $div2->division,
            $p2Strength,
            !$player1Won,
            $p2IsTemp
        );

        $match->update([
            'status' => 'finished',
            'winner_id' => $winnerId,
            'player1_points_earned' => $player1PointsEarned,
            'player2_points_earned' => $player2PointsEarned,
            'player1_coins_earned' => $p1CoinReward['coins'],
            'player2_coins_earned' => $p2CoinReward['coins'],
        ]);

        $this->updatePlayerStats($player1, $player1Won, $player1PointsEarned, $match->player1_level, $p1CoinReward['coins']);
        $this->updatePlayerStats($player2, !$player1Won, $player2PointsEarned, $match->player2_level, $p2CoinReward['coins']);

        $this->divisionService->clearCurrentMatch($player1);
        $this->divisionService->clearCurrentMatch($player2);
    }

    /**
     * Met à jour les statistiques d'un joueur après un match
     */
    private function updatePlayerStats(User $user, bool $won, int $pointsEarned, int $currentLevel, int $coinsEarned = 0): void
    {
        $stats = $this->getOrCreateStats($user);
        
        $stats->matches_played++;
        if ($won) {
            $stats->matches_won++;
        } else {
            $stats->matches_lost++;
        }
        $stats->total_points += max(0, $pointsEarned);
        $stats->save();

        $division = $this->divisionService->getOrCreateDivision($user, 'league_individual');
        $this->divisionService->updateDivisionPointsWithFloor($division, $pointsEarned);

        // Ligue gagne des pièces d'Intelligence (car vous prouvez vos connaissances)
        if ($coinsEarned > 0) {
            $user->coins = ($user->coins ?? 0) + $coinsEarned;
            $user->save();
        }
    }

    /**
     * Récupère ou crée les statistiques d'un joueur
     */
    public function getOrCreateStats(User $user): LeagueIndividualStat
    {
        return LeagueIndividualStat::firstOrCreate(
            ['user_id' => $user->id],
            ['level' => 1, 'initialized' => true]
        );
    }

    /**
     * Récupère les classements par division
     */
    public function getRankingsByDivision(string $division, int $limit = 100): array
    {
        return PlayerDivision::where('mode', 'league_individual')
            ->where('division', $division)
            ->with(['user.leagueIndividualStat'])
            ->orderByDesc('level')
            ->orderByDesc('points')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($division, $index) {
                return [
                    'rank' => $index + 1,
                    'user' => $division->user,
                    'division' => $division->division,
                    'level' => $division->level,
                    'points' => $division->points,
                    'stats' => $division->user->leagueIndividualStat,
                ];
            })
            ->toArray();
    }

    /**
     * Récupère le rang d'un joueur dans sa division
     */
    public function getPlayerRank(User $user): ?int
    {
        return $this->divisionService->getPlayerRank($user, 'league_individual');
    }
}
