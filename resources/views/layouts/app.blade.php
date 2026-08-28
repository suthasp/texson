<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' · ' : '' }}{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-50 font-sans text-gray-900 antialiased">
<div x-data="{ sidebarOpen: false }" class="min-h-full lg:flex">

    {{-- ── ม่านดำตอนเปิดเมนูบนจอเล็ก ── --}}
    <div x-show="sidebarOpen"
         x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-navy-950/50 lg:hidden"
         x-cloak></div>

    @include('layouts.sidebar')

    <div class="flex min-w-0 flex-1 flex-col">
        @include('layouts.topbar')

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <x-flash-messages />

            {{ $slot }}
        </main>

        <footer class="border-t border-gray-200 px-4 py-4 text-center text-xs text-gray-500 sm:px-6 lg:px-8">
            {{ config('app.name') }} · {{ __('ระบบภายในองค์กร') }}
        </footer>
    </div>
</div>
</body>
</html>
