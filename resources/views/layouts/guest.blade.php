<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-navy-900 font-sans text-gray-900 antialiased">
<div class="flex min-h-full flex-col items-center justify-center px-4 py-10">

    <div class="mb-6 flex items-center gap-3">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-aqua-400 text-xl font-bold text-navy-900">T</span>
        <div>
            <p class="text-lg font-semibold text-white">TEXSON</p>
            <p class="text-xs text-navy-200">{{ __('ระบบอะไหล่และงานขาย') }}</p>
        </div>
    </div>

    <div class="w-full max-w-md rounded-xl bg-white px-6 py-7 shadow-lg sm:px-8">
        {{ $slot }}
    </div>

    <p class="mt-6 text-xs text-navy-300">{{ __('ระบบภายในองค์กร — ติดต่อผู้ดูแลระบบเพื่อขอบัญชีผู้ใช้') }}</p>
</div>
</body>
</html>
