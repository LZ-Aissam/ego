<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#09090b">
<link rel="manifest" href="/manifest.json">
<link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<title>{{ $title ? $title.' · EGO' : 'EGO' }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
