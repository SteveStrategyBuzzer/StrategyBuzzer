<?php

namespace App\Services;

class SkillCatalog
{
    const TYPE_PERSONAL = 'personal';
    const TYPE_ATTACK = 'attack';
    const TYPE_DEFENSE = 'defense';
    
    const TRIGGER_ON_QUESTION = 'on_question';
    const TRIGGER_ON_ANSWER = 'on_answer';
    const TRIGGER_ON_RESULT = 'on_result';
    const TRIGGER_ON_ERROR = 'on_error';
    const TRIGGER_ON_VICTORY = 'on_victory';
    const TRIGGER_ALWAYS = 'always';
    const TRIGGER_MATCH_START = 'match_start';
    
    public static function getAll(): array
    {
        return [
            'illuminate_numbers' => [
                'id' => 'illuminate_numbers',
                'name' => 'Illumine si chiffre',
                'icon' => '💡',
                'description' => 'Met en évidence la bonne réponse si elle contient un chiffre',
                'avatar' => 'mathematicien',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'acidify_error' => [
                'id' => 'acidify_error',
                'name' => 'Acidifie 2 erreurs',
                'icon' => '🧪',
                'description' => 'Acidifie 2 mauvaises réponses avant de choisir (1 fois par partie)',
                'avatar' => 'scientifique',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'see_opponent_choice' => [
                'id' => 'see_opponent_choice',
                'name' => 'Voit choix adverse',
                'icon' => '👁️',
                'description' => 'Voit le choix de l\'adversaire (ou la réponse la plus cliquée en Master)',
                'avatar' => 'explorateur',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'block_attack' => [
                'id' => 'block_attack',
                'name' => 'Annule attaque',
                'icon' => '🛡️',
                'description' => 'Bloque la prochaine attaque adverse',
                'avatar' => 'defenseur',
                'type' => self::TYPE_DEFENSE,
                'trigger' => self::TRIGGER_ALWAYS,
                'auto' => true,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'fake_score' => [
                'id' => 'fake_score',
                'name' => 'Score masqué',
                'icon' => '🎭',
                'description' => 'Affiche un score moins élevé à l\'adversaire jusqu\'à la fin',
                'avatar' => 'comedienne',
                'type' => self::TYPE_ATTACK,
                'trigger' => self::TRIGGER_MATCH_START,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => true,
            ],
            'invert_answers' => [
                'id' => 'invert_answers',
                'name' => 'Trompe réponse',
                'icon' => '🔄',
                'description' => 'Trompe l\'adversaire: une bonne réponse apparaît mauvaise',
                'avatar' => 'comedienne',
                'type' => self::TYPE_ATTACK,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => true,
            ],
            'bonus_question' => [
                'id' => 'bonus_question',
                'name' => 'Question bonus',
                'icon' => '❓',
                'description' => 'Ajoute une question bonus à la fin de la manche',
                'avatar' => 'magicienne',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_RESULT,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'cancel_error' => [
                'id' => 'cancel_error',
                'name' => 'Annule erreur',
                'icon' => '✨',
                'description' => 'Annule une erreur commise',
                'avatar' => 'magicienne',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ERROR,
                'auto' => true,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'shuffle_answers' => [
                'id' => 'shuffle_answers',
                'name' => 'Mélange réponses',
                'icon' => '🔀',
                'description' => 'Les réponses de l\'adversaire changent d\'emplacement toutes les 2s',
                'avatar' => 'challenger',
                'type' => self::TYPE_ATTACK,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => false,
                'uses_per_match' => 3,
                'affects_opponent' => true,
            ],
            'reduce_time' => [
                'id' => 'reduce_time',
                'name' => 'Diminue temps',
                'icon' => '⏱️',
                'description' => 'Diminue le compte à rebours des autres joueurs',
                'avatar' => 'challenger',
                'type' => self::TYPE_ATTACK,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => false,
                'uses_per_match' => 2,
                'affects_opponent' => true,
            ],
            'knowledge_without_time' => [
                'id' => 'knowledge_without_time',
                'name' => 'Savoir sans temps',
                'icon' => '🪶',
                'description' => 'Répondre sans buzzer (+1 max)',
                'avatar' => 'historien',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'history_corrects' => [
                'id' => 'history_corrects',
                'name' => "L'histoire corrige",
                'icon' => '📜',
                'description' => 'Récupérer les points après erreur',
                'avatar' => 'historien',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_RESULT,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'ai_suggestion' => [
                'id' => 'ai_suggestion',
                'name' => 'Suggestion IA',
                'icon' => '🤖',
                'description' => 'L\'IA illumine une réponse (1 fois)',
                'avatar' => 'ia-junior',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'eliminate_two' => [
                'id' => 'eliminate_two',
                'name' => 'Élimine 2',
                'icon' => '❌',
                'description' => 'Élimine 2 mauvaises réponses sur les 4',
                'avatar' => 'ia-junior',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'replay' => [
                'id' => 'replay',
                'name' => 'Reprendre',
                'icon' => '🔁',
                'description' => 'Reprendre une réponse 1 fois',
                'avatar' => 'ia-junior',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ERROR,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'coin_bonus' => [
                'id' => 'coin_bonus',
                'name' => '+20% pièces',
                'icon' => '💰',
                'description' => 'Gagne 20% de pièces en plus',
                'avatar' => 'stratege',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_VICTORY,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'create_team' => [
                'id' => 'create_team',
                'name' => 'Créer team',
                'icon' => '👥',
                'description' => 'Permet de créer et gérer une équipe',
                'avatar' => 'stratege',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ALWAYS,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'avatar_discount' => [
                'id' => 'avatar_discount',
                'name' => '-10% avatars',
                'icon' => '🏷️',
                'description' => 'Réduction de 10% sur les avatars en boutique',
                'avatar' => 'stratege',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ALWAYS,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'faster_buzz' => [
                'id' => 'faster_buzz',
                'name' => 'Buzzer + rapide',
                'icon' => '⚡',
                'description' => 'Peut reculer son temps de buzzer jusqu\'à 0.5s du plus rapide',
                'avatar' => 'sprinteur',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ALWAYS,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'extra_reflection' => [
                'id' => 'extra_reflection',
                'name' => '+3s réflexion',
                'icon' => '🧠',
                'description' => '3 secondes de réflexion en plus (1 fois)',
                'avatar' => 'sprinteur',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'auto_reactivation' => [
                'id' => 'auto_reactivation',
                'name' => 'Auto-réactivation',
                'icon' => '🔄',
                'description' => 'Les skills se réactivent automatiquement après chaque niveau',
                'avatar' => 'sprinteur',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ALWAYS,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'preview_questions' => [
                'id' => 'preview_questions',
                'name' => '5 Q° futures',
                'icon' => '🔮',
                'description' => 'Voir 5 prochaines questions révélées en avance (5 fois par partie)',
                'avatar' => 'visionnaire',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_RESULT,
                'auto' => false,
                'uses_per_match' => 5,
                'affects_opponent' => false,
            ],
            'counter_challenger' => [
                'id' => 'counter_challenger',
                'name' => 'Contre Challenger',
                'icon' => '🛡️',
                'description' => 'Contrer une attaque du Challenger (1 fois par partie)',
                'avatar' => 'visionnaire',
                'type' => self::TYPE_DEFENSE,
                'trigger' => self::TRIGGER_ALWAYS,
                'auto' => true,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'lock_correct' => [
                'id' => 'lock_correct',
                'name' => '2 pts sécurisés',
                'icon' => '🔒',
                'description' => 'Si sur 2 points, seule la bonne réponse est sélectionnable',
                'avatar' => 'visionnaire',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ANSWER,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
        ];
    }
    
    public static function getSkill(string $skillId): ?array
    {
        $skills = self::getAll();
        return $skills[$skillId] ?? null;
    }
    
    public static function getSkillsByType(string $type): array
    {
        return array_filter(self::getAll(), fn($s) => $s['type'] === $type);
    }
    
    public static function getAttackSkills(): array
    {
        return self::getSkillsByType(self::TYPE_ATTACK);
    }
    
    public static function getDefenseSkills(): array
    {
        return self::getSkillsByType(self::TYPE_DEFENSE);
    }
    
    public static function getPersonalSkills(): array
    {
        return self::getSkillsByType(self::TYPE_PERSONAL);
    }
    
    public static function getSkillsForAvatar(string $avatarSlug): array
    {
        return array_filter(self::getAll(), fn($s) => $s['avatar'] === $avatarSlug);
    }
    
    public static function isAttackSkill(string $skillId): bool
    {
        $skill = self::getSkill($skillId);
        return $skill && $skill['type'] === self::TYPE_ATTACK;
    }
    
    public static function isDefenseSkill(string $skillId): bool
    {
        $skill = self::getSkill($skillId);
        return $skill && $skill['type'] === self::TYPE_DEFENSE;
    }
    
    public static function affectsOpponent(string $skillId): bool
    {
        $skill = self::getSkill($skillId);
        return $skill && ($skill['affects_opponent'] ?? false);
    }
}
