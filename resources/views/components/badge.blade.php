@props(['color' => 'gray'])

@php
    $palette = [
        'gray' => 'bg-gray-100 text-gray-700',
        'navy' => 'bg-navy-100 text-navy-800',
        'aqua' => 'bg-aqua-100 text-aqua-800',
        'green' => 'bg-emerald-100 text-emerald-800',
        'amber' => 'bg-amber-100 text-amber-800',
        'red' => 'bg-rose-100 text-rose-800',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.($palette[$color] ?? $palette['gray'])]) }}>
    {{ $slot }}
</span>
