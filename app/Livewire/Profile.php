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

    public function render()
    {
        return view('livewire.profile', ['user' => auth()->user()->fresh()]);
    }
}
