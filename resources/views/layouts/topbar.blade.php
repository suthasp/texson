<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">

    <button type="button"
            @click="sidebarOpen = ! sidebarOpen"
            class="rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden"
            aria-label="{{ __('เปิด/ปิดเมนู') }}">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </button>

    {{-- ค้นหาสินค้าแบบเร็วจากทุกหน้า --}}
    @can('viewAny', App\Models\Product::class)
        <form action="{{ route('products.index') }}" method="GET" class="hidden min-w-0 flex-1 sm:block sm:max-w-md">
            <label for="topbar-search" class="sr-only">{{ __('ค้นหาสินค้า') }}</label>
            <div class="relative">
                <svg class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                </svg>
                <input id="topbar-search" type="search" name="q" value="{{ request('q') }}"
                       placeholder="{{ __('ค้นหา SKU, ชื่อ หรือรุ่นสินค้า') }}"
                       class="form-input-base ps-9 text-sm">
            </div>
        </form>
    @endcan

    <div class="flex flex-1 items-center justify-end gap-2">

        {{-- สลับภาษา TH/EN (spec 7) --}}
        <div class="flex rounded-md border border-gray-200 p-0.5 text-xs font-medium">
            @foreach (['th' => 'ไทย', 'en' => 'EN'] as $code => $label)
                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}"
                   class="rounded px-2 py-1 transition {{ app()->getLocale() === $code ? 'bg-navy-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-700 transition hover:bg-gray-100">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-navy-100 text-xs font-semibold text-navy-900">
                        {{ Str::substr(Auth::user()->name, 0, 1) }}
                    </span>
                    <span class="hidden max-w-[10rem] truncate sm:block">{{ Auth::user()->name }}</span>
                    <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="border-b border-gray-100 px-4 py-3">
                    <p class="truncate text-sm font-medium text-gray-900">{{ Auth::user()->name }}</p>
                    <p class="truncate text-xs text-gray-500">{{ Auth::user()->email }}</p>
                    <p class="mt-1 text-xs text-aqua-600">{{ Auth::user()->roles->pluck('name')->map(fn ($r) => App\Enums\RoleName::from($r)->label())->implode(', ') }}</p>
                </div>

                <x-dropdown-link :href="route('profile.edit')">{{ __('โปรไฟล์') }}</x-dropdown-link>

                <form method="POST" action="{{ route('logout') }}" x-data>
                    @csrf
                    <x-dropdown-link :href="route('logout')"
                                     x-on:click.prevent="$el.closest('form').submit()">
                        {{ __('ออกจากระบบ') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</header>
