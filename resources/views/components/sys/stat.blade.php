@props(['label', 'value' => null])
<div>
    <div class="sys-num flex justify-between text-sm">
        <span class="text-mist">{{ $label }}</span>
        <span @class(['font-bold', 'text-ice' => $value !== null, 'text-steel' => $value === null])>{{ $value ?? '—' }}</span>
    </div>
    <div class="sys-track mt-1 !h-1.5"><div class="sys-xp-fill" style="width: {{ min(100, $value ?? 0) }}%"></div></div>
</div>
