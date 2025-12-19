<?php

namespace App\Services;

use App\Models\Team;
use App\Models\LeagueTeamMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeagueTeamService
{
    private GameStateService $gameStateService;
    private BuzzManagerService $buzzManagerService;
    private DivisionService $divisionService;
    private QuestionService $questionService;

    public function __construct(
        GameStateService $gameStateService,
        BuzzManagerService $buzzManagerService,
        DivisionService $divisionService,
        QuestionService $questionService
    ) {
        $this->gameStateService = $gameStateService;
        $this->buzzManagerService = $buzzManagerService;
        $this->divisionService = $divisionService;
        $this->questionService = $questionService;
    }

    public function initializeTeamMatch(Team $team, string $gameMode = 'classique', array $options = []): LeagueTeamMatch
    {
        if ($team->teamMembers()->count() < 5) {
            throw new \Exception('Votre équipe doit avoir 5 joueurs pour jouer.');
        }

        $opponent = $this->findOpponent($team);
        if (!$opponent) {
            throw new \Exception('Aucun adversaire trouvé dans votre division.');
        }

        if ($opponent->teamMembers()->count() < 5) {
            throw new \Exception('L\'équipe adverse n\'a pas assez de joueurs.');
        }
        
        $matchDivision = $options['match_division'] ?? strtolower($team->division ?? 'bronze');
        $divisionIndexes = ['bronze' => 0, 'argent' => 1, 'silver' => 1, 'or' => 2, 'gold' => 2, 'platine' => 3, 'platinum' => 3, 'diamant' => 4, 'diamond' => 4];
        $team1Level = $divisionIndexes[strtolower($team->division ?? 'bronze')] ?? 0;
        $team2Level = $divisionIndexes[strtolower($opponent->division ?? 'bronze')] ?? 0;

        $team1Members = $team->teamMembers()->with('user')->get()->pluck('user');
        $team2Members = $opponent->teamMembers()->with('user')->get()->pluck('user');

        $allPlayers = $team1Members->concat($team2Members)->map(function ($user, $index) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->strategic_avatar ?? null,
                'team_index' => $index < 5 ? 1 : 2,
                'level' => $user->duoStats?->level ?? 1,
            ];
        })->toArray();

        $gameState = $this->gameStateService->initializeGame($allPlayers, 'league_team');
        $gameState['game_mode'] = $gameMode;
        $gameState['skills_free_for_all'] = ($gameMode === 'classique');
        
        $duelPairings = null;
        $playerOrder = null;
        
        if ($gameMode === 'bataille') {
            $duelPairings = $options['duel_pairings'] ?? $this->createDuelPairings($team1Members, $team2Members);
            $gameState['duel_pairings'] = $duelPairings;
            $gameState['active_duels'] = array_map(fn($d) => [
                'player1_id' => $d['player1']['id'],
                'player2_id' => $d['player2']['id'],
                'player1_score' => 0,
                'player2_score' => 0,
                'current_question' => 0,
            ], $duelPairings);
        }
        
        $relayIndices = null;
        if ($gameMode === 'relais') {
            $playerOrder = [
                'team1' => $options['player_order']['team1'] ?? $team1Members->pluck('id')->toArray(),
                'team2' => $options['player_order']['team2'] ?? $team2Members->pluck('id')->toArray(),
            ];
            $relayIndices = ['team1' => 0, 'team2' => 0];
            $gameState['active_player'] = [
                'team1' => $playerOrder['team1'][0] ?? null,
                'team2' => $playerOrder['team2'][0] ?? null,
            ];
        }

        return LeagueTeamMatch::create([
            'team1_id' => $team->id,
            'team2_id' => $opponent->id,
            'team1_level' => $team1Level,
            'team2_level' => $team2Level,
            'status' => 'playing',
            'game_mode' => $gameMode,
            'match_division' => $matchDivision,
            'duel_pairings' => $duelPairings,
            'player_order' => $playerOrder,
            'relay_indices' => $relayIndices,
            'game_state' => $gameState,
        ]);
    }
    
    private function createDuelPairings($team1Members, $team2Members): array
    {
        $team1Sorted = $team1Members->sortByDesc(fn($u) => $u->duoStats?->level ?? 1)->values();
        $team2Sorted = $team2Members->sortByDesc(fn($u) => $u->duoStats?->level ?? 1)->values();
        
        $pairings = [];
        $minCount = min($team1Sorted->count(), $team2Sorted->count());
        
        for ($i = 0; $i < $minCount; $i++) {
            $p1 = $team1Sorted[$i];
            $p2 = $team2Sorted[$i];
            $pairings[] = [
                'rank' => $i + 1,
                'player1' => [
                    'id' => $p1->id,
                    'name' => $p1->name,
                    'level' => $p1->duoStats?->level ?? 1,
                ],
                'player2' => [
                    'id' => $p2->id,
                    'name' => $p2->name,
                    'level' => $p2->duoStats?->level ?? 1,
                ],
            ];
        }
        
        return $pairings;
    }
    
    public function canPlayerUseSkill(LeagueTeamMatch $match, int $userId): bool
    {
        $gameMode = $match->game_mode;
        
        if ($gameMode === 'classique') {
            return true;
        }
        
        if ($gameMode === 'bataille') {
            return true;
        }
        
        if ($gameMode === 'relais') {
            $gameState = $match->game_state;
            $activePlayer = $gameState['active_player'] ?? [];
            return in_array($userId, $activePlayer);
        }
        
        return false;
    }
    
    public function advanceRelayPlayer(LeagueTeamMatch $match, int $teamIndex): void
    {
        if ($match->game_mode !== 'relais') return;
        
        $gameState = $match->game_state;
        $relayIndices = $match->relay_indices ?? ['team1' => 0, 'team2' => 0];
        $teamKey = "team{$teamIndex}";
        $currentIndex = $relayIndices[$teamKey] ?? 0;
        $playerOrder = $match->player_order[$teamKey] ?? [];
        
        if (empty($playerOrder)) return;
        
        $nextIndex = ($currentIndex + 1) % count($playerOrder);
        $relayIndices[$teamKey] = $nextIndex;
        
        $gameState['active_player'][$teamKey] = $playerOrder[$nextIndex] ?? null;
        
        $match->update([
            'game_state' => $gameState,
            'relay_indices' => $relayIndices,
        ]);
    }
    
    public function getDuelForPlayer(LeagueTeamMatch $match, int $userId): ?array
    {
        if ($match->game_mode !== 'bataille') return null;
        
        $pairings = $match->duel_pairings ?? [];
        
        foreach ($pairings as $duel) {
            if (($duel['player1']['id'] ?? 0) === $userId || ($duel['player2']['id'] ?? 0) === $userId) {
                return $duel;
            }
        }
        
        return null;
    }

    private function findOpponent(Team $team): ?Team
    {
        return Team::where('division', $team->division)
            ->where('id', '!=', $team->id)
            ->has('teamMembers', '=', 5)
            ->inRandomOrder()
            ->first();
    }

    public function getNextQuestion(LeagueTeamMatch $match): array
    {
        $gameState = $match->game_state;
        $currentRound = $gameState['current_round'];
        $currentQuestion = $gameState['current_question'];

        if ($currentQuestion >= 10) {
            return [];
        }

        $theme = $this->selectTheme($match);
        $difficulty = $this->calculateDifficulty($match);
        
        $question = $this->questionService->getQuestion($theme, $difficulty);

        $gameState['questions'][$currentRound][$currentQuestion] = [
            'id' => $question['id'],
            'question' => $question['question'],
            'answers' => $question['answers'],
            'correct_answer' => $question['correct_answer'],
            'theme' => $theme,
        ];

        $match->update(['game_state' => $gameState]);

        return [
            'question' => $question['question'],
            'answers' => $question['answers'],
            'question_number' => $currentQuestion + 1,
            'round' => $currentRound + 1,
            'theme' => $theme,
        ];
    }

    private function selectTheme(LeagueTeamMatch $match): string
    {
        $themes = ['culture', 'sport', 'science', 'histoire', 'geographie', 'divertissement', 'art'];
        return $themes[array_rand($themes)];
    }

    private function calculateDifficulty(LeagueTeamMatch $match): int
    {
        $avgLevel = ($match->team1_level + $match->team2_level) / 2;
        
        if ($avgLevel <= 10) return 1;
        if ($avgLevel <= 30) return 2;
        if ($avgLevel <= 60) return 3;
        return 4;
    }

    public function processBuzz(LeagueTeamMatch $match, User $user, float $buzzTime): array
    {
        $gameState = $match->game_state;
        
        $result = $this->buzzManagerService->registerBuzz(
            $gameState,
            $user->id,
            $buzzTime
        );

        $match->update(['game_state' => $result['game_state']]);

        return $result;
    }

    public function submitAnswer(LeagueTeamMatch $match, User $user, string $answer): array
    {
        $gameState = $match->game_state;
        $currentRound = $gameState['current_round'];
        $currentQuestion = $gameState['current_question'];

        $correctAnswer = $gameState['questions'][$currentRound][$currentQuestion]['correct_answer'];

        $result = $this->buzzManagerService->submitAnswer(
            $gameState,
            $user->id,
            $answer,
            $correctAnswer
        );

        $gameState = $result['game_state'];
        $gameState['current_question']++;

        $match->update(['game_state' => $gameState]);

        $isRoundOver = $gameState['current_question'] >= 10;
        $roundResult = null;

        if ($isRoundOver) {
            $roundResult = $this->gameStateService->endRound($gameState);
            $gameState = $roundResult['game_state'];
            $match->update(['game_state' => $gameState]);

            if ($roundResult['match_over']) {
                $this->finalizeMatch($match, $gameState);
            }
        }

        return [
            'correct' => $result['correct'],
            'points_awarded' => $result['points_awarded'],
            'is_round_over' => $isRoundOver,
            'round_result' => $roundResult,
            'game_state' => $gameState,
        ];
    }

    private function finalizeMatch(LeagueTeamMatch $match, array $gameState): void
    {
        $team1Players = array_filter($gameState['players'], fn($p) => $p['team_index'] === 1);
        $team2Players = array_filter($gameState['players'], fn($p) => $p['team_index'] === 2);

        $team1Score = array_sum(array_column($team1Players, 'total_score'));
        $team2Score = array_sum(array_column($team2Players, 'total_score'));

        $winnerTeamId = null;
        $team1Points = 0;
        $team2Points = 0;

        $matchDivision = $match->match_division ?? 'bronze';
        
        if ($team1Score > $team2Score) {
            $winnerTeamId = $match->team1_id;
            $team1Points = $this->calculatePointsEarned($match->team1_level, $match->team2_level, true);
            $team2Points = -3;
            $this->awardCoinsToTeam($match->team1_id, $matchDivision);
        } elseif ($team2Score > $team1Score) {
            $winnerTeamId = $match->team2_id;
            $team2Points = $this->calculatePointsEarned($match->team2_level, $match->team1_level, true);
            $team1Points = -3;
            $this->awardCoinsToTeam($match->team2_id, $matchDivision);
        }

        $match->update([
            'winner_team_id' => $winnerTeamId,
            'status' => 'finished',
            'team1_points_earned' => $team1Points,
            'team2_points_earned' => $team2Points,
        ]);

        $this->updateTeamStats($match->team1_id, $winnerTeamId === $match->team1_id, $team1Points);
        $this->updateTeamStats($match->team2_id, $winnerTeamId === $match->team2_id, $team2Points);
    }

    private function calculatePointsEarned(int $myLevel, int $opponentLevel, bool $won): int
    {
        if (!$won) return -3;

        $levelDiff = $opponentLevel - $myLevel;
        
        if ($levelDiff >= 2) return 20;
        if ($levelDiff === 1) return 15;
        if ($levelDiff === 0) return 10;
        if ($levelDiff === -1) return 5;
        return 3;
    }
    
    public function calculateCoinsEarned(string $matchDivision, bool $won): int
    {
        if (!$won) return 0;
        
        $divisionCoins = [
            'bronze' => 10,
            'argent' => 20, 'silver' => 20,
            'or' => 40, 'gold' => 40,
            'platine' => 80, 'platinum' => 80,
            'diamant' => 160, 'diamond' => 160,
        ];
        
        return $divisionCoins[strtolower($matchDivision)] ?? 10;
    }
    
    private function awardCoinsToTeam(int $teamId, string $matchDivision): void
    {
        $coinsEarned = $this->calculateCoinsEarned($matchDivision, true);
        $team = Team::with('members')->find($teamId);
        
        if ($team) {
            foreach ($team->members as $member) {
                $member->increment('competence_coins', $coinsEarned);
            }
        }
    }
    
    public function deductAccessCost(User $user, string $targetDivision, string $teamDivision): bool
    {
        $divisions = ['bronze' => 0, 'argent' => 1, 'silver' => 1, 'or' => 2, 'gold' => 2, 'platine' => 3, 'platinum' => 3, 'diamant' => 4, 'diamond' => 4];
        $divisionCoins = ['bronze' => 10, 'argent' => 20, 'silver' => 20, 'or' => 40, 'gold' => 40, 'platine' => 80, 'platinum' => 80, 'diamant' => 160, 'diamond' => 160];
        
        $teamIndex = $divisions[strtolower($teamDivision)] ?? 0;
        $targetIndex = $divisions[strtolower($targetDivision)] ?? 0;
        
        if ($targetIndex <= $teamIndex) {
            return true;
        }
        
        $accessCost = ($divisionCoins[strtolower($targetDivision)] ?? 10) * 2;
        
        if ($user->competence_coins >= $accessCost) {
            $user->decrement('competence_coins', $accessCost);
            return true;
        }
        
        return false;
    }

    private function updateTeamStats(int $teamId, bool $won, int $pointsEarned): void
    {
        $team = Team::find($teamId);
        
        $newPoints = max(0, $team->points + $pointsEarned);
        $newDivision = $this->divisionService->getDivisionFromPoints($newPoints);

        $team->update([
            'points' => $newPoints,
            'division' => $newDivision,
            'matches_played' => $team->matches_played + 1,
            'matches_won' => $won ? $team->matches_won + 1 : $team->matches_won,
            'matches_lost' => !$won ? $team->matches_lost + 1 : $team->matches_lost,
        ]);
    }

    public function getTeamRankings(string $division, int $limit = 20): array
    {
        return Team::with(['captain', 'teamMembers.user'])
            ->where('division', $division)
            ->orderBy('points', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($team, $index) {
                return [
                    'rank' => $index + 1,
                    'team' => $team,
                    'win_rate' => $team->matches_played > 0 
                        ? round(($team->matches_won / $team->matches_played) * 100, 1)
                        : 0,
                ];
            })
            ->toArray();
    }
}
