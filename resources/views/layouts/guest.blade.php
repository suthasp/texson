<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <link rel="icon" type="image/png" sizes="128x128" href="{{ asset('logo/favicon-128.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo/favicon-128.png') }}">
    <meta name="theme-color" content="#1B2A4A">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-navy-900 font-sans text-gray-900 antialiased">
<div class="flex min-h-full flex-col items-center justify-center px-4 py-10">

    <div class="mb-6 flex flex-col items-center gap-1.5">
        <x-application-logo on="dark" class="h-10 w-auto" />
        <p class="text-xs text-navy-200">{{ __('ระบบอะไหล่และงานขาย') }}</p>
    </div>

    <div class="w-full max-w-md rounded-xl bg-white px-6 py-7 shadow-lg sm:px-8">
        {{ $slot }}
    </div>

    <p class="mt-6 text-xs text-navy-300">{{ __('ระบบภายในองค์กร — ติดต่อผู้ดูแลระบบเพื่อขอบัญชีผู้ใช้') }}</p>
</div>
</body>
</html>
