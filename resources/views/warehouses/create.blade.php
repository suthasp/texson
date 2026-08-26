<x-app-layout>
    <x-slot name="title">{{ __('เพิ่มคลัง') }}</x-slot>
    <x-page-header :title="__('เพิ่มคลังสินค้า')" :back="route('warehouses.index')" />
    @include('warehouses._form')
</x-app-layout>
