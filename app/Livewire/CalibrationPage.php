<?php

namespace App\Livewire;

use App\Game\Calibration;
use App\Game\CalibrationPlan;
use App\Models\Quest;
use App\Support\SystemNotifier;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Calibrage')]
class CalibrationPage extends Component
{
    // L'état du parcours n'est modifiable que par le serveur : seuls testKg et testReps viennent du navigateur.
    #[Locked]
    public array $movements = [];

    #[Locked]
    public int $index = 0;

    #[Locked]
    public array $warmupsDone = [];

    public $testKg = null;

    public $testReps = null;

    #[Locked]
    public array $results = [];

    #[Locked]
    public string $startedAt = '';

    /** Identifiant de la séance de calibrage : un second envoi est reconnu et ignoré. */
    #[Locked]
    public string $clientId = '';

    #[Locked]
    public bool $finished = false;

    /** Échauffements réellement faits, modifiables par le joueur (pré-remplis avec la proposition). */
    public array $warmupKg = [];

    public array $warmupReps = [];

    private function progressKey(): string
    {
        return 'calibration_progress.'.auth()->id();
    }

    public function mount(?Quest $quest = null)
    {
        $user = auth()->user();

        if ($quest && $quest->exists) {
            abort_unless($quest->user_id === $user->id, 403);
            if ($quest->type !== 'calibration' || $quest->status !== 'pending' || ! $quest->date->isToday()) {
                return $this->redirectRoute('home');
            }
        }

        if (! $user->active_program_id) {
            return $this->redirectRoute('program');
        }

        $this->movements = app(CalibrationPlan::class)->plan($user);
        $this->startedAt = now()->toIso8601String();
        $this->clientId = (string) Str::uuid();

        // Page rechargée en plein calibrage : on reprend les mouvements déjà mesurés aujourd'hui.
        $saved = session($this->progressKey());
        if (is_array($saved) && ($saved['date'] ?? null) === today()->toDateString()
            && ($saved['ids'] ?? null) === array_column($this->movements, 'exercise_id')) {
            [$this->results, $this->index, $this->clientId, $this->startedAt] = [$saved['results'], $saved['index'], $saved['clientId'], $saved['startedAt']];
        }

        $this->prepare();
    }

    private function prepare(): void
    {
        $this->warmupsDone = [false, false];
        $current = $this->movements[$this->index] ?? null;
        $this->testKg = $current && ! $current['isBodyweight'] ? $current['estimate'] : 0;
        $this->testReps = null;
        $this->warmupKg = array_map(fn ($w) => $w[0], $current['warmups'] ?? []);
        $this->warmupReps = array_map(fn ($w) => $w[1], $current['warmups'] ?? []);
    }

    public function toggleWarmup(int $i): void
    {
        $this->warmupsDone[$i] = ! ($this->warmupsDone[$i] ?? false);
    }

    public function saveTest()
    {
        if ($this->finished) {
            return $this->redirectRoute('home');
        }

        $current = $this->movements[$this->index];

        if (in_array(false, $this->warmupsDone, true)) {
            throw ValidationException::withMessages(['warmups' => 'Valide d\'abord tes deux séries d\'échauffement.']);
        }

        $kg = is_string($this->testKg) ? str_replace(',', '.', $this->testKg) : $this->testKg;
        $minKg = $current['isBodyweight'] ? 0 : 0.5;
        $message = 'Indique la charge et le nombre de répétitions réalisées.';
        validator(['testKg' => $kg, 'testReps' => $this->testReps], [
            'testKg' => ['required', 'numeric', 'min:'.$minKg, 'max:500'],
            'testReps' => ['required', 'integer', 'between:1,30'],
        ], ['*' => $message, 'testKg.*' => $message, 'testReps.*' => $message])->validate();

        $this->results[] = ['exercise_id' => $current['exercise_id'], 'warmups' => $this->validatedWarmups($current), 'test' => [(float) $kg, (int) $this->testReps]];

        if ($this->index + 1 < count($this->movements)) {
            $this->index++;
            $this->prepare();
            session()->put($this->progressKey(), [
                'date' => today()->toDateString(), 'ids' => array_column($this->movements, 'exercise_id'),
                'results' => $this->results, 'index' => $this->index, 'clientId' => $this->clientId, 'startedAt' => $this->startedAt,
            ]);

            return null;
        }

        session()->forget($this->progressKey());

        $user = auth()->user();
        $this->finished = true;
        $out = app(Calibration::class)->complete($user, $this->results, $this->startedAt, $this->clientId);
        foreach ($out['notifications'] as $notification) {
            app(SystemNotifier::class)->push($user, $notification);
        }

        return $this->redirectRoute('home');
    }

    /** @return list<array{0: float, 1: int}> */
    private function validatedWarmups(array $current): array
    {
        $warmups = [];
        foreach ([0, 1] as $i) {
            $kg = $current['isBodyweight'] ? 0.0 : str_replace(',', '.', (string) ($this->warmupKg[$i] ?? ''));
            $reps = $this->warmupReps[$i] ?? null;
            $valid = is_numeric($kg) && $kg >= 0 && $kg <= 500 && filter_var($reps, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]) !== false;
            if (! $valid) {
                throw ValidationException::withMessages(['warmups' => 'Indique la charge et les répétitions de tes échauffements.']);
            }
            $warmups[] = [(float) $kg, (int) $reps];
        }

        return $warmups;
    }

    public function render()
    {
        return view('livewire.calibration-page', ['current' => $this->movements[$this->index] ?? null]);
    }
}
