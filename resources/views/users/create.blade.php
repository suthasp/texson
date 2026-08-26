<x-app-layout>
    <x-slot name="title">{{ __('เพิ่มผู้ใช้') }}</x-slot>
    <x-page-header :title="__('เพิ่มผู้ใช้งาน')" :back="route('users.index')" />
    @include('users._form')
</x-app-layout>
