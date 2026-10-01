<div class="space-y-5">
    <a href="{{ route('community', ['tab' => 'membres']) }}" class="sys-label">← Membres</a>
    <x-sys.window title="CARTE DE JOUEUR">
        <div class="flex items-center gap-4">
            <span class="grid h-16 w-16 place-items-center text-3xl font-black text-void [clip-path:var(--sys-cut)]" style="background: {{ $member->avatar_color }}; box-shadow: 0 0 24px {{ $member->avatar_color }}">
                {{ mb_strtoupper(mb_substr($member->first_name, 0, 1)) }}
            </span>
            <div>
                <h1 class="text-2xl font-bold">{{ $member->first_name }}</h1>
                <p class="sys-num text-sm text-mist">Niveau {{ $player['level'] }} · Rang {{ $player['rank'] }} · Série {{ $member->streak_days }} j</p>
                @if ($member->equippedTitleName()) <p class="text-sm text-ice">« {{ $member->equippedTitleName() }} »</p> @endif
            </div>
        </div>
        <x-sys.xp-bar :progress="$player" class="mt-4" />
        <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-3">
            <x-sys.stat label="FORCE" :value="$stats['force']" />
            <x-sys.stat label="ENDURANCE" :value="$stats['endurance']" />
            <x-sys.stat label="PROGRESSION" :value="$stats['progression']" />
            <x-sys.stat label="RÉGULARITÉ" :value="$stats['regularite']" />
        </div>
        @if ($commonCrews->isNotEmpty())
            <p class="mt-4 text-sm text-mist">Groupes en commun :
                @foreach ($commonCrews as $crew)
                    <a href="{{ route('crew.group', $crew) }}" class="font-semibold" style="color: {{ $crew->hex() }}">{{ $crew->name }}</a>@if (! $loop->last), @endif
                @endforeach
            </p>
        @endif
    </x-sys.window>

    <x-sys.window title="RECORDS" :index="1">
        @forelse ($records as $record)
            <div class="sys-row text-sm">
                <span>{{ $record->exercise->name }}</span>
                <span class="sys-num text-right"><span class="font-bold text-ice">{{ \App\Support\Format::kg($record->max_weight_kg) }} × {{ $record->max_weight_reps }}</span>
                    <span class="block text-xs text-steel">1RM estimé : {{ \App\Support\Format::kg($record->best_e1rm_kg) }}</span></span>
            </div>
        @empty
            <p class="text-sm text-mist">Aucun record pour l'instant.</p>
        @endforelse
    </x-sys.window>

    <x-sys.window title="PROFIL DE FORCE" :index="2">
        @forelse ($maxes as $max)
            <div class="sys-row text-sm">
                <span>{{ $max->exercise->name }}</span>
                <span class="sys-num font-bold text-ice">{{ \App\Support\Format::kg($max->e1rm_kg) }}</span>
            </div>
        @empty
            <p class="text-sm text-mist">Pas encore calibré.</p>
        @endforelse
    </x-sys.window>

    <x-sys.window title="PROGRAMME ACTIF" :index="3">
        @if ($program)
            @foreach ($program->days as $day)
                <x-sys.label class="mt-2">{{ $day->name }}</x-sys.label>
                @foreach ($day->exercises as $line)
                    <div class="sys-row text-sm">
                        <span>{{ $line->exercise->name }}</span>
                        <span class="sys-num text-mist">{{ $line->sets }} × {{ $line->reps_min }}–{{ $line->reps_max }}</span>
                    </div>
                @endforeach
            @endforeach
        @else
            <p class="text-sm text-mist">Pas de programme actif.</p>
        @endif
    </x-sys.window>
</div>
