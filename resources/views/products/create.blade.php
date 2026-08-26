<x-app-layout>
    <x-slot name="title">{{ __('เพิ่มสินค้า') }}</x-slot>

    <x-page-header :title="__('เพิ่มสินค้า')" :back="route('products.index')" />

    @include('products._form')
</x-app-layout>
