<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขผู้ใช้') }}</x-slot>
    <x-page-header :title="$user->name" :subtitle="$user->email" :back="route('users.index')" />
    @include('users._form')
</x-app-layout>
