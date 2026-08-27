<x-app-layout>
    <x-slot name="title">{{ __('ปรับปรุงสต็อก') }}</x-slot>
    <x-page-header :title="__('ปรับปรุงสต็อก')"
                   :subtitle="__('ปรับยอดในระบบให้ตรงกับของจริงหน้างาน')"
                   :back="route('stock-adjustments.index')" />
    @include('stock-adjustments._form')
</x-app-layout>
