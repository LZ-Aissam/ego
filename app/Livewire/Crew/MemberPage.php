<?php

namespace App\Livewire\Crew;

use App\Game\PlayerStats;
use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profil public')]
class MemberPage extends Component
{
    #[Locked]
    public int $memberId;

    public bool $copying = false;

    public array $weekdays = [];

    public function mount(User $member): void
    {
        $this->memberId = $member->id;
    }

    /** Programme actif d'un autre joueur, seul programme que l'on peut copier. */
    private function copiableProgram(): \App\Models\Program
    {
        $member = User::findOrFail($this->memberId);
        abort_if($member->id === auth()->id() || ! $member->active_program_id, 403);

        return $member->activeProgram()->with('days')->firstOrFail();
    }

    public function startCopy(): void
    {
        $this->copiableProgram();
        $this->weekdays = auth()->user()->training_weekdays ?? [];
        $this->copying = true;
    }

    public function copyProgram(\App\Services\ProgramBuilder $builder)
    {
        $source = $this->copiableProgram();
        $count = $source->days->count();
        $days = collect($this->weekdays)->filter(fn ($d) => is_numeric($d) && (int) $d >= 1 && (int) $d <= 7)->map(fn ($d) => (int) $d)->unique();
        if ($days->count() !== $count) {
            throw \Illuminate\Validation\ValidationException::withMessages(['weekdays' => "Choisis {$count} jours."]);
        }

        $user = auth()->user();
        $program = $builder->copy($user, $source, $days->all());
        app(\App\Support\SystemNotifier::class)->push($user, \App\Support\SystemMessage::programForged($program->name));
        session()->put('program_forged', true);

        return $this->redirectRoute('program');
    }

    public function render(PlayerStats $stats)
    {
        $member = User::findOrFail($this->memberId);
        $myCrewIds = auth()->user()->crews()->pluck('crews.id');

        return view('livewire.crew.member-page', [
            'member' => $member,
            'player' => $member->player(),
            'stats' => $stats->for($member),
            'records' => $member->personalRecords()->with('exercise')->get()->sortBy(fn ($r) => $r->exercise->name)->values(),
            'maxes' => $member->exerciseMaxes()->with('exercise')->orderByDesc('e1rm_kg')->get(),
            'program' => $member->activeProgram()->with('days.exercises.exercise')->first(),
            'commonCrews' => $member->crews()->whereIn('crews.id', $myCrewIds)->orderBy('name')->get(),
        ]);
    }
}
