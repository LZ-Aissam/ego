<?php

namespace App\Livewire\Auth;

trait ValidatesIdentity
{
    public string $firstName = '';

    public string $birthDate = '';

    protected function validateIdentity(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'max:50'],
            'birthDate' => ['required', 'date', 'before:today', 'after:1900-01-01'],
        ], [
            'firstName.required' => 'Indique ton prénom.',
            'firstName.max' => 'Ton prénom est trop long (50 caractères max.).',
            'birthDate.required' => 'Indique ta date de naissance.',
            'birthDate.date' => 'Date de naissance invalide.',
            'birthDate.before' => 'La date de naissance doit être dans le passé.',
            'birthDate.after' => 'Date de naissance invalide.',
        ]);
    }
}
