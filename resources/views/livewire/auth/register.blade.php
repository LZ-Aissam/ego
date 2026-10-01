<div>
    <x-sys.window title="ÉVEIL">
        <h1 class="mb-1 text-xl font-bold">Créer mon compte</h1>
        <p class="mb-5 text-sm text-mist">Le Système a besoin de ton identité pour t'assigner tes quêtes.</p>
        <form wire:submit="register" class="space-y-4" novalidate>
            <x-sys.field name="firstName" label="Prénom" wire:model="firstName" autocomplete="given-name" autocapitalize="words" />
            <x-sys.field name="birthDate" label="Date de naissance" type="date" wire:model="birthDate" autocomplete="bday" />
            <x-sys.button type="submit" :pulse="true" wire:loading.attr="disabled">C'est parti</x-sys.button>
        </form>
    </x-sys.window>
    <p class="mt-6 text-center text-sm text-mist">
        Déjà inscrit ? <a href="{{ route('login') }}" class="font-semibold text-ice">Se connecter</a>
    </p>
</div>
