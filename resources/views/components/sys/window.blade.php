@props(['title' => null, 'variant' => 'default', 'index' => 0])
<section {{ $attributes->class(['sys-window', 'sys-window--alert' => $variant === 'alert']) }} style="--i: {{ $index }}">
    @if ($title)
        <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span>{{ $title }}</header>
    @endif
    {{ $slot }}
</section>
