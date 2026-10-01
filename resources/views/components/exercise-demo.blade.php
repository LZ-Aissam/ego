@props(['slug', 'name'])
@php $guide = \App\Support\ExerciseGuide::for($slug); @endphp
@if ($guide)
    <span x-data="{ open: false }" {{ $attributes->class('inline-block') }}>
        <button type="button" class="sys-label underline decoration-ice/40 underline-offset-4 hover:text-ice" @click="open = true">Comment faire ?</button>
        <template x-teleport="body">
            <div x-show="open" x-transition.opacity x-cloak class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-void/85 p-4"
                 role="dialog" aria-modal="true" aria-label="Comment faire : {{ $name }}" @keydown.escape.window="open = false" @click.self="open = false">
                <template x-if="open">
                    <section class="sys-window w-full max-w-md" x-data="exerciseDemo('{{ $slug }}')">
                        @include('components.partials.demo-panel', ['name' => $name, 'guide' => $guide])
                        <x-sys.button variant="ghost" class="mt-4" @click="open = false">Fermer</x-sys.button>
                    </section>
                </template>
            </div>
        </template>
    </span>
@endif
