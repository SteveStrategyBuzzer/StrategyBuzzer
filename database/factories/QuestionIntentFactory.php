<?php

namespace Database\Factories;

use App\Models\QuestionIntent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionIntent>
 */
class QuestionIntentFactory extends Factory
{
    protected $model = QuestionIntent::class;

    public function definition(): array
    {
        $domains = ['Géographie', 'Sciences', 'Histoire', 'Culture', 'Technologie'];
        $domain  = $this->faker->randomElement($domains);

        return [
            'intent_key'       => $this->faker->unique()->slug(6),
            'semantic_key'     => null,
            'language_source'  => 'en',
            'domain'           => $domain,
            'sub_domain'       => $this->faker->word(),
            'difficulty_depth' => $this->faker->numberBetween(1, 10),
            'subject'          => $this->faker->words(2, true),
            'angle_large'      => $this->faker->word(),
            'micro_angle'      => $this->faker->word(),
            'answer_target'    => $this->faker->sentence(),
            'potential_trap'   => $this->faker->sentence(),
            'concept_family'   => $this->faker->word(),
            'source'           => 'kernel',
            'dialysis_status'  => 'pending',
            'kernel_code'      => null,
        ];
    }
}
