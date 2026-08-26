<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขสินค้า') }}</x-slot>

    <x-page-header :title="$product->name_th" :subtitle="$product->sku" :back="route('products.show', $product)" />

    @include('products._form')
</x-app-layout>
