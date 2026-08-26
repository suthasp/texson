<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขผู้ขาย') }}</x-slot>

    <x-page-header :title="$supplier->name" :subtitle="$supplier->code" :back="route('suppliers.index')" />

    @include('suppliers._form')
</x-app-layout>
