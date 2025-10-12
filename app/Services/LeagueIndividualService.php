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

        $player1PointsEarned = $this->divisionService->calculatePoints(
            $match->player1_level,
            $match->player2_level,
            $player1Won
        );

        $player2PointsEarned = $this->divisionService->calculatePoints(
            $match->player2_level,
            $match->player1_level,
            !$player1Won
        );

        $match->update([
            'status' => 'finished',
            'winner_id' => $winnerId,
            'player1_points_earned' => $player1PointsEarned,
            'player2_points_earned' => $player2PointsEarned,
        ]);

        $this->updatePlayerStats($player1, $player1Won, $player1PointsEarned, $match->player1_level);
        $this->updatePlayerStats($player2, !$player1Won, $player2PointsEarned, $match->player2_level);
    }

    /**
     * Met à jour les statistiques d'un joueur après un match
     */
    private function updatePlayerStats(User $user, bool $won, int $pointsEarned, int $currentLevel): void
    {
        $stats = $this->getOrCreateStats($user);
        
        $stats->matches_played++;
        if ($won) {
            $stats->matches_won++;
        } else {
            $stats->matches_lost++;
        }
        $stats->total_points += $pointsEarned;
        $stats->save();

        $this->divisionService->updateDivisionAfterMatch(
            $user,
            'league_individual',
            $pointsEarned,
            $currentLevel
        );
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
