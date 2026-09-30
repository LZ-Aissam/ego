@props(['title' => null])
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="flex min-h-full items-center justify-center bg-zinc-950 px-4 text-zinc-100 antialiased">
    <main class="w-full max-w-sm py-10">
        <p class="mb-8 text-center text-5xl font-black tracking-[0.3em] text-lime-400">EGO</p>
        {{ $slot }}
    </main>
    @livewireScriptConfig
</body>
</html>
