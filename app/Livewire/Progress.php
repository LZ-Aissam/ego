<?php

namespace App\Livewire;

use App\Models\Exercise;
use App\Models\WorkoutSet;
use App\Support\Sparkline;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Progression')]
class Progress extends Component
{
    public Exercise $exercise;

    public function render()
    {
        $rows = WorkoutSet::query()
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', auth()->id())
            ->where('workout_sets.exercise_id', $this->exercise->id)
            ->groupBy('workouts.id', 'workouts.finished_at')
            ->orderByDesc('workouts.finished_at')
            ->limit(30)
            ->selectRaw('workouts.finished_at as finished_at, MAX(workout_sets.weight_kg) as max_kg')
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($row) => ['date' => Carbon::parse($row->finished_at), 'kg' => (float) $row->max_kg]);

        return view('livewire.progress', [
            'rows' => $rows,
            'points' => Sparkline::points($rows->pluck('kg')->all(), 320, 140),
        ]);
    }
}
