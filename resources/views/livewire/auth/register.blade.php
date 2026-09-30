<div>
    <h1 class="mb-6 text-xl font-bold">Créer mon compte</h1>
    <form wire:submit="register" class="space-y-4" novalidate>
        <div>
            <label for="firstName" class="mb-1 block text-sm text-zinc-400">Prénom</label>
            <input id="firstName" type="text" wire:model="firstName" autocomplete="given-name" autocapitalize="words" class="input">
            @error('firstName') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="birthDate" class="mb-1 block text-sm text-zinc-400">Date de naissance</label>
            <input id="birthDate" type="date" wire:model="birthDate" autocomplete="bday" class="input">
            @error('birthDate') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">C'est parti</button>
    </form>
    <p class="mt-6 text-center text-sm text-zinc-400">
        Déjà inscrit ? <a href="{{ route('login') }}" class="font-semibold text-lime-400">Se connecter</a>
    </p>
</div>
