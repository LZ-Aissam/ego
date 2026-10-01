<?php

namespace App\Livewire;

use App\Models\Exercise;
use App\Services\ProgressStats;
use App\Support\Sparkline;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Progression')]
class Progress extends Component
{
    public const METRICS = ['max_kg' => 'Charge max', 'e1rm' => '1RM estimé', 'volume' => 'Volume'];

    public const PERIODS = ['3m' => '3 mois', '1y' => '1 an', 'all' => 'Depuis le début'];

    public Exercise $exercise;

    // Valeurs d'URL non typées : ramenées à des valeurs connues par sanitize().
    #[Url(as: 'courbe')]
    public $metric = 'max_kg';

    #[Url(as: 'periode')]
    public $period = 'all';

    public function mount(): void
    {
        $this->sanitize();
    }

    public function updated(): void
    {
        $this->sanitize();
    }

    private function sanitize(): void
    {
        $this->metric = is_string($this->metric) && isset(self::METRICS[$this->metric]) ? $this->metric : 'max_kg';
        $this->period = is_string($this->period) && isset(self::PERIODS[$this->period]) ? $this->period : 'all';
    }

    public function render(ProgressStats $stats)
    {
        $user = auth()->user();
        $rows = $stats->exerciseSeries($user, $this->exercise, $this->period);
        $values = $rows->pluck($this->metric)->all();

        return view('livewire.progress', [
            'rows' => $rows,
            'summary' => $stats->summary($user, $this->exercise),
            'points' => $values === [] ? [] : explode(' ', Sparkline::points($values, 320, 140)),
            'minValue' => $values === [] ? null : min($values),
            'maxValue' => $values === [] ? null : max($values),
        ]);
    }
}
