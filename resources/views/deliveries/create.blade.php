<x-app-layout>
    <x-slot name="title">{{ __('ออกใบส่งของ') }}</x-slot>
    <x-page-header :title="__('ออกใบส่งของ')"
                   :subtitle="__('จากใบสั่งขาย :no · :customer', ['no' => $order->so_no, 'customer' => $order->customer->name_th])"
                   :back="route('sales-orders.show', $order)" />
    @include('deliveries._form')
</x-app-layout>
