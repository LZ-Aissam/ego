@props(['href' => null, 'variant' => 'primary', 'pulse' => false, 'type' => 'button'])
@php
    $classes = ['sys-btn', 'sys-btn--ghost' => $variant === 'ghost', 'sys-btn--pulse' => $pulse];
@endphp
@if ($href)
<a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
