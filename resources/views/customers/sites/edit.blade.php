<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขหน้างาน') }}</x-slot>

    <x-page-header :title="__('แก้ไขหน้างาน')" :subtitle="$customer->name_th" :back="route('customers.show', $customer)" />

    @include('customers.sites._form')
</x-app-layout>
