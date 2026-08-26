<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขลูกค้า') }}</x-slot>

    <x-page-header :title="$customer->name_th" :subtitle="__('แก้ไขข้อมูลลูกค้า')" :back="route('customers.show', $customer)" />

    @include('customers._form')
</x-app-layout>
