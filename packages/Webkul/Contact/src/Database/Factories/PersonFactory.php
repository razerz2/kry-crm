<?php

namespace Webkul\Contact\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Contact\Models\Person;

class PersonFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Person::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'emails' => [[
                'value' => $this->faker->unique()->safeEmail(),
                'label' => 'work',
            ]],
            'contact_numbers' => [[
                'value' => (string) $this->faker->randomNumber(9),
                'label' => 'work',
            ]],
        ];
    }
}
