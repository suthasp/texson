<x-app-layout>
    <x-slot name="title">{{ __('แก้ไขหมวดหมู่') }}</x-slot>
    <x-page-header :title="$category->name_th" :subtitle="__('แก้ไขหมวดหมู่')" :back="route('categories.index')" />
    @include('categories._form')
</x-app-layout>
