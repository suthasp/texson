@php
    /**
     * ตัวแก้ไขบรรทัดสินค้าที่ใช้ร่วมกันในใบรับสินค้า ใบโอนคลัง และใบปรับปรุงสต็อก
     *
     * ต้องอยู่ภายใน x-data="stockLines({...})" ของฟอร์มที่เรียกใช้
     *
     * ตัวแปรที่รับ:
     *   title      หัวข้อการ์ด
     *   qtyField   ชื่อฟิลด์จำนวน ('qty' หรือ 'qty_counted')
     *   showCost   แสดงช่องราคาทุนหรือไม่
     *   showSerial แสดงช่อง serial หรือไม่
     */
    $qtyField = $qtyField ?? 'qty';
    $showCost = $showCost ?? false;
    $showSerial = $showSerial ?? false;
    $qtyLabel = $qtyField === 'qty_counted' ? __('จำนวนที่นับได้') : __('จำนวน');
@endphp

<x-card :padded="false">
    <x-slot name="header">
        <div>
            <h2 class="text-sm font-semibold text-navy-900">{{ $title }}</h2>
            <p class="text-xs text-gray-500">{{ __('พิมพ์ SKU ชื่อ หรือรุ่นเพื่อค้นหาสินค้า') }}</p>
        </div>
        <button type="button" @click="addRow()"
                class="rounded-md border border-aqua-200 bg-aqua-50 px-3 py-1.5 text-xs font-medium text-aqua-700 transition hover:bg-aqua-100">
            {{ __('+ เพิ่มบรรทัด') }}
        </button>
    </x-slot>

    <div class="space-y-3 p-4 sm:p-5">
        <template x-for="(row, index) in rows" :key="index">
            <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-3">
                <div class="grid gap-3 lg:grid-cols-12">

                    {{-- ── ค้นหาสินค้า ── --}}
                    <div class="relative lg:col-span-5">
                        <label class="mb-1 block text-xs text-gray-600" :for="`line-search-${index}`">{{ __('สินค้า') }}</label>

                        <input type="text"
                               :id="`line-search-${index}`"
                               x-model="row.search"
                               @input="onSearchInput(row)"
                               @focus="row.open = true"
                               @click.outside="row.open = false"
                               autocomplete="off"
                               placeholder="{{ __('พิมพ์ SKU หรือชื่อสินค้า') }}"
                               class="form-input-base text-sm"
                               :class="row.product_id ? '' : 'border-amber-300'">

                        <input type="hidden" :name="`items[${index}][product_id]`" :value="row.product_id">

                        <ul x-show="row.open" x-cloak x-transition.opacity
                            class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                            <template x-for="product in matches(row)" :key="product.id">
                                <li>
                                    <button type="button" @click="choose(row, product)"
                                            class="block w-full px-3 py-2 text-start text-sm transition hover:bg-aqua-50">
                                        <span class="tabular font-medium text-navy-900" x-text="product.sku"></span>
                                        <span class="block truncate text-xs text-gray-500" x-text="product.name"></span>
                                    </button>
                                </li>
                            </template>
                            <li x-show="matches(row).length === 0" class="px-3 py-2 text-sm text-gray-400">
                                {{ __('ไม่พบสินค้า') }}
                            </li>
                        </ul>
                    </div>

                    {{-- ── จำนวน ── --}}
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs text-gray-600" :for="`line-qty-${index}`">{{ $qtyLabel }}</label>
                        <div class="flex items-center gap-1">
                            <input type="number" step="0.001" min="{{ $qtyField === 'qty_counted' ? '0' : '0.001' }}"
                                   :id="`line-qty-${index}`"
                                   x-model="row.{{ $qtyField }}"
                                   :name="`items[${index}][{{ $qtyField }}]`"
                                   class="form-input-base tabular text-sm">
                            <span class="shrink-0 text-xs text-gray-400" x-text="row.uom"></span>
                        </div>
                    </div>

                    @if ($showCost)
                        <div class="lg:col-span-2">
                            <label class="mb-1 block text-xs text-gray-600" :for="`line-cost-${index}`">{{ __('ราคาทุน/หน่วย') }}</label>
                            <input type="number" step="0.01" min="0"
                                   :id="`line-cost-${index}`"
                                   x-model="row.unit_cost"
                                   :name="`items[${index}][unit_cost]`"
                                   class="form-input-base tabular text-sm">
                        </div>
                    @endif

                    <div class="{{ $showCost ? 'lg:col-span-2' : 'lg:col-span-4' }}">
                        <label class="mb-1 block text-xs text-gray-600" :for="`line-lot-${index}`">{{ __('Lot') }}</label>
                        <input type="text"
                               :id="`line-lot-${index}`"
                               x-model="row.lot_no"
                               :name="`items[${index}][lot_no]`"
                               class="form-input-base text-sm">
                    </div>

                    <div class="flex items-end justify-end lg:col-span-1">
                        <button type="button" @click="removeRow(index)"
                                class="rounded-md p-2 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600"
                                aria-label="{{ __('ลบบรรทัด') }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                @if ($showSerial)
                    {{-- ช่อง serial โผล่เฉพาะสินค้าที่ติดตาม serial เพื่อไม่ให้ฟอร์มรก --}}
                    <div x-show="row.is_serialized" x-cloak class="mt-3 border-t border-gray-200 pt-3">
                        <label class="mb-1 flex flex-wrap items-center gap-2 text-xs text-gray-600" :for="`line-serial-${index}`">
                            {{ __('Serial (บรรทัดละ 1 ตัว)') }}
                            <span class="tabular rounded px-1.5 py-0.5 text-[11px]"
                                  :class="serialMismatch(row) ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                  x-text="`${serialCount(row)} / ${row.{{ $qtyField }} || 0}`"></span>
                        </label>

                        <textarea :id="`line-serial-${index}`"
                                  x-model="row.serial_numbers"
                                  :name="`items[${index}][serial_numbers]`"
                                  rows="3"
                                  placeholder="{{ __("SN00001\nSN00002") }}"
                                  class="form-input-base tabular text-sm"></textarea>

                        <p x-show="serialMismatch(row)" x-cloak class="mt-1 text-xs text-amber-700">
                            {{ __('จำนวน serial ต้องเท่ากับจำนวนสินค้าพอดี ไม่งั้นจะ post ไม่ผ่าน') }}
                        </p>
                    </div>
                @endif
            </div>
        </template>
    </div>

    <x-input-error :messages="$errors->get('items')" class="px-5 pb-4" />
</x-card>
