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
                <span class="sys-num font-bold text-ice">{{ $max->label() }}</span>
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
            @if ($member->id !== auth()->id())
                @if ($copying)
                    @php
                        $needed = $program->params['equipment'] ?? null;
                        $mine = auth()->user()->equipment;
                        $ranks = \App\Services\ProgramBuilder::EQUIPMENT_RANK;
                        $labels = \App\Services\ProgramBuilder::EQUIPMENT;
                        $dayNames = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
                    @endphp
                    <div class="mt-4 border border-ice/30 p-3">
                        <p class="text-sm">Choisis tes {{ $program->days->count() }} jours d'entraînement :</p>
                        <div class="mt-2 grid grid-cols-7 gap-1">
                            @foreach ($dayNames as $value => $label)
                                <label @class(['sys-chip cursor-pointer px-0 py-2 text-center text-xs', 'sys-chip--active' => in_array($value, array_map('intval', $weekdays), true)])>
                                    <input type="checkbox" wire:model.live="weekdays" value="{{ $value }}" class="sr-only">{{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('weekdays') <p class="sys-error">{{ $message }}</p> @enderror
                        @if ($needed && $mine && ($ranks[$needed] ?? 0) > ($ranks[$mine] ?? 0))
                            <p class="mt-2 text-sm text-amber">Ce programme demande : {{ $labels[$needed] }}. Tu as indiqué : {{ $labels[$mine] }}.</p>
                        @endif
                        <p class="mt-2 text-xs text-mist">Tes charges seront calculées d'après ton propre profil de force.</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <x-sys.button variant="ghost" wire:click="$set('copying', false)">Annuler</x-sys.button>
                            <x-sys.button wire:click="copyProgram">Copier et activer</x-sys.button>
                        </div>
                    </div>
                @else
                    <x-sys.button variant="ghost" class="mt-4" wire:click="startCopy">Copier ce programme</x-sys.button>
                @endif
            @endif
        @else
            <p class="text-sm text-mist">Pas de programme actif.</p>
        @endif
    </x-sys.window>
</div>
