<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขคลัง') }}</x-slot>
    <x-page-header :title="$warehouse->name" :subtitle="$warehouse->code" :back="route('warehouses.index')" />
    @include('warehouses._form')
</x-app-layout>
