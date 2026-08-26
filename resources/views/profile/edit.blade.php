<x-app-layout>
    <x-slot name="title">{{ __('โปรไฟล์') }}</x-slot>

    <x-page-header :title="__('โปรไฟล์ของฉัน')" :subtitle="Auth::user()->email" />

    <div class="grid max-w-3xl gap-4">
        <x-card>
            @include('profile.partials.update-profile-information-form')
        </x-card>

        <x-card>
            @include('profile.partials.update-password-form')
        </x-card>

        <x-card>
            @include('profile.partials.delete-user-form')
        </x-card>
    </div>
</x-app-layout>
