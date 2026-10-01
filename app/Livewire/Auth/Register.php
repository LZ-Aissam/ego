<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\IdentityKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Inscription')]
class Register extends Component
{
    use ValidatesIdentity;

    public function register()
    {
        $this->validateIdentity();

        if ($this->throttled()) {
            return;
        }

        $key = IdentityKey::make($this->firstName, $this->birthDate);

        if (User::where('identity_key', $key)->exists()) {
            return $this->duplicate();
        }

        try {
            $user = User::create([
                'first_name' => IdentityKey::displayFirstName($this->firstName),
                'identity_key' => $key,
                'birth_date' => $this->birthDate,
                'avatar_color' => collect(User::AVATAR_COLORS)->random(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->duplicate();
        }

        Auth::login($user, remember: true);
        session()->regenerate();

        return $this->redirectRoute('home');
    }

    private function duplicate(): void
    {
        $this->addError('firstName', 'Ce prénom et cette date de naissance sont déjà utilisés — connecte-toi.');
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
