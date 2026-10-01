<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\RateLimiter;

trait ValidatesIdentity
{
    public string $firstName = '';

    public string $birthDate = '';

    /**
     * Compteur commun à l'inscription et à la connexion, par IP : sans lui, l'un ou l'autre formulaire
     * permettrait d'essayer des dates de naissance à la chaîne. Il n'est jamais remis à zéro, il expire seul.
     */
    protected function throttled(): bool
    {
        $key = 'identity:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('firstName', "Trop de tentatives. Réessaie dans {$seconds} secondes.");

            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

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
