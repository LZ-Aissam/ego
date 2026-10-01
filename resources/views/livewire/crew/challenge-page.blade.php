<div class="space-y-5">
    <a href="{{ route('community', ['tab' => 'defis']) }}" class="sys-label">← Défis</a>
    <x-sys.window :title="mb_strtoupper($challenge->title())">
        <p class="text-sm text-mist">
            {{ $challenge->crew->name }} · {{ \App\Models\Challenge::DURATIONS[$challenge->duration] }}
            · du {{ $challenge->starts_at->translatedFormat('l j') }} au {{ $challenge->ends_at->copy()->subSecond()->translatedFormat('l j F') }}
        </p>
        <p class="sys-num mt-1 text-sm text-ice">
            {{ ['upcoming' => 'Commence demain', 'active' => \App\Crew\ChallengeBoard::remaining($challenge->ends_at), 'finished' => 'Terminé', 'cancelled' => 'Annulé'][$challenge->status] }}
        </p>
        @if ($challenge->stake)
            <p class="mt-3 border-l-2 border-amber px-3 py-1 text-sm"><span class="sys-label block">Enjeu</span>{{ $challenge->stake }}</p>
        @endif
        @if ($challenge->metric === 'strength')
            <p class="mt-2 text-xs text-mist">Fais ce mouvement avant le début pour être classé : ta progression se mesure depuis ton meilleur 1RM d'avant le défi.</p>
        @endif
        @error('challenge') <p class="sys-error">{{ $message }}</p> @enderror

        <div class="mt-4 grid grid-cols-2 gap-2">
            @if ($challenge->isOpen() && ! $participating)
                <x-sys.button wire:click="join">Rejoindre</x-sys.button>
            @endif
            @if ($challenge->status === 'upcoming' && $participating && ! $isCreator)
                <x-sys.button variant="ghost" wire:click="leave">Quitter</x-sys.button>
            @endif
            @if ($challenge->status === 'upcoming' && $isCreator)
                <x-sys.button variant="ghost" wire:click="cancel">Annuler le défi</x-sys.button>
            @endif
        </div>
    </x-sys.window>

    <x-sys.window :title="$challenge->status === 'finished' ? 'CLASSEMENT FINAL' : 'CLASSEMENT'" :index="1">
        @forelse ($standings as $row)
            <div @class(['sys-row', 'bg-ice/10 px-2' => $row['user_id'] === auth()->id()])>
                <span class="flex items-center gap-3">
                    <span @class(['sys-num w-10 text-right font-extrabold', 'text-ice' => $row['rank'] <= 3, 'text-mist' => $row['rank'] > 3])>#{{ $row['rank'] }}</span>
                    <span class="font-semibold">{{ $row['first_name'] }}</span>
                </span>
                <span class="sys-num font-bold text-ice">{{ \App\Crew\ChallengeBoard::format($challenge->metric, $row['value']) }}</span>
            </div>
        @empty
            <p class="text-sm text-mist">Personne n'est encore classé.</p>
        @endforelse
    </x-sys.window>
</div>
