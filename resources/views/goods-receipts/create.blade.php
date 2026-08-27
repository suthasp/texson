<x-app-layout>
    <x-slot name="title">{{ __('รับสินค้าเข้า') }}</x-slot>
    <x-page-header :title="__('รับสินค้าเข้า')"
                   :subtitle="__('บันทึกเป็นร่างก่อน แล้วค่อยกด post เพื่อเข้าสต็อกจริง')"
                   :back="route('goods-receipts.index')" />
    @include('goods-receipts._form')
</x-app-layout>
