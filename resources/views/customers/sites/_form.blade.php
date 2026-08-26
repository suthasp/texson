@php
    /** @var App\Models\CustomerSite $site */
    $isEdit = $site->exists;
    $contactOptions = $contacts->pluck('name', 'id')->all();
@endphp

<form method="POST"
      action="{{ $isEdit ? route('customers.sites.update', [$customer, $site]) : route('customers.sites.store', $customer) }}"
      class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="site_code" :label="__('รหัสหน้างาน')" :value="$site->site_code" required
                          :help="__('ไม่ซ้ำภายในลูกค้ารายนี้ เช่น DC-01')" />
            <x-form.input name="site_name" :label="__('ชื่อหน้างาน')" :value="$site->site_name" required
                          :help="__('เช่น DC ชั้น 3 อาคาร A')" />
            <x-form.input name="address_line" :label="__('ที่อยู่')" :value="$site->address_line" class="sm:col-span-2" />
            <x-form.input name="province" :label="__('จังหวัด')" :value="$site->province" />

            <x-form.select name="primary_contact_id" :label="__('ผู้ติดต่อหลักของหน้างาน')"
                           :options="$contactOptions" :selected="$site->primary_contact_id"
                           :placeholder="__('— ไม่ระบุ —')"
                           :help="$contacts->isEmpty() ? __('ยังไม่มีผู้ติดต่อให้เลือก — เพิ่มผู้ติดต่อก่อน') : null" />

            <x-form.textarea name="access_note" :label="__('หมายเหตุการเข้าพื้นที่')" :value="$site->access_note" rows="3"
                             :help="__('เช่น ต้องแจ้งล่วงหน้า 1 วัน / ต้องมีบัตรผ่าน / เข้าได้เฉพาะวันเสาร์')"
                             class="sm:col-span-2" />

            <x-form.checkbox name="is_active" :label="__('เปิดใช้งานหน้างานนี้')" :checked="$site->is_active ?? true" class="sm:col-span-2" />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="route('customers.show', $customer)" variant="secondary">{{ __('ยกเลิก') }}</x-link-button>
        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('เพิ่มหน้างาน') }}
        </button>
    </div>
</form>
