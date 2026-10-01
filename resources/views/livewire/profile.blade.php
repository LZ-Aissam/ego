<div class="space-y-5">
    <x-sys.window title="FICHE DU JOUEUR">
        <div class="flex items-center gap-4">
            <span class="grid h-16 w-16 place-items-center text-3xl font-black text-void [clip-path:var(--sys-cut)]" style="background: {{ $user->avatar_color }}; box-shadow: 0 0 24px {{ $user->avatar_color }}">
                {{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}
            </span>
            <div>
                <h1 class="text-2xl font-bold">{{ $user->first_name }}</h1>
                <p class="sys-num text-sm text-steel">{{ $user->birth_date->age }} ans</p>
            </div>
        </div>
    </x-sys.window>

    <div class="grid grid-cols-2 gap-3">
        <x-sys.button :href="route('history')" variant="ghost">Historique</x-sys.button>
        <x-sys.button :href="route('records')" variant="ghost">Records</x-sys.button>
    </div>

    <x-sys.window title="MORPHOLOGIE" :index="1">
        <form wire:submit="save" class="space-y-4" novalidate>
            <div>
                <p class="sys-label mb-1">Sexe</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['male' => 'Homme', 'female' => 'Femme'] as $value => $label)
                        <label @class(['sys-chip cursor-pointer py-3 text-center text-sm', 'sys-chip--active' => $sex === $value])>
                            <input type="radio" wire:model.live="sex" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('sex') <p class="sys-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-sys.field name="heightCm" label="Taille (cm)" inputmode="numeric" wire:model="heightCm" />
                <x-sys.field name="weightKg" label="Poids (kg)" inputmode="decimal" wire:model="weightKg" />
            </div>
            @if ($user->bmi())
                <p class="sys-num text-sm text-mist">IMC indicatif : {{ number_format($user->bmi(), 1, ',', ' ') }}</p>
            @endif
            <x-sys.button type="submit">Enregistrer</x-sys.button>
            @if ($saved) <p class="text-center text-sm text-ice">Profil enregistré ✓</p> @endif
        </form>
    </x-sys.window>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-sys.button type="submit" variant="ghost">Se déconnecter</x-sys.button>
    </form>
</div>
