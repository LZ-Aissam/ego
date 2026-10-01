<x-layouts.app :title="$title">
    <x-sys.label>{{ $title }}</x-sys.label>
    <x-sys.window title="ACCÈS VERROUILLÉ" class="mt-3">
        <p class="text-mist">{{ $message }}</p>
    </x-sys.window>
</x-layouts.app>
