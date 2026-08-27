@php
    /**
     * @var App\Models\Delivery $delivery
     * @var App\Models\SalesOrder $order
     * @var array<int, array<string, mixed>> $lines
     */
    $isEdit = $delivery->exists;
    $rows = old('items', $lines);
@endphp

<form method="POST" x-data="deliveryLines({ lines: @js($rows) })"
      action="{{ $isEdit ? route('deliveries.update', $delivery) : route('sales-orders.deliveries.store', $order) }}"
      class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card :title="__('ข้อมูลใบส่งของ')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.select name="warehouse_id" :label="__('คลังที่จ่ายของ')"
                           :options="$warehouses->mapWithKeys(fn ($w) => [$w->id => $w->code . ' — ' . $w->name])->all()"
                           :selected="$delivery->warehouse_id ?? $order->warehouse_id"
                           :help="__('ปกติคือคลังที่จองของไว้')"
                           required />

            <x-form.input name="delivery_date" type="date" :label="__('วันที่ส่ง')"
                          :value="optional($delivery->delivery_date)->format('Y-m-d') ?? now()->format('Y-m-d')"
                          :help="__('ใช้เป็นวันเริ่มรับประกันของสินค้าที่มี serial')"
                          required />

            <x-form.input name="receiver_name" :label="__('ชื่อผู้รับของ')" :value="$delivery->receiver_name" />

            <x-form.input name="vehicle_note" :label="__('รถ / ผู้ส่ง')" :value="$delivery->vehicle_note"
                          :help="__('เช่น ทะเบียนรถ หรือชื่อขนส่ง')" />
        </div>
    </x-card>

    {{-- ── รายการที่ส่ง ── --}}
    <x-card :padded="false">
        <x-slot name="header">
            <div>
                <h2 class="text-sm font-semibold text-navy-900">{{ __('รายการที่ส่ง') }}</h2>
                <p class="text-xs text-gray-500">{{ __('ติ๊กเฉพาะบรรทัดที่ส่งรอบนี้ · ส่งไม่ครบได้ ที่เหลือค้างไว้ออกใบใหม่ทีหลัง') }}</p>
            </div>
            <button type="button" @click="selectAllOutstanding()"
                    class="rounded-md border border-aqua-200 bg-aqua-50 px-3 py-1.5 text-xs font-medium text-aqua-700 transition hover:bg-aqua-100">
                {{ __('ส่งทั้งหมดที่ค้าง') }}
            </button>
        </x-slot>

        <div class="space-y-3 p-4 sm:p-5">
            <template x-for="(line, index) in lines" :key="index">
                <div class="rounded-lg border p-3 transition"
                     :class="line.include ? 'border-aqua-200 bg-aqua-50/30' : 'border-gray-200 bg-gray-50/50'">

                    <div class="flex flex-wrap items-start gap-3">
                        <label class="flex items-center pt-1">
                            <input type="checkbox" x-model="line.include"
                                   class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                            <span class="sr-only">{{ __('ส่งบรรทัดนี้') }}</span>
                        </label>

                        <div class="min-w-0 flex-1">
                            <p class="tabular text-xs font-medium text-navy-900" x-text="line.sku || '—'"></p>
                            <p class="text-sm text-gray-800" x-text="line.description"></p>
                            <p class="tabular mt-0.5 text-xs text-gray-500">
                                {{ __('ส่งแล้ว') }} <span x-text="qtyLabel(line)"></span>
                                <span x-text="line.uom || ''"></span>
                                · {{ __('ค้างอีก') }} <span class="font-medium" x-text="outstanding(line).toLocaleString()"></span>
                            </p>
                        </div>

                        <div x-show="line.include" x-cloak class="w-32">
                            <label class="mb-1 block text-xs text-gray-600" :for="`dn-qty-${index}`">{{ __('จำนวนที่ส่ง') }}</label>
                            <input type="number" step="0.001" min="0.001"
                                   :id="`dn-qty-${index}`"
                                   x-model="line.qty"
                                   :name="`items[${index}][qty]`"
                                   class="form-input-base tabular text-sm"
                                   :class="isOverDelivering(line) ? 'border-rose-400' : ''">
                            <p x-show="isOverDelivering(line)" x-cloak class="mt-1 text-[11px] text-rose-700">
                                {{ __('เกินยอดที่ค้าง') }}
                            </p>
                        </div>

                        <div x-show="line.include" x-cloak class="w-32">
                            <label class="mb-1 block text-xs text-gray-600" :for="`dn-lot-${index}`">{{ __('Lot') }}</label>
                            <input type="text" :id="`dn-lot-${index}`"
                                   x-model="line.lot_no" :name="`items[${index}][lot_no]`"
                                   class="form-input-base text-sm">
                        </div>
                    </div>

                    {{-- ส่งบรรทัดนี้ก็ต่อเมื่อติ๊กไว้ — hidden input จึงอยู่ใต้ x-if --}}
                    <template x-if="line.include">
                        <input type="hidden" :name="`items[${index}][sales_order_item_id]`" :value="line.sales_order_item_id">
                    </template>

                    {{-- ── serial: บังคับกรอกให้ครบตามจำนวน (spec 4.4) ── --}}
                    <div x-show="line.include && line.is_serialized" x-cloak class="mt-3 border-t border-gray-200 pt-3">
                        <label class="mb-1 flex flex-wrap items-center gap-2 text-xs text-gray-600" :for="`dn-serial-${index}`">
                            {{ __('Serial ที่จ่ายออก (บรรทัดละ 1 ตัว)') }}
                            <span class="tabular rounded px-1.5 py-0.5 text-[11px]"
                                  :class="serialMismatch(line) ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                  x-text="`${serialCount(line)} / ${line.qty || 0}`"></span>
                        </label>

                        <textarea :id="`dn-serial-${index}`"
                                  x-model="line.serial_numbers"
                                  :name="`items[${index}][serial_numbers]`"
                                  rows="3"
                                  placeholder="{{ __("SN00001\nSN00002") }}"
                                  class="form-input-base tabular text-sm"></textarea>

                        <p x-show="serialMismatch(line)" x-cloak class="mt-1 text-xs text-amber-700">
                            {{ __('ต้องระบุ serial ให้ครบเท่าจำนวนที่ส่ง ไม่งั้นจะบันทึกเข้าสต็อกไม่ผ่าน') }}
                        </p>
                    </div>
                </div>
            </template>

            <p x-show="includedCount === 0" x-cloak class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                {{ __('ยังไม่ได้เลือกบรรทัดที่จะส่ง — ติ๊กอย่างน้อย 1 บรรทัด') }}
            </p>
        </div>

        <x-input-error :messages="$errors->get('items')" class="px-5 pb-4" />
    </x-card>

    <x-card :title="__('หมายเหตุ')">
        <x-form.textarea name="note" :label="__('หมายเหตุ')" :value="$delivery->note" rows="3" />
    </x-card>

    <div class="flex flex-wrap items-center justify-end gap-3">
        <p x-show="hasProblem()" x-cloak class="me-auto text-sm text-amber-700">
            {{ __('มีบรรทัดที่ยังไม่เรียบร้อย — บันทึกเป็นร่างได้ แต่จะบันทึกเข้าสต็อกไม่ผ่าน') }}
        </p>

        <x-link-button :href="$isEdit ? route('deliveries.show', $delivery) : route('sales-orders.show', $order)" variant="secondary">
            {{ __('ยกเลิก') }}
        </x-link-button>

        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกเป็นร่าง') }}
        </button>
    </div>
</form>
