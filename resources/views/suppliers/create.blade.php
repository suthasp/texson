<x-app-layout>
    <x-slot name="title">{{ __('เพิ่มผู้ขาย') }}</x-slot>

    <x-page-header :title="__('เพิ่มผู้ขาย')" :back="route('suppliers.index')" />

    @include('suppliers._form')
</x-app-layout>
