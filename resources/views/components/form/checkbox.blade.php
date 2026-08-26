@props(['name', 'label', 'checked' => false, 'help' => null])

<div {{ $attributes->merge(['class' => 'flex items-start gap-2.5']) }}>
    {{-- hidden input ทำให้ checkbox ที่ไม่ติ๊กส่งค่า 0 มาด้วย ไม่ใช่หายไปเฉย ๆ --}}
    <input type="hidden" name="{{ $name }}" value="0">

    <input id="{{ $name }}"
           name="{{ $name }}"
           type="checkbox"
           value="1"
           @checked((bool) old($name, $checked))
           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">

    <div class="min-w-0">
        <label for="{{ $name }}" class="text-sm text-gray-700">{{ $label }}</label>
        @if ($help)
            <p class="text-xs text-gray-500">{{ $help }}</p>
        @endif
        <x-input-error :messages="$errors->get($name)" class="mt-1" />
    </div>
</div>
