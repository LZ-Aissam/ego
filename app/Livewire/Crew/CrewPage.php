<?php

namespace App\Livewire\Crew;

use App\Crew\CrewService;
use App\Crew\Leaderboard;
use App\Models\Crew;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Crew')]
class CrewPage extends Component
{
    public const TABS = ['classement', 'membres', 'groupes'];

    public const TOP = 50;

    #[Url]
    public string $tab = 'classement';

    #[Url]
    public string $metric = 'xp';

    #[Url]
    public string $period = 'week';

    #[Url]
    public string $lift = 'bench';

    #[Url(as: 'groupe')]
    public ?int $group = null;

    public string $search = '';

    public string $newName = '';

    public string $newColor = 'ice';

    public string $joinCode = '';

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
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'classement';
        $this->metric = in_array($this->metric, Leaderboard::METRICS, true) ? $this->metric : 'xp';
        $this->period = in_array($this->period, Leaderboard::PERIODS, true) ? $this->period : 'week';
        $this->lift = array_key_exists($this->lift, Leaderboard::LIFTS) ? $this->lift : 'bench';
        if ($this->group !== null && ! auth()->user()->crews()->whereKey($this->group)->exists()) {
            $this->group = null;
        }
    }

    public function createCrew(CrewService $crews)
    {
        $crew = $crews->create(auth()->user(), $this->newName, $this->newColor);

        return $this->redirectRoute('crew.group', $crew);
    }

    public function joinCrew(CrewService $crews)
    {
        $crew = $crews->join(auth()->user(), $this->joinCode);

        return $this->redirectRoute('crew.group', $crew);
    }

    public function render(Leaderboard $board)
    {
        $me = auth()->user();
        $myCrews = $me->crews()->withCount('members')->orderBy('name')->get();
        $data = ['me' => $me, 'myCrews' => $myCrews];

        if ($this->tab === 'classement') {
            $rows = $board->rank($this->metric, $this->period, $this->lift, $myCrews->firstWhere('id', $this->group));
            $data['rows'] = $rows->take(self::TOP);
            $mine = $rows->first(fn ($r) => $r['user']->id === $me->id);
            $data['pinned'] = $mine && $mine['rank'] > 0 && ! $data['rows']->contains(fn ($r) => $r['user']->id === $me->id) ? $mine : null;
        }

        if ($this->tab === 'membres') {
            $needle = mb_strtolower(trim($this->search));
            $data['members'] = User::orderBy('first_name')->get()
                ->filter(fn (User $u) => $needle === '' || str_contains(mb_strtolower($u->first_name), $needle))->values();
        }

        return view('livewire.crew.crew-page', $data);
    }
}
