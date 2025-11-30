<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'age_group' => fake()->randomElement(array_keys(Profile::AGE_GROUPS)),
            'is_default' => false,
        ];
    }

    /**
     * Mark the profile as default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Set a specific age group.
     */
    public function ageGroup(string $ageGroup): static
    {
        return $this->state(fn (array $attributes) => [
            'age_group' => $ageGroup,
        ]);
    }

    /**
     * Kids age group (7-10).
     */
    public function kids(): static
    {
        return $this->ageGroup('8');
    }

    /**
     * Pre-teen age group (11-13).
     */
    public function preTeen(): static
    {
        return $this->ageGroup('12');
    }

    /**
     * Teen age group (14-17).
     */
    public function teen(): static
    {
        return $this->ageGroup('16');
    }

    /**
     * Adult age group (18+).
     */
    public function adult(): static
    {
        return $this->ageGroup('18');
    }
}
