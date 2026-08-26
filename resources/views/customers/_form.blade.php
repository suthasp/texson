@php
    /** @var App\Models\Customer $customer */
    $isEdit = $customer->exists;
@endphp

<form method="POST" action="{{ $isEdit ? route('customers.update', $customer) : route('customers.store') }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card :title="__('ข้อมูลทั่วไป')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-form.input name="code" :label="__('รหัสลูกค้า')" :value="$customer->code" required
                          :help="__('เช่น CUS-0001 — ห้ามซ้ำกับลูกค้ารายอื่น')" />
            <x-form.input name="name_th" :label="__('ชื่อลูกค้า (ไทย)')" :value="$customer->name_th" required class="lg:col-span-2" />
            <x-form.input name="name_en" :label="__('ชื่อลูกค้า (อังกฤษ)')" :value="$customer->name_en" class="lg:col-span-2" />

            <x-form.input name="tax_id" :label="__('เลขประจำตัวผู้เสียภาษี')" :value="$customer->tax_id"
                          inputmode="numeric" maxlength="13" :help="__('13 หลัก ไม่ต้องใส่ขีด')" />
            <x-form.input name="branch_code" :label="__('รหัสสาขา')" :value="$customer->branch_code ?? '00000'" required
                          inputmode="numeric" maxlength="5" :help="__('00000 = สำนักงานใหญ่')" />
        </div>
    </x-card>

    <x-card :title="__('ที่อยู่และการติดต่อ')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-form.input name="address_line" :label="__('ที่อยู่')" :value="$customer->address_line" class="lg:col-span-3" />
            <x-form.input name="subdistrict" :label="__('ตำบล / แขวง')" :value="$customer->subdistrict" />
            <x-form.input name="district" :label="__('อำเภอ / เขต')" :value="$customer->district" />
            <x-form.input name="province" :label="__('จังหวัด')" :value="$customer->province" />
            <x-form.input name="postcode" :label="__('รหัสไปรษณีย์')" :value="$customer->postcode" inputmode="numeric" maxlength="5" />
            <x-form.input name="phone" :label="__('โทรศัพท์')" :value="$customer->phone" type="tel" />
            <x-form.input name="email" :label="__('อีเมล')" :value="$customer->email" type="email" />
        </div>
    </x-card>

    <x-card :title="__('เงื่อนไขการค้า')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-form.select name="price_tier" :label="__('ระดับราคา')" :options="$priceTiers"
                           :selected="$customer->price_tier?->value" required
                           :help="__('ใช้เลือกราคาตั้งต้นตอนออกใบเสนอราคา')" />
            <x-form.input name="credit_term_days" :label="__('เครดิต (วัน)')" :value="$customer->credit_term_days ?? 30"
                          type="number" min="0" max="365" required />
            <x-form.input name="payment_terms" :label="__('เงื่อนไขการชำระเงิน')" :value="$customer->payment_terms"
                          :help="__('เช่น โอนเงินภายใน 30 วันหลังส่งของ')" />

            <x-form.textarea name="notes" :label="__('หมายเหตุภายใน')" :value="$customer->notes" rows="3" class="lg:col-span-3" />

            <x-form.checkbox name="is_active" :label="__('เปิดใช้งานลูกค้ารายนี้')" :checked="$customer->is_active ?? true" class="lg:col-span-3" />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="$isEdit ? route('customers.show', $customer) : route('customers.index')" variant="secondary">
            {{ __('ยกเลิก') }}
        </x-link-button>

        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกลูกค้า') }}
        </button>
    </div>
</form>
