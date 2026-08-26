@php
    /** @var App\Models\Supplier $supplier */
    $isEdit = $supplier->exists;
@endphp

<form method="POST" action="{{ $isEdit ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-form.input name="code" :label="__('รหัสผู้ขาย')" :value="$supplier->code" required :help="__('เช่น SUP-0001')" />
            <x-form.input name="name" :label="__('ชื่อผู้ขาย')" :value="$supplier->name" required class="lg:col-span-2" />
            <x-form.input name="tax_id" :label="__('เลขประจำตัวผู้เสียภาษี')" :value="$supplier->tax_id" inputmode="numeric" maxlength="13" />
            <x-form.input name="contact_name" :label="__('ผู้ติดต่อ')" :value="$supplier->contact_name" />
            <x-form.input name="phone" :label="__('โทรศัพท์')" :value="$supplier->phone" type="tel" />
            <x-form.input name="email" :label="__('อีเมล')" :value="$supplier->email" type="email" />
            <x-form.input name="lead_time_days" :label="__('ระยะเวลาส่งของ (วัน)')" :value="$supplier->lead_time_days ?? 0"
                          type="number" min="0" max="3650" required />

            <x-form.textarea name="notes" :label="__('หมายเหตุ')" :value="$supplier->notes" rows="3" class="lg:col-span-3" />
            <x-form.checkbox name="is_active" :label="__('เปิดใช้งานผู้ขายรายนี้')" :checked="$supplier->is_active ?? true" class="lg:col-span-3" />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="route('suppliers.index')" variant="secondary">{{ __('ยกเลิก') }}</x-link-button>
        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกผู้ขาย') }}
        </button>
    </div>
</form>
