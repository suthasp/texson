@props(['title' => null, 'padded' => true])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm']) }}>
    @if ($title || isset($header))
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-3 sm:px-5">
            @if ($title)
                <h2 class="text-sm font-semibold text-navy-900">{{ $title }}</h2>
            @endif
            @isset($header)
                {{ $header }}
            @endisset
        </div>
    @endif

    <div class="{{ $padded ? 'p-4 sm:p-5' : '' }}">
        {{ $slot }}
    </div>
</section>
