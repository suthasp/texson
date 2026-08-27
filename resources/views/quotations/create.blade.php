<x-app-layout>
    <x-slot name="title">{{ __('สร้างใบเสนอราคา') }}</x-slot>
    <x-page-header :title="__('สร้างใบเสนอราคา')"
                   :subtitle="__('บันทึกเป็นร่างก่อน แล้วค่อยส่งอนุมัติหรือส่งให้ลูกค้า')"
                   :back="route('quotations.index')" />
    @include('quotations._form')
</x-app-layout>
