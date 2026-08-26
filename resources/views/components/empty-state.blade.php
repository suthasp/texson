@props(['message' => null])

<div class="px-4 py-12 text-center">
    <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5 12 12m0 0L3.75 7.5M12 12v9.75m8.25-14.25v9l-8.25 4.5-8.25-4.5v-9L12 3l8.25 4.5Z" />
    </svg>
    <p class="mt-3 text-sm text-gray-500">{{ $message ?? __('ยังไม่มีข้อมูล') }}</p>

    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
