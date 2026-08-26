<x-app-layout>
    <x-slot name="title">{{ __('เพิ่มลูกค้า') }}</x-slot>

    <x-page-header :title="__('เพิ่มลูกค้า')" :back="route('customers.index')" />

    @include('customers._form')
</x-app-layout>
