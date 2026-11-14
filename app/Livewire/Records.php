<?php

namespace App\Livewire;

use App\Game\StrengthRanks;
use App\Services\RecordCalculator;
use App\Support\Sparkline;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Records')]
class Records extends Component
{
    public function render(RecordCalculator $calc, StrengthRanks $ranks)
    {
        $user = auth()->user();

        $records = $user->personalRecords()->with('exercise')->get()
            ->sortBy(fn ($r) => $r->exercise->name)
            ->map(fn ($r) => [
                'record' => $r,
                'ratio' => $r->exercise->is_bodyweight ? null : $calc->bodyweightRatio($r->max_weight_kg, $user->weight_kg),
            ]);

        // Poids du corps : 12 dernières pesées, et l'écart sur 4 semaines.
        $weights = $user->bodyWeights()->orderByDesc('date')->limit(12)->get()->sortBy('date')->values();
        $last = $weights->last();
        $before = $last ? $user->bodyWeights()->where('date', '<=', $last->date->copy()->subDays(21))->orderByDesc('date')->first() : null;

        return view('livewire.records', [
            'records' => $records,
            'ranks' => $ranks->for($user),
            'weights' => $weights,
            'weightPoints' => $weights->count() > 1 ? Sparkline::points($weights->pluck('kg')->all(), 320, 80) : '',
            'weightDelta' => $last && $before ? round($last->kg - $before->kg, 1) : null,
        ]);
    }
}
