<?php

namespace App\Livewire;

use App\Services\RecordCalculator;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Records')]
class Records extends Component
{
    public function render(RecordCalculator $calc)
    {
        $user = auth()->user();

        $records = $user->personalRecords()->with('exercise')->get()
            ->sortBy(fn ($r) => $r->exercise->name)
            ->map(fn ($r) => [
                'record' => $r,
                'ratio' => $r->exercise->is_bodyweight ? null : $calc->bodyweightRatio($r->max_weight_kg, $user->weight_kg),
            ]);

        return view('livewire.records', ['records' => $records]);
    }
}
