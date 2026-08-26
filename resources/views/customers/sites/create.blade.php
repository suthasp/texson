<x-app-layout>
    <x-slot name="title">{{ __('เพิ่มหน้างาน') }}</x-slot>

    <x-page-header :title="__('เพิ่มหน้างาน')" :subtitle="$customer->name_th" :back="route('customers.show', $customer)" />

    @include('customers.sites._form')
</x-app-layout>
