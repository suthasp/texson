<x-app-layout>
    <x-slot name="title">{{ __('เพิ่มหมวดหมู่') }}</x-slot>
    <x-page-header :title="__('เพิ่มหมวดหมู่')" :back="route('categories.index')" />
    @include('categories._form')
</x-app-layout>
