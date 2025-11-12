<?php

namespace App\Services;

use App\Models\PlayerStatistic;
use App\Models\User;

class StatisticsService
{
    public function recordRoundStatistics(
        int $userId,
        string $gameMode,
        string $gameId,
        int $roundNumber,
        array $roundData
    ): PlayerStatistic {
        $stats = $this->calculateMetrics($roundData);
        
        return PlayerStatistic::create([
            'user_id' => $userId,
            'game_mode' => $gameMode,
            'scope' => 'round',
            'game_id' => $gameId,
            'round_number' => $roundNumber,
            'total_questions' => $roundData['total_questions'],
            'questions_buzzed' => $roundData['questions_buzzed'],
            'correct_answers' => $roundData['correct_answers'],
            'wrong_answers' => $roundData['wrong_answers'],
            'points_earned' => $roundData['points_earned'],
            'points_possible' => $roundData['points_possible'],
            'efficacite_manche' => $stats['efficacite_manche'],
            'taux_participation' => $stats['taux_participation'],
            'taux_precision' => $stats['taux_precision'],
            'ratio_performance' => $stats['ratio_performance'],
            'details' => $roundData['details'] ?? null,
        ]);
    }

    public function recordMatchStatistics(
        int $userId,
        string $gameMode,
        string $gameId,
        array $matchData
    ): PlayerStatistic {
        $stats = $this->calculateMetrics($matchData);
        
        // Utiliser ratio_performance comme efficacite_partie (calculé directement sur les données du match)
        // Cela évite de dépendre d'une requête SQL sur les rounds qui pourrait être vide
        $efficacitePartie = $stats['ratio_performance'];
        
        return PlayerStatistic::create([
            'user_id' => $userId,
            'game_mode' => $gameMode,
            'scope' => 'match',
            'game_id' => $gameId,
            'round_number' => null,
            'total_questions' => $matchData['total_questions'],
            'questions_buzzed' => $matchData['questions_buzzed'],
            'correct_answers' => $matchData['correct_answers'],
            'wrong_answers' => $matchData['wrong_answers'],
            'points_earned' => $matchData['points_earned'],
            'points_possible' => $matchData['points_possible'],
            'efficacite_partie' => round($efficacitePartie, 2),
            'taux_participation' => $stats['taux_participation'],
            'taux_precision' => $stats['taux_precision'],
            'ratio_performance' => $stats['ratio_performance'],
            'details' => $matchData['details'] ?? null,
        ]);
    }

    public function updateGlobalStatistics(int $userId, string $gameMode): PlayerStatistic
    {
        $matchStats = PlayerStatistic::where('user_id', $userId)
            ->where('game_mode', $gameMode)
            ->where('scope', 'match')
            ->get();

        $last10Matches = PlayerStatistic::where('user_id', $userId)
            ->where('game_mode', $gameMode)
            ->where('scope', 'match')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        $efficaciteJoueur = $last10Matches->avg('efficacite_partie') ?? 0;

        $aggregated = [
            'total_questions' => $matchStats->sum('total_questions'),
            'questions_buzzed' => $matchStats->sum('questions_buzzed'),
            'correct_answers' => $matchStats->sum('correct_answers'),
            'wrong_answers' => $matchStats->sum('wrong_answers'),
            'points_earned' => $matchStats->sum('points_earned'),
            'points_possible' => $matchStats->sum('points_possible'),
        ];

        $stats = $this->calculateMetrics($aggregated);

        return PlayerStatistic::updateOrCreate(
            [
                'user_id' => $userId,
                'game_mode' => $gameMode,
                'scope' => 'global',
            ],
            [
                'game_id' => null,
                'round_number' => null,
                'total_questions' => $aggregated['total_questions'],
                'questions_buzzed' => $aggregated['questions_buzzed'],
                'correct_answers' => $aggregated['correct_answers'],
                'wrong_answers' => $aggregated['wrong_answers'],
                'points_earned' => $aggregated['points_earned'],
                'points_possible' => $aggregated['points_possible'],
                'efficacite_joueur' => round($efficaciteJoueur, 2),
                'taux_participation' => $stats['taux_participation'],
                'taux_precision' => $stats['taux_precision'],
                'ratio_performance' => $stats['ratio_performance'],
            ]
        );
    }

    private function calculateMetrics(array $data): array
    {
        $totalQuestions = $data['total_questions'] ?? 0;
        $questionsBuzzed = $data['questions_buzzed'] ?? 0;
        $correctAnswers = $data['correct_answers'] ?? 0;
        $pointsEarned = $data['points_earned'] ?? 0;
        $pointsPossible = $data['points_possible'] ?? 0;

        // Efficacité de la manche : (Points réels gagnés / Points max possibles) × 100
        // Points max possibles = nb_questions × 2
        $efficaciteManche = $pointsPossible > 0 
            ? ($pointsEarned / $pointsPossible) * 100 
            : 0;

        // Taux de participation : (Questions buzzées / Total questions) × 100
        $tauxParticipation = $totalQuestions > 0 
            ? ($questionsBuzzed / $totalQuestions) * 100 
            : 0;

        // Taux de précision : (Bonnes réponses / Buzz tentés) × 100
        $tauxPrecision = $questionsBuzzed > 0 
            ? ($correctAnswers / $questionsBuzzed) * 100 
            : 0;

        // Ratio performance : (Points obtenus / Points max des buzz) × 100
        // Seulement sur les questions buzzées
        $ratioPerformance = $pointsPossible > 0 
            ? ($pointsEarned / $pointsPossible) * 100 
            : 0;

        return [
            'efficacite_manche' => round($efficaciteManche, 2),
            'taux_participation' => round($tauxParticipation, 2),
            'taux_precision' => round($tauxPrecision, 2),
            'ratio_performance' => round($ratioPerformance, 2),
        ];
    }

    public function getPlayerStatistics(int $userId, string $gameMode, string $scope = 'global')
    {
        return PlayerStatistic::where('user_id', $userId)
            ->where('game_mode', $gameMode)
            ->where('scope', $scope)
            ->first();
    }

    public function getMatchHistory(int $userId, string $gameMode, int $limit = 10)
    {
        return PlayerStatistic::where('user_id', $userId)
            ->where('game_mode', $gameMode)
            ->where('scope', 'match')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRoundStatistics(int $userId, string $gameId)
    {
        return PlayerStatistic::where('user_id', $userId)
            ->where('game_id', $gameId)
            ->where('scope', 'round')
            ->orderBy('round_number')
            ->get();
    }
}
