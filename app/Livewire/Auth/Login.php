<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\IdentityKey;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Connexion')]
class Login extends Component
{
    use ValidatesIdentity;

    public function login()
    {
        $this->validateIdentity();

        $throttleKey = 'login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('firstName', "Trop de tentatives. Réessaie dans {$seconds} secondes.");

            return;
        }

        $user = User::where('identity_key', IdentityKey::make($this->firstName, $this->birthDate))->first();

        if (! $user) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('firstName', 'Aucun compte ne correspond à ce prénom et cette date de naissance.');

            return;
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, remember: true);
        session()->regenerate();

        return $this->redirectRoute('home');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
