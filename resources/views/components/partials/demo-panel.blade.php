{{-- Contenu de la fenêtre « Comment faire ? » : textes du serveur, animation par le composant Alpine exerciseDemo. --}}
<header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span>{{ mb_strtoupper($name) }}</header>
<p class="text-sm text-mist">{{ $guide['muscles'] }}</p>

<div class="relative mt-3 aspect-[240/215] border border-ice/15 [background:repeating-linear-gradient(0deg,rgba(90,216,255,.05)_0_1px,transparent_1px_22px),repeating-linear-gradient(90deg,rgba(90,216,255,.05)_0_1px,transparent_1px_22px)]">
    <span class="sys-num absolute left-2 top-1.5 text-[11px] uppercase tracking-widest text-ice" x-text="phase"></span>
    <span class="sys-num absolute right-2 top-1.5 text-[11px] text-mist">{{ $guide['tempo'] }}</span>
    <svg x-ref="svg" :viewBox="viewBox" class="h-full w-full" role="img" aria-label="Démonstration animée : {{ $name }}"></svg>
    <span class="sys-num absolute bottom-1 left-2 text-[10px] text-mist" x-text="note"></span>
</div>

<div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Vitesse de l'animation">
    <button type="button" class="sys-chip" :class="{ 'sys-chip--active': !paused && speed === 1 }" @click="setSpeed(1)">Vitesse réelle</button>
    <button type="button" class="sys-chip" :class="{ 'sys-chip--active': !paused && speed !== 1 }" @click="setSpeed(0.4)">Ralenti</button>
    <button type="button" class="sys-chip" :class="{ 'sys-chip--active': paused }" @click="paused = !paused">Pause</button>
</div>

<ol class="mt-4 list-decimal space-y-2 pl-5 text-sm leading-relaxed marker:font-bold marker:text-ice">
    @foreach ($guide['steps'] as $step)
        <li>{{ $step }}</li>
    @endforeach
</ol>

<p class="mt-4 border-l-2 border-alert bg-alert/10 px-3 py-2 text-sm">
    <span class="sys-num block text-[11px] font-bold tracking-widest text-alert">ERREUR À ÉVITER</span>{{ $guide['mistake'] }}
</p>
