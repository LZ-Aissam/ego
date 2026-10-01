@props(['name', 'label', 'type' => 'text'])
<div>
    <label for="{{ $name }}" class="sys-label">{{ $label }}</label>
    <input id="{{ $name }}" type="{{ $type }}" {{ $attributes->merge(['class' => 'sys-input']) }}>
    @error($name) <p class="sys-error">{{ $message }}</p> @enderror
</div>
