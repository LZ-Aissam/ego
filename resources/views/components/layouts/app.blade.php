@props(['title' => null, 'bare' => false])
@php
    $tabs = [
        ['home', 'Accueil', 'home', ['home']],
        ['program', 'Programme', 'program', ['program*']],
        ['community', 'Crew', 'community', ['community*']],
        ['profile', 'Profil', 'profile', ['profile*', 'history*', 'records*', 'progress*']],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
    @if (! empty($systemNotifications))
        {{-- Le service worker ne met pas cette page en cache : sinon la notification réapparaîtrait hors ligne --}}
        <meta name="ego-no-cache" content="1">
    @endif
</head>
<body class="sys-body min-h-full antialiased" data-user-id="{{ auth()->id() }}">
    @include('partials.backdrop')

    <div id="offline-banner" hidden class="sys-banner sys-banner--amber">Hors ligne — ta séance sera synchronisée</div>

    @if ($bare)
        {{ $slot }}
    @else
        <div class="lg:flex">
            <nav class="sys-nav">
                <p class="sys-logo hidden px-6 py-8 text-2xl lg:block">EGO</p>
                <ul class="grid grid-cols-4 lg:grid-cols-1 lg:gap-1 lg:px-3">
                    @foreach ($tabs as [$route, $label, $icon, $patterns])
                        @php $active = request()->routeIs(...$patterns); @endphp
                        <li>
                            <a href="{{ route($route) }}" @class(['sys-nav-link', 'sys-nav-link--active' => $active]) @if ($active) aria-current="page" @endif>
                                @include('partials.nav-icon', ['name' => $icon])
                                <span>{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <main class="relative mx-auto w-full max-w-[720px] px-4 pb-32 pt-[max(1.5rem,env(safe-area-inset-top))] lg:pb-12">
                <a id="resume-banner" hidden href="{{ route('workout') }}" class="sys-banner sys-banner--ice mb-5">Séance en cours — Reprendre</a>
                {{ $slot }}
            </main>
        </div>
    @endif

    <x-sys.notifications :items="$systemNotifications ?? []" />
    @livewireScriptConfig
</body>
</html>
