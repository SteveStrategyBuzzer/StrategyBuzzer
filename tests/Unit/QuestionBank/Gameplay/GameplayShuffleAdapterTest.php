<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Gameplay;

use App\Services\QuestionBank\CreatorDomainRegistry;
use App\Services\QuestionBank\Gameplay\GameplayShuffleAdapter;
use Tests\TestCase;

final class GameplayShuffleAdapterTest extends TestCase
{
    public function test_shuffle_aliases_expand_using_configured_order_and_labels(): void
    {
        $config = config('question_bank_profiles');
        $expected = [
            'Histoire',
            'Sport',
            'Géographie',
            'Art',
            'Cuisine',
            'Science',
            'Cinéma',
            'Faune',
        ];

        $this->assertSame($expected, GameplayShuffleAdapter::subDomains($config));
        $this->assertSame($expected, GameplayShuffleAdapter::resolveDomainList('general', $config));
        $this->assertSame($expected, GameplayShuffleAdapter::resolveDomainList('général', $config));
        $this->assertSame($config['general_sub_domain_weights'], GameplayShuffleAdapter::weights($config));
    }

    public function test_non_shuffle_theme_remains_a_single_gameplay_filter(): void
    {
        $config = config('question_bank_profiles');

        $this->assertSame(['Histoire'], GameplayShuffleAdapter::resolveDomainList('Histoire', $config));
    }

    public function test_shuffle_is_not_a_creator_registry_identity(): void
    {
        $this->assertTrue(GameplayShuffleAdapter::isShuffleTheme('general'));
        $this->assertFalse(GameplayShuffleAdapter::isShuffleTheme('Culture générale'));

        $this->expectException(\App\Exceptions\QuestionBank\KernelCodeEngineException::class);
        CreatorDomainRegistry::fromInput('General');
    }
}