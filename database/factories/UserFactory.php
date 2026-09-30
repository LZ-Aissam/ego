<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\IdentityKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'birth_date' => fake()->unique()->dateTimeBetween('-45 years', '-16 years')->format('Y-m-d'),
            'avatar_color' => fake()->randomElement(User::AVATAR_COLORS),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->identity_key ??= IdentityKey::make($user->first_name, $user->birth_date->format('Y-m-d'));
        });
    }

    public function withMorphology(string $sex = 'male', int $heightCm = 180, float $weightKg = 80.0, string $level = 'beginner'): static
    {
        return $this->state(fn () => [
            'sex' => $sex,
            'height_cm' => $heightCm,
            'weight_kg' => $weightKg,
            'level' => $level,
        ]);
    }
}
