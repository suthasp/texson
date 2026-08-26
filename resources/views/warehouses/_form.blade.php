@php
    /** @var App\Models\Warehouse $warehouse */
    $isEdit = $warehouse->exists;
@endphp

<form method="POST" action="{{ $isEdit ? route('warehouses.update', $warehouse) : route('warehouses.store') }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="code" :label="__('รหัสคลัง')" :value="$warehouse->code" required :help="__('เช่น HQ, VAN, CONSIGN')" />
            <x-form.input name="name" :label="__('ชื่อคลัง')" :value="$warehouse->name" required />
            <x-form.input name="address" :label="__('ที่อยู่')" :value="$warehouse->address" class="sm:col-span-2" />

            <div class="space-y-3 sm:col-span-2">
                <x-form.checkbox name="is_default" :label="__('ตั้งเป็นคลังเริ่มต้น')" :checked="$warehouse->is_default"
                                 :help="__('คลังเริ่มต้นมีได้คลังเดียว — ตั้งคลังใหม่แล้วคลังเดิมจะถูกปลดอัตโนมัติ')" />
                <x-form.checkbox name="is_active" :label="__('เปิดใช้งานคลังนี้')" :checked="$warehouse->is_active ?? true" />
            </div>
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="route('warehouses.index')" variant="secondary">{{ __('ยกเลิก') }}</x-link-button>
        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกคลัง') }}
        </button>
    </div>
</form>
