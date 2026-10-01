<?php

namespace App\Console\Commands;

use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ForceVitesseProgram extends Command
{
    protected $signature = 'ego:programme-force-vitesse {prenom}';

    protected $description = 'Installe le programme Force × Vitesse (4 jours haut du corps, vendredi jambes explosives) et l\'active';

    public function handle(LoadPrescriber $prescriber): int
    {
        $user = User::where('first_name', $this->argument('prenom'))->first();
        if (! $user) {
            $this->error('Aucun joueur nommé « '.$this->argument('prenom').' ».');

            return self::FAILURE;
        }

        $exercises = Exercise::all()->keyBy('slug');
        $days = require database_path('data/programs/force-vitesse.php');

        $program = DB::transaction(function () use ($user, $exercises, $days) {
            $program = $user->programs()->create([
                'name' => 'Programme Force × Vitesse · 5 j',
                'split' => 'custom',
                'params' => ['level' => $user->level ?? 'advanced', 'goal' => 'strength', 'equipment' => 'gym', 'weekdays' => [1, 2, 3, 4, 5], 'custom' => true],
            ]);
            foreach ($days as $i => [$name, $lines]) {
                $day = $program->days()->create(['position' => $i + 1, 'name' => $name, 'type' => 'custom']);
                foreach ($lines as $j => [$slug, $intent, $sets, $min, $max, $rest]) {
                    $day->exercises()->create([
                        'exercise_id' => $exercises[$slug]->id, 'slot' => $exercises[$slug]->slots[0], 'position' => $j + 1,
                        'intent' => $intent, 'sets' => $sets, 'reps_min' => $min, 'reps_max' => $max, 'rest_seconds' => $rest,
                    ]);
                }
            }
            $user->forceFill(['active_program_id' => $program->id, 'goal' => 'strength', 'equipment' => 'gym', 'training_weekdays' => [1, 2, 3, 4, 5], 'days_per_week' => 5])->save();

            return $program;
        });

        if ($user->calibrated_at) {
            $prescriber->prescribeProgram($user->fresh());
        }

        $this->info('Programme activé pour '.$user->first_name.' : '.$program->name);

        return self::SUCCESS;
    }
}
