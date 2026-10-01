<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require database_path('data/exercises.php') as [$slug, $name, $muscles, $equipment, $kind, $slots, $ratio]) {
            Exercise::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'primary_muscles' => $muscles,
                'equipment' => $equipment,
                'kind' => $kind,
                'slots' => $slots,
                'is_bodyweight' => $ratio === null,
                'start_ratio' => $ratio === null ? null : self::ratios($ratio),
            ]);
        }
    }

    /** @return array{male: array<string, float>, female: array<string, float>} */
    public static function ratios(float $maleBeginner): array
    {
        $levels = fn (float $base) => [
            'beginner' => round($base, 3),
            'intermediate' => round($base * 1.5, 3),
            'advanced' => round($base * 2, 3),
        ];

        return ['male' => $levels($maleBeginner), 'female' => $levels($maleBeginner * 0.6)];
    }
}
