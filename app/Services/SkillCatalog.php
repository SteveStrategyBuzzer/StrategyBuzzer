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
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'acidify_error' => [
                'id' => 'acidify_error',
                'name' => 'Acidifie erreur',
                'icon' => '🧪',
                'description' => 'Marque visuellement une mauvaise réponse',
                'avatar' => 'scientifique',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'see_opponent_choice' => [
                'id' => 'see_opponent_choice',
                'name' => 'Voit choix adverse',
                'icon' => '👁️',
                'description' => 'Voir quelle réponse l\'adversaire a choisie',
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
                'name' => 'Score -',
                'icon' => '🎭',
                'description' => 'Affiche un faux score à l\'adversaire',
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
                'description' => 'Inverse visuellement les réponses de l\'adversaire',
                'avatar' => 'comedienne',
                'type' => self::TYPE_ATTACK,
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => false,
                'uses_per_match' => 2,
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
                'description' => 'Les réponses de l\'adversaire bougent toutes les secondes',
                'avatar' => 'challenger',
                'type' => self::TYPE_ATTACK,
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => false,
                'uses_per_match' => 3,
                'affects_opponent' => true,
            ],
            'reduce_time' => [
                'id' => 'reduce_time',
                'name' => 'Diminue temps',
                'icon' => '⏱️',
                'description' => 'Réduit le temps de réponse de l\'adversaire',
                'avatar' => 'challenger',
                'type' => self::TYPE_ATTACK,
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => false,
                'uses_per_match' => 2,
                'affects_opponent' => true,
            ],
            'text_hint' => [
                'id' => 'text_hint',
                'name' => 'Indice texte',
                'icon' => '📜',
                'description' => 'Affiche un indice textuel pour la question',
                'avatar' => 'historien',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => false,
                'uses_per_match' => 3,
                'affects_opponent' => false,
            ],
            'extra_time' => [
                'id' => 'extra_time',
                'name' => '+2s réponse',
                'icon' => '⏳',
                'description' => 'Ajoute 2 secondes au temps de réponse',
                'avatar' => 'historien',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'ai_suggestion' => [
                'id' => 'ai_suggestion',
                'name' => 'Suggestion IA',
                'icon' => '🤖',
                'description' => 'L\'IA suggère une réponse (80% de fiabilité)',
                'avatar' => 'ia-junior',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => false,
                'uses_per_match' => 3,
                'affects_opponent' => false,
            ],
            'eliminate_two' => [
                'id' => 'eliminate_two',
                'name' => 'Élimine 2',
                'icon' => '❌',
                'description' => 'Élimine 2 mauvaises réponses',
                'avatar' => 'ia-junior',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => false,
                'uses_per_match' => 2,
                'affects_opponent' => false,
            ],
            'replay' => [
                'id' => 'replay',
                'name' => 'Rejouer',
                'icon' => '🔁',
                'description' => 'Permet de rejouer une question ratée',
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
                'description' => 'Le buzzer réagit plus vite',
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
                'description' => 'Ajoute 3 secondes de réflexion',
                'avatar' => 'sprinteur',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'auto_reactivation' => [
                'id' => 'auto_reactivation',
                'name' => 'Auto-réactivation',
                'icon' => '🔄',
                'description' => 'Les skills se réactivent après 5 questions',
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
                'description' => 'Aperçu des 5 prochaines questions',
                'avatar' => 'visionnaire',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_QUESTION,
                'auto' => false,
                'uses_per_match' => 1,
                'affects_opponent' => false,
            ],
            'counter_challenger' => [
                'id' => 'counter_challenger',
                'name' => 'Contre Challenger',
                'icon' => '🛡️',
                'description' => 'Immunité contre les attaques du Challenger',
                'avatar' => 'visionnaire',
                'type' => self::TYPE_DEFENSE,
                'trigger' => self::TRIGGER_ALWAYS,
                'auto' => true,
                'uses_per_match' => -1,
                'affects_opponent' => false,
            ],
            'lock_correct' => [
                'id' => 'lock_correct',
                'name' => '2 pts sécurisés',
                'icon' => '🔒',
                'description' => 'Sécurise 2 points même en cas d\'erreur',
                'avatar' => 'visionnaire',
                'type' => self::TYPE_PERSONAL,
                'trigger' => self::TRIGGER_ON_ERROR,
                'auto' => true,
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
