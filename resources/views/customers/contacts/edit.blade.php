<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขผู้ติดต่อ') }}</x-slot>

    <x-page-header :title="__('แก้ไขผู้ติดต่อ')" :subtitle="$customer->name_th" :back="route('customers.show', $customer)" />

    @include('customers.contacts._form')
</x-app-layout>
