<x-app-layout>
    <x-slot name="title">{{ __('เพิ่มยี่ห้อ') }}</x-slot>
    <x-page-header :title="__('เพิ่มยี่ห้อ')" :back="route('brands.index')" />
    @include('brands._form')
</x-app-layout>
