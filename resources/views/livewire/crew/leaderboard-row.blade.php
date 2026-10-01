<a href="{{ route('crew.member', $row['user']) }}" @class(['sys-row', 'bg-ice/10 px-2' => $row['user']->id === $me->id])>
    <span class="flex items-center gap-3">
        <span @class(['sys-num w-10 text-right font-extrabold', 'text-ice [text-shadow:0_0_12px_var(--glow-ice)]' => $row['rank'] <= 3, 'text-mist' => $row['rank'] > 3])>#{{ $row['rank'] }}</span>
        <span>
            <span class="font-semibold">{{ $row['user']->first_name }}</span>
            @if ($row['user']->isPaused()) <span class="sys-label">en pause</span> @endif
            @if ($row['user']->equippedTitleName()) <span class="block text-xs text-ice">« {{ $row['user']->equippedTitleName() }} »</span> @endif
        </span>
    </span>
    <span class="sys-num text-right">
        <span class="sys-label">Rang {{ $row['user']->player()['rank'] }}</span>
        <span class="block font-bold text-ice">{{ \App\Crew\Leaderboard::format($metric, $row['value']) }}</span>
    </span>
</a>
