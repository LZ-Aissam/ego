<div class="space-y-6">
    <div class="flex items-center gap-4">
        <span class="flex h-14 w-14 items-center justify-center rounded-full text-2xl font-black text-zinc-950" style="background: {{ $user->avatar_color }}">
            {{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}
        </span>
        <div>
            <h1 class="text-2xl font-bold">{{ $user->first_name }}</h1>
            <p class="text-sm text-zinc-400">{{ $user->birth_date->age }} ans</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <a href="{{ route('history') }}" class="card text-center font-semibold active:bg-zinc-800">Historique</a>
        <a href="{{ route('records') }}" class="card text-center font-semibold active:bg-zinc-800">Records</a>
    </div>

    <form wire:submit="save" class="card space-y-4" novalidate>
        <h2 class="text-lg font-bold">Morphologie</h2>
        <div>
            <span class="mb-1 block text-sm text-zinc-400">Sexe</span>
            <div class="grid grid-cols-2 gap-2">
                @foreach (['male' => 'Homme', 'female' => 'Femme'] as $value => $label)
                    <label class="cursor-pointer rounded-xl border px-4 py-3 text-center {{ $sex === $value ? 'border-lime-400 text-lime-300' : 'border-zinc-800 text-zinc-300' }}">
                        <input type="radio" wire:model.live="sex" value="{{ $value }}" class="sr-only">{{ $label }}
                    </label>
                @endforeach
            </div>
            @error('sex') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="heightCm" class="mb-1 block text-sm text-zinc-400">Taille (cm)</label>
                <input id="heightCm" type="text" inputmode="numeric" wire:model="heightCm" class="input">
                @error('heightCm') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="weightKg" class="mb-1 block text-sm text-zinc-400">Poids (kg)</label>
                <input id="weightKg" type="text" inputmode="decimal" wire:model="weightKg" class="input">
                @error('weightKg') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>
        @if ($user->bmi())
            <p class="text-sm text-zinc-400">IMC indicatif : {{ number_format($user->bmi(), 1, ',', ' ') }}</p>
        @endif
        <button type="submit" class="btn-primary">Enregistrer</button>
        @if ($saved) <p class="text-center text-sm text-lime-400">Profil enregistré ✓</p> @endif
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn-secondary">Se déconnecter</button>
    </form>
</div>
