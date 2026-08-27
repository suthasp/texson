<x-app-layout>
    <x-slot name="title">{{ __('แก้ไข') }} {{ $quotation->displayNo() }}</x-slot>
    <x-page-header :title="$quotation->displayNo()"
                   :subtitle="__('แก้ไขใบเสนอราคา · :customer', ['customer' => $quotation->customer->name_th])"
                   :back="route('quotations.show', $quotation)" />
    @include('quotations._form')
</x-app-layout>
