@php
    /** @var App\Models\CustomerContact $contact */
    $isEdit = $contact->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('customers.contacts.update', [$customer, $contact]) : route('customers.contacts.store', $customer) }}"
      class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="name" :label="__('ชื่อผู้ติดต่อ')" :value="$contact->name" required />
            <x-form.input name="position" :label="__('ตำแหน่ง')" :value="$contact->position" />
            <x-form.input name="phone" :label="__('โทรศัพท์')" :value="$contact->phone" type="tel" />
            <x-form.input name="email" :label="__('อีเมล')" :value="$contact->email" type="email" />
            <x-form.input name="line_id" :label="__('LINE ID')" :value="$contact->line_id" />

            <x-form.checkbox name="is_primary" :label="__('ตั้งเป็นผู้ติดต่อหลัก')" :checked="$contact->is_primary"
                             :help="__('ลูกค้าหนึ่งรายมีผู้ติดต่อหลักได้คนเดียว — ตั้งคนใหม่แล้วคนเดิมจะถูกปลดอัตโนมัติ')"
                             class="sm:col-span-2" />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="route('customers.show', $customer)" variant="secondary">{{ __('ยกเลิก') }}</x-link-button>
        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('เพิ่มผู้ติดต่อ') }}
        </button>
    </div>
</form>
