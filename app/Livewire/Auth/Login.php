<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\IdentityKey;
use Illuminate\Support\Facades\Auth;
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

        if ($this->throttled()) {
            return;
        }

        $user = User::where('identity_key', IdentityKey::make($this->firstName, $this->birthDate))->first();

        if (! $user) {
            $this->addError('firstName', 'Aucun compte ne correspond à ce prénom et cette date de naissance.');

            return;
        }

        Auth::login($user, remember: true);
        session()->regenerate();

        return $this->redirectRoute('home');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
