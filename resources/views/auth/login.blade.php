<x-guest-layout>
    <h1 class="text-lg font-semibold text-navy-900">{{ __('เข้าสู่ระบบ') }}</h1>
    <p class="mt-1 text-sm text-gray-500">{{ __('ใช้อีเมลบริษัทของคุณเพื่อเข้าใช้งาน') }}</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf

        <div class="space-y-1">
            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('อีเมล') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   class="form-input-base {{ $errors->has('email') ? 'border-rose-400' : '' }}">
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div class="space-y-1">
            <label for="password" class="block text-sm font-medium text-gray-700">{{ __('รหัสผ่าน') }}</label>
            <input id="password" type="password" name="password"
                   required autocomplete="current-password"
                   class="form-input-base {{ $errors->has('password') ? 'border-rose-400' : '' }}">
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div class="flex items-center justify-between gap-3">
            <label for="remember_me" class="flex items-center gap-2 text-sm text-gray-600">
                <input id="remember_me" type="checkbox" name="remember"
                       class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                {{ __('จำฉันไว้') }}
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm text-aqua-600 hover:text-aqua-700">
                    {{ __('ลืมรหัสผ่าน?') }}
                </a>
            @endif
        </div>

        <button type="submit"
                class="w-full rounded-md bg-navy-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-navy-800 focus:outline-none focus:ring-2 focus:ring-navy-700 focus:ring-offset-2">
            {{ __('เข้าสู่ระบบ') }}
        </button>
    </form>
</x-guest-layout>
