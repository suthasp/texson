<x-app-layout>
    <x-slot name="title">{{ __('แก้ไข') }} {{ $delivery->delivery_no }}</x-slot>
    <x-page-header :title="$delivery->delivery_no"
                   :subtitle="__('แก้ไขใบส่งของ · จากใบสั่งขาย :no', ['no' => $order->so_no])"
                   :back="route('deliveries.show', $delivery)" />
    @include('deliveries._form')
</x-app-layout>
