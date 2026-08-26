@php
    /** @var App\Models\Brand $brand */
    $isEdit = $brand->exists;
@endphp

<form method="POST" action="{{ $isEdit ? route('brands.update', $brand) : route('brands.store') }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card>
        <div class="grid gap-4 sm:max-w-md">
            <x-form.input name="name" :label="__('ชื่อยี่ห้อ')" :value="$brand->name" required />
            <x-form.checkbox name="is_active" :label="__('เปิดใช้งานยี่ห้อนี้')" :checked="$brand->is_active ?? true" />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="route('brands.index')" variant="secondary">{{ __('ยกเลิก') }}</x-link-button>
        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกยี่ห้อ') }}
        </button>
    </div>
</form>
