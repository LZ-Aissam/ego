<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profil')]
class Profile extends Component
{
    public ?string $sex = null;

    public $heightCm = null;

    public $weightKg = null;

    public bool $saved = false;

    public ?string $pauseUntil = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->sex = $user->sex;
        $this->heightCm = $user->height_cm;
        $this->weightKg = $user->weight_kg;
    }

    public function save(): void
    {
        $this->sex = $this->sex ?: null;
        $this->heightCm = $this->heightCm === '' ? null : $this->heightCm;
        $this->weightKg = in_array($this->weightKg, ['', null], true) ? null : str_replace(',', '.', (string) $this->weightKg);

        $data = $this->validate([
            'sex' => ['nullable', 'in:male,female'],
            'heightCm' => ['nullable', 'integer', 'between:100,250'],
            'weightKg' => ['nullable', 'numeric', 'between:30,300'],
        ], [
            'sex.in' => 'Choisis homme ou femme.',
            'heightCm.integer' => 'La taille doit être un nombre entier (en cm).',
            'heightCm.between' => 'La taille doit être comprise entre 100 et 250 cm.',
            'weightKg.numeric' => 'Le poids doit être un nombre.',
            'weightKg.between' => 'Le poids doit être compris entre 30 et 300 kg.',
        ]);

        auth()->user()->update([
            'sex' => $data['sex'],
            'height_cm' => $data['heightCm'],
            'weight_kg' => $data['weightKg'],
        ]);

        $this->saved = true;
    }

    public function pause()
    {
        $notifier = app(\App\Support\SystemNotifier::class);
        $this->validate(
            ['pauseUntil' => ['nullable', 'date', 'after_or_equal:today']],
            ['pauseUntil.date' => 'Date invalide.', 'pauseUntil.after_or_equal' => 'La date de fin doit être aujourd\'hui ou plus tard.'],
        );

        $user = auth()->user();
        $until = $this->pauseUntil ? \Illuminate\Support\Carbon::parse($this->pauseUntil) : null;
        $user->forceFill(['paused_at' => today(), 'paused_until' => $until])->save();
        $user->quests()->where('status', 'pending')->where('date', '>=', today()->toDateString())->update(['status' => 'cancelled']);
        $notifier->push($user, \App\Support\SystemMessage::pauseOn($until));

        return $this->redirectRoute('profile');
    }

    public function resume()
    {
        $notifier = app(\App\Support\SystemNotifier::class);
        $user = auth()->user();
        app(\App\Game\QuestScheduler::class)->resume($user, today());
        $notifier->push($user, \App\Support\SystemMessage::pauseOff());

        return $this->redirectRoute('profile');
    }

    public function equipTitle(string $key): void
    {
        $user = auth()->user();

        if (! $user->titles()->where('title_key', $key)->exists()) {
            return;
        }

        $user->forceFill(['equipped_title' => $key])->save();
    }

    public function render(\App\Game\PlayerStats $stats)
    {
        $user = auth()->user()->fresh();

        return view('livewire.profile', [
            'user' => $user,
            'player' => \App\Game\Leveling::progress((int) $user->xp_total),
            'stats' => $stats->for($user),
            'unlocked' => $user->titles()->pluck('title_key')->all(),
            'maxes' => $user->exerciseMaxes()->with('exercise')->orderByDesc('e1rm_kg')->get(),
        ]);
    }
}
