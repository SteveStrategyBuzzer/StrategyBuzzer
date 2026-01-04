<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class QuestionPlanBuilder
{
    public static function build(array $settings): array
    {
        $questionsPerRound = $settings['nb_questions'] ?? 10;
        $rounds = $settings['nb_rounds'] ?? 3;
        $hasStrategicAvatar = !empty($settings['strategic_avatar']) && $settings['strategic_avatar'] !== 'Aucun';
        $hasSkillBonus = $hasStrategicAvatar && ($settings['skill_bonus_enabled'] ?? true);
        
        $mainQuestions = $questionsPerRound * $rounds;
        $skillBonusQuestions = $hasSkillBonus ? self::calculateSkillBonusQuestions($settings) : 0;
        $tiebreakerQuestions = $settings['tiebreaker_questions'] ?? 5;
        
        $totalQuestions = $mainQuestions + $skillBonusQuestions + $tiebreakerQuestions;
        
        $plan = [
            'total_questions' => $totalQuestions,
            'main_questions' => $mainQuestions,
            'skill_bonus_questions' => $skillBonusQuestions,
            'tiebreaker_questions' => $tiebreakerQuestions,
            'questions_per_round' => $questionsPerRound,
            'rounds' => $rounds,
            'has_strategic_avatar' => $hasStrategicAvatar,
            'has_skill_bonus' => $hasSkillBonus,
            'blocs' => self::buildBlocs($mainQuestions, $skillBonusQuestions, $tiebreakerQuestions),
        ];
        
        Log::info('[QuestionPlanBuilder] Plan built', $plan);
        
        return $plan;
    }
    
    protected static function calculateSkillBonusQuestions(array $settings): int
    {
        $questionsPerRound = $settings['nb_questions'] ?? 10;
        return min(3, (int) ceil($questionsPerRound / 10));
    }
    
    protected static function buildBlocs(int $main, int $skillBonus, int $tiebreaker): array
    {
        $blocs = [];
        $currentIndex = 1;
        $blocSize = 4;
        
        while ($currentIndex <= $main) {
            $endIndex = min($currentIndex + $blocSize - 1, $main);
            $blocs[] = [
                'type' => 'main',
                'start_index' => $currentIndex,
                'end_index' => $endIndex,
                'count' => $endIndex - $currentIndex + 1,
            ];
            $currentIndex = $endIndex + 1;
        }
        
        if ($skillBonus > 0) {
            $skillStart = $main + 1;
            $skillEnd = $main + $skillBonus;
            $blocs[] = [
                'type' => 'skill_bonus',
                'start_index' => $skillStart,
                'end_index' => $skillEnd,
                'count' => $skillBonus,
            ];
        }
        
        if ($tiebreaker > 0) {
            $tiebreakerStart = $main + $skillBonus + 1;
            $tiebreakerEnd = $main + $skillBonus + $tiebreaker;
            $blocs[] = [
                'type' => 'tiebreaker',
                'start_index' => $tiebreakerStart,
                'end_index' => $tiebreakerEnd,
                'count' => $tiebreaker,
            ];
        }
        
        return $blocs;
    }
    
    public static function getTotalForMode(string $mode, array $settings): int
    {
        $plan = self::build($settings);
        return $plan['total_questions'];
    }
    
    public static function getQuestionTypeByIndex(int $index, int $mainQuestions, int $skillBonusQuestions): string
    {
        if ($index <= $mainQuestions) {
            return 'main';
        }
        
        if ($index <= $mainQuestions + $skillBonusQuestions) {
            return 'skill_bonus';
        }
        
        return 'tiebreaker';
    }
    
    public static function getQuestionMetadataByType(string $type): array
    {
        return match ($type) {
            'skill_bonus' => [
                'is_skill_bonus' => true,
                'is_tiebreaker' => false,
                'difficulty_modifier' => 1.2,
                'time_bonus' => true,
            ],
            'tiebreaker' => [
                'is_skill_bonus' => false,
                'is_tiebreaker' => true,
                'difficulty_modifier' => 1.5,
                'time_bonus' => false,
            ],
            default => [
                'is_skill_bonus' => false,
                'is_tiebreaker' => false,
                'difficulty_modifier' => 1.0,
                'time_bonus' => false,
            ],
        };
    }
}
