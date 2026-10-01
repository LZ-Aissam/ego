<div>
    <x-sys.window title="IDENTIFICATION">
        <h1 class="mb-1 text-xl font-bold">Connexion</h1>
        <p class="mb-5 text-sm text-mist">Déclare-toi au Système.</p>
        <form wire:submit="login" class="space-y-4" novalidate>
            <x-sys.field name="firstName" label="Prénom" wire:model="firstName" autocomplete="given-name" autocapitalize="words" />
            <x-sys.field name="birthDate" label="Date de naissance" type="date" wire:model="birthDate" autocomplete="bday" />
            <x-sys.button type="submit" :pulse="true" wire:loading.attr="disabled">Se connecter</x-sys.button>
        </form>
    </x-sys.window>
    <p class="mt-6 text-center text-sm text-mist">
        Pas encore de compte ? <a href="{{ route('register') }}" class="font-semibold text-ice">Créer un compte</a>
    </p>
</div>
