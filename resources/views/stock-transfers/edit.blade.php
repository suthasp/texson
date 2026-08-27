<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขใบโอน') }}</x-slot>
    <x-page-header :title="$transfer->transfer_no" :subtitle="__('แก้ไขใบโอนคลัง')"
                   :back="route('stock-transfers.show', $transfer)" />
    @include('stock-transfers._form')
</x-app-layout>
