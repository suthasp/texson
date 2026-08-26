@props(['title', 'subtitle' => null, 'back' => null])

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-1 inline-flex items-center gap-1 text-xs text-gray-500 transition hover:text-navy-700">
                <svg class="h-3.5 w-3.5 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ __('ย้อนกลับ') }}
            </a>
        @endif

        <h1 class="truncate text-xl font-semibold text-navy-900 sm:text-2xl">{{ $title }}</h1>

        @if ($subtitle)
            <p class="mt-0.5 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
