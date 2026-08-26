@props(['href', 'variant' => 'primary'])

@php
    $variants = [
        'primary' => 'bg-navy-900 text-white hover:bg-navy-800 focus:ring-navy-700',
        'accent' => 'bg-aqua-400 text-navy-900 hover:bg-aqua-300 focus:ring-aqua-500',
        'secondary' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-gray-400',
    ];
@endphp

<a href="{{ $href }}"
   {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-1 '.($variants[$variant] ?? $variants['primary'])]) }}>
    {{ $slot }}
</a>
