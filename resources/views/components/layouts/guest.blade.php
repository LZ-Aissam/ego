@props(['title' => null])
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="sys-body flex min-h-full items-center justify-center px-4 antialiased">
    @include('partials.backdrop')
    <main class="w-full max-w-sm py-10">
        <p class="sys-logo mb-2 text-center text-5xl">EGO</p>
        <p class="sys-label mb-8 text-center">Système d'entraînement</p>
        {{ $slot }}
    </main>
    @livewireScriptConfig
</body>
</html>
