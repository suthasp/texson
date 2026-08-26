<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขยี่ห้อ') }}</x-slot>
    <x-page-header :title="$brand->name" :subtitle="__('แก้ไขยี่ห้อ')" :back="route('brands.index')" />
    @include('brands._form')
</x-app-layout>
