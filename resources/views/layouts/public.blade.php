<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full motion-safe:scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? __('TEXSON — ที่ปรึกษา Data Center Facility | Audit · PM Planning · Training') }}</title>
    <meta name="description" content="{{ __('ตรวจประเมิน วางแผนบำรุงรักษา และอบรมทีมงาน ด้วยประสบการณ์ปฏิบัติงานจริงใน Data Center ระดับมืออาชีพ') }}">

    <link rel="icon" type="image/png" sizes="128x128" href="{{ asset('logo/favicon-128.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo/favicon-128.png') }}">
    <meta name="theme-color" content="#1B2A4A">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body id="top" class="h-full bg-white font-sans text-navy-900 antialiased">

{{-- ── แถบบนสุด ── --}}
<header x-data="{ open: false }"
        class="sticky top-0 z-40 border-b border-gray-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('landing') }}" class="shrink-0">
            <x-application-logo on="light" size="lg" />
        </a>

        <nav class="ms-auto hidden items-center gap-7 lg:flex">
            @foreach ([
                'services' => __('บริการ'),
                'products' => __('สินค้า'),
                'why-us' => __('ทำไมต้องเรา'),
                'process' => __('ขั้นตอน'),
                'contact' => __('ติดต่อ'),
            ] as $anchor => $label)
                <a href="#{{ $anchor }}" class="text-sm text-gray-600 transition hover:text-navy-900">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ms-auto flex items-center gap-2 lg:ms-0">
            {{-- สลับภาษา: ปุ่มแสดง "ภาษาที่จะสลับไป" ตามแบบในหน้าเว็บเดิม --}}
            <a href="{{ request()->fullUrlWithQuery(['lang' => app()->getLocale() === 'th' ? 'en' : 'th']) }}"
               class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-navy-800 transition hover:border-navy-900">
                {{ app()->getLocale() === 'th' ? 'EN' : 'ไทย' }}
            </a>

            @auth
                <a href="{{ route('dashboard') }}"
                   class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                    {{ __('แดชบอร์ด') }}
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="hidden rounded-lg px-3 py-2 text-sm font-medium text-navy-800 transition hover:bg-gray-100 sm:inline-block">
                    {{ __('เข้าสู่ระบบ') }}
                </a>
                <a href="#contact"
                   class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                    {{ __('ขอใบเสนอราคา') }}
                </a>
            @endauth

            <button type="button" @click="open = ! open"
                    class="rounded-lg p-2 text-navy-800 transition hover:bg-gray-100 lg:hidden"
                    :aria-expanded="open" aria-label="{{ __('เมนู') }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>
    </div>

    {{-- ── เมนูบนจอเล็ก ── --}}
    <div x-show="open" x-transition.opacity x-cloak class="border-t border-gray-200 lg:hidden">
        <nav class="mx-auto max-w-6xl space-y-1 px-4 py-3 sm:px-6">
            @foreach ([
                'services' => __('บริการ'),
                'products' => __('สินค้า'),
                'why-us' => __('ทำไมต้องเรา'),
                'process' => __('ขั้นตอน'),
                'contact' => __('ติดต่อ'),
            ] as $anchor => $label)
                <a href="#{{ $anchor }}" @click="open = false"
                   class="block rounded-lg px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-100">{{ $label }}</a>
            @endforeach

            @guest
                <a href="{{ route('login') }}"
                   class="block rounded-lg px-3 py-2 text-sm font-medium text-navy-900 transition hover:bg-gray-100">
                    {{ __('เข้าสู่ระบบ') }}
                </a>
            @endguest
        </nav>
    </div>
</header>

<main>
    {{ $slot }}
</main>

{{-- ── ท้ายหน้า ── --}}
<footer class="border-t border-navy-900">
    <div class="mx-auto flex max-w-6xl flex-col items-center gap-4 px-4 py-8 text-sm text-gray-500 sm:px-6 lg:px-8 md:flex-row md:justify-between md:gap-6">
        <div class="text-center md:text-start">
            <p>&copy; {{ now()->year }} Texson — {{ __('ที่ปรึกษา Data Center Facility · Audit · PM Planning · Training') }}</p>

            <p class="mt-1">
                <a href="{{ route('login') }}" class="underline underline-offset-2 transition hover:text-navy-900">
                    {{ __('เข้าสู่ระบบสำหรับพนักงาน') }}
                </a>
            </p>
        </div>

        {{--
            ใช้ลิงก์ไปยัง #top ไม่ใช่ JavaScript — เลื่อนนุ่มด้วย motion-safe:scroll-smooth ของ CSS
            จึงทำงานได้แม้ JS โหลดไม่ขึ้น และไม่ต้องผ่อนปรน CSP เพิ่ม
        --}}
        <a href="#top"
           class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 font-medium text-aqua-600 transition hover:bg-gray-100 hover:text-aqua-700">
            {{ __('กลับขึ้นด้านบน') }}
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v-15m0 0-6.75 6.75M12 4.5l6.75 6.75" />
            </svg>
        </a>
    </div>
</footer>

</body>
</html>
