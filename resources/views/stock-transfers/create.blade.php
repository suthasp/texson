<x-app-layout>
    <x-slot name="title">{{ __('โอนคลัง') }}</x-slot>
    <x-page-header :title="__('โอนสินค้าระหว่างคลัง')"
                   :subtitle="__('บันทึกเป็นร่างก่อน แล้วค่อยกด post เพื่อตัดของจากคลังต้นทางเข้าคลังปลายทาง')"
                   :back="route('stock-transfers.index')" />
    @include('stock-transfers._form')
</x-app-layout>
