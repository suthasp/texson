<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขใบรับสินค้า') }}</x-slot>
    <x-page-header :title="$receipt->receipt_no" :subtitle="__('แก้ไขใบรับสินค้า')"
                   :back="route('goods-receipts.show', $receipt)" />
    @include('goods-receipts._form')
</x-app-layout>
