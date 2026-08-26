@props(['name', 'label', 'required' => false, 'help' => null])

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if ($required)
            <span class="text-rose-600" aria-hidden="true">*</span>
        @endif
    </label>

    {{ $slot }}

    @if ($help)
        <p class="text-xs text-gray-500">{{ $help }}</p>
    @endif

    <x-input-error :messages="$errors->get($name)" class="mt-1" />
</div>
