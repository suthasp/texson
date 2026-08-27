<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขใบปรับปรุง') }}</x-slot>
    <x-page-header :title="$adjustment->adjust_no" :subtitle="__('แก้ไขใบปรับปรุงสต็อก')"
                   :back="route('stock-adjustments.show', $adjustment)" />
    @include('stock-adjustments._form')
</x-app-layout>
