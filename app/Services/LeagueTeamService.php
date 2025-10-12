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

    public function initializeTeamMatch(Team $team): LeagueTeamMatch
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

        $team1Members = $team->teamMembers()->with('user')->get()->pluck('user');
        $team2Members = $opponent->teamMembers()->with('user')->get()->pluck('user');

        $allPlayers = $team1Members->concat($team2Members)->map(function ($user, $index) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->strategic_avatar ?? null,
                'team_index' => $index < 5 ? 1 : 2,
            ];
        })->toArray();

        $gameState = $this->gameStateService->initializeGame($allPlayers, 'league_team');

        return LeagueTeamMatch::create([
            'team1_id' => $team->id,
            'team2_id' => $opponent->id,
            'team1_level' => $team->level,
            'team2_level' => $opponent->level,
            'status' => 'playing',
            'game_state' => $gameState,
        ]);
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

        if ($team1Score > $team2Score) {
            $winnerTeamId = $match->team1_id;
            $team1Points = $this->calculatePointsEarned($match->team1_level, $match->team2_level, true);
            $team2Points = -2;
        } elseif ($team2Score > $team1Score) {
            $winnerTeamId = $match->team2_id;
            $team2Points = $this->calculatePointsEarned($match->team2_level, $match->team1_level, true);
            $team1Points = -2;
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
        if (!$won) return -2;

        if ($opponentLevel > $myLevel) return 5;
        if ($opponentLevel === $myLevel) return 2;
        return 1;
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
