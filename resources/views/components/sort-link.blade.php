@props(['column', 'label'])

@php
    $current = request('sort');
    $direction = request('direction') === 'desc' ? 'desc' : 'asc';
    $isActive = $current === $column;
    $nextDirection = $isActive && $direction === 'asc' ? 'desc' : 'asc';
@endphp

<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection, 'page' => null]) }}"
   @if ($isActive) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif
   class="inline-flex items-center gap-1 transition hover:text-aqua-600 {{ $isActive ? 'text-aqua-600' : '' }}">
    {{ $label }}
    @if ($isActive)
        <svg class="h-3 w-3 {{ $direction === 'desc' ? 'rotate-180' : '' }}" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 5a.75.75 0 0 1 .55.24l4 4.25a.75.75 0 1 1-1.1 1.02L10 6.852 6.55 10.51a.75.75 0 1 1-1.1-1.02l4-4.25A.75.75 0 0 1 10 5Z" clip-rule="evenodd" />
        </svg>
    @endif
</a>
