@props(['title' => null, 'bare' => false])
@php
    $tabs = [
        ['home', 'Accueil', 'home', ['home']],
        ['program', 'Programme', 'program', ['program*']],
        ['community', 'Communauté', 'community', ['community*']],
        ['profile', 'Profil', 'profile', ['profile*', 'history*', 'records*', 'progress*']],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-full bg-zinc-950 text-zinc-100 antialiased" data-auth="1">
    <div id="offline-banner" hidden class="sticky top-0 z-30 bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-zinc-950">
        Hors ligne — ta séance sera synchronisée
    </div>

    @if ($bare)
        {{ $slot }}
    @else
        <div class="lg:flex">
            <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-zinc-800 bg-zinc-950/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:sticky lg:top-0 lg:h-screen lg:w-56 lg:border-r lg:border-t-0 lg:pb-0">
                <p class="hidden px-6 py-8 text-2xl font-black tracking-[0.3em] text-lime-400 lg:block">EGO</p>
                <ul class="grid grid-cols-4 lg:grid-cols-1 lg:gap-1 lg:px-3">
                    @foreach ($tabs as [$route, $label, $icon, $patterns])
                        @php $active = request()->routeIs(...$patterns); @endphp
                        <li>
                            <a href="{{ route($route) }}"
                               @class([
                                   'flex flex-col items-center gap-1 py-2 text-xs lg:flex-row lg:gap-3 lg:rounded-xl lg:px-3 lg:py-3 lg:text-sm',
                                   'text-lime-400 lg:bg-zinc-900' => $active,
                                   'text-zinc-400 hover:text-zinc-100' => ! $active,
                               ])
                               @if ($active) aria-current="page" @endif>
                                @include('partials.nav-icon', ['name' => $icon])
                                <span>{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <main class="mx-auto w-full max-w-[720px] px-4 pb-28 pt-[max(1.5rem,env(safe-area-inset-top))] lg:pb-10">
                <a id="resume-banner" hidden href="{{ route('workout') }}"
                   class="mb-4 block rounded-xl bg-lime-400 px-4 py-3 text-center font-bold text-zinc-950">
                    Séance en cours — Reprendre
                </a>
                {{ $slot }}
            </main>
        </div>
    @endif

    @livewireScriptConfig
</body>
</html>
