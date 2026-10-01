@props(['progress'])
@php $pct = $progress['needed'] > 0 ? min(100, (int) round($progress['into'] / $progress['needed'] * 100)) : 100; @endphp
<div {{ $attributes }}>
    <div class="sys-track" role="progressbar" aria-label="Expérience" aria-valuemin="0" aria-valuemax="{{ $progress['needed'] }}" aria-valuenow="{{ $progress['into'] }}">
        <div class="sys-xp-fill" style="width: {{ $pct }}%"></div>
    </div>
    <div class="sys-num mt-1 flex justify-between text-xs text-mist">
        <span>XP {{ number_format($progress['into'], 0, ',', ' ') }} / {{ number_format($progress['needed'], 0, ',', ' ') }}</span>
        <span>→ NIV. {{ $progress['level'] + 1 }}</span>
    </div>
</div>
