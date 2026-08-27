@php
    /** @var App\Models\Quotation $quotation */
    $isEdit = $quotation->exists;

    $rows = old('items', $isEdit
        ? $quotation->items->map(fn ($item) => [
            'item_type' => $item->item_type->value,
            'product_id' => $item->product_id,
            'search' => $item->product ? $item->product->sku . ' — ' . $item->product->name_th : '',
            'description' => $item->description,
            'uom' => $item->uom,
            'qty' => (string) $item->qty,
            'unit_price' => (string) $item->unit_price,
            'cost_price' => (string) $item->cost_snapshot,
            'discount_percent' => (string) $item->discount_percent,
            'discount_amount' => (string) $item->discount_amount,
            'lead_time_days' => $item->lead_time_days,
            'available' => $item->product?->totalAvailable(),
            'price_overridden' => true,
            'open' => false,
        ])->values()->all()
        : []);

    $blank = [
        'item_type' => 'product', 'product_id' => '', 'search' => '', 'description' => '',
        'uom' => '', 'qty' => '1', 'unit_price' => '', 'cost_price' => '0',
        'discount_percent' => '', 'discount_amount' => '', 'lead_time_days' => null,
        'available' => null, 'price_overridden' => false, 'open' => false,
    ];
@endphp

<form method="POST" x-ref="form"
      action="{{ $isEdit ? route('quotations.update', $quotation) : route('quotations.store') }}"
      x-data="quotationLines({
          products: @js($products),
          customers: @js($customers),
          rows: @js($rows),
          blank: @js($blank),
          customerId: @js(old('customer_id', $quotation->customer_id)),
          priceTier: @js(old('price_tier', $quotation->price_tier?->value ?? 'standard')),
          vatRate: @js(old('vat_rate', (string) ($quotation->vat_rate ?? '7.00'))),
          discountAmount: @js(old('discount_amount', (string) ($quotation->discount_amount ?? '0'))),
          minMargin: @js($minMargin ?? 10),
      })"
      x-init="contactId = @js((string) old('customer_contact_id', $quotation->customer_contact_id)); siteId = @js((string) old('customer_site_id', $quotation->customer_site_id))"
      class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- ── หัวใบ ── --}}
    <x-card :title="__('ข้อมูลใบเสนอราคา')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="space-y-1">
                <label for="customer_id" class="block text-sm font-medium text-gray-700">
                    {{ __('ลูกค้า') }} <span class="text-rose-600" aria-hidden="true">*</span>
                </label>
                <select id="customer_id" name="customer_id" required
                        x-model="customerId" @change="onCustomerChange()"
                        class="form-input-base {{ $errors->has('customer_id') ? 'border-rose-400' : '' }}">
                    <option value="">{{ __('— เลือกลูกค้า —') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer['id'] }}">{{ $customer['code'] }} — {{ $customer['name'] }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('customer_id')" />
            </div>

            <div class="space-y-1">
                <label for="customer_contact_id" class="block text-sm font-medium text-gray-700">{{ __('ผู้ติดต่อ') }}</label>
                <select id="customer_contact_id" name="customer_contact_id" x-model="contactId" class="form-input-base">
                    <option value="">{{ __('— ไม่ระบุ —') }}</option>
                    <template x-for="contact in contacts" :key="contact.id">
                        <option :value="contact.id" x-text="contact.name"></option>
                    </template>
                </select>
                <x-input-error :messages="$errors->get('customer_contact_id')" />
            </div>

            <div class="space-y-1">
                <label for="customer_site_id" class="block text-sm font-medium text-gray-700">{{ __('หน้างาน') }}</label>
                <select id="customer_site_id" name="customer_site_id" x-model="siteId" class="form-input-base">
                    <option value="">{{ __('— ไม่ระบุ —') }}</option>
                    <template x-for="site in sites" :key="site.id">
                        <option :value="site.id" x-text="site.name"></option>
                    </template>
                </select>
                <p class="text-xs text-gray-500">{{ __('ระบุไว้เพื่อให้งาน PM ใน Phase 2 อ้างถึงได้') }}</p>
                <x-input-error :messages="$errors->get('customer_site_id')" />
            </div>

            <div class="space-y-1">
                <label for="price_tier" class="block text-sm font-medium text-gray-700">{{ __('ระดับราคา') }}</label>
                <select id="price_tier" name="price_tier" x-model="priceTier" @change="applyTierPrices()" class="form-input-base">
                    @foreach ($priceTiers as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500">{{ __('เปลี่ยนแล้วราคาที่ยังไม่ถูกแก้มือจะอัปเดตตาม') }}</p>
                <x-input-error :messages="$errors->get('price_tier')" />
            </div>

            <x-form.input name="issue_date" :label="__('วันที่ออกใบ')" type="date"
                          :value="optional($quotation->issue_date)->format('Y-m-d') ?? now()->format('Y-m-d')" required />

            <x-form.input name="valid_until" :label="__('ยืนราคาถึง')" type="date"
                          :value="optional($quotation->valid_until)->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d')" required />

            <div class="space-y-1">
                <label for="vat_rate" class="block text-sm font-medium text-gray-700">{{ __('อัตรา VAT (%)') }}</label>
                <input id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" required
                       x-model="vatRate" class="form-input-base tabular">
                <x-input-error :messages="$errors->get('vat_rate')" />
            </div>

            <div class="space-y-1">
                <label for="discount_amount" class="block text-sm font-medium text-gray-700">{{ __('ส่วนลดท้ายบิล (บาท)') }}</label>
                <input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0"
                       x-model="discountAmount" class="form-input-base tabular">
                <x-input-error :messages="$errors->get('discount_amount')" />
            </div>
        </div>
    </x-card>

    <div class="grid gap-4 xl:grid-cols-4">
        {{-- ── รายการ ── --}}
        <div class="space-y-4 xl:col-span-3">
            <x-card :padded="false">
                <x-slot name="header">
                    <div>
                        <h2 class="text-sm font-semibold text-navy-900">{{ __('รายการ') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('พิมพ์ SKU ชื่อ หรือรุ่นเพื่อค้นหาสินค้า · บรรทัดค่าแรงและค่าขนส่งพิมพ์อิสระได้') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($itemTypes as $value => $label)
                            <button type="button" @click="addRow(@js($value))"
                                    class="rounded-md border border-aqua-200 bg-aqua-50 px-2.5 py-1.5 text-xs font-medium text-aqua-700 transition hover:bg-aqua-100">
                                + {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </x-slot>

                <div class="space-y-3 p-4 sm:p-5">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-3">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="tabular flex h-5 w-5 items-center justify-center rounded bg-navy-100 text-[11px] font-semibold text-navy-800"
                                          x-text="index + 1"></span>
                                    <select x-model="row.item_type" :name="`items[${index}][item_type]`"
                                            class="rounded-md border-gray-300 py-1 text-xs focus:border-aqua-400 focus:ring-aqua-400">
                                        @foreach ($itemTypes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="flex items-center gap-0.5">
                                    <button type="button" @click="moveRow(index, -1)" :disabled="index === 0"
                                            class="rounded p-1.5 text-gray-400 transition hover:bg-gray-200 disabled:opacity-30"
                                            aria-label="{{ __('เลื่อนขึ้น') }}">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                        </svg>
                                    </button>
                                    <button type="button" @click="moveRow(index, 1)" :disabled="index === rows.length - 1"
                                            class="rounded p-1.5 text-gray-400 transition hover:bg-gray-200 disabled:opacity-30"
                                            aria-label="{{ __('เลื่อนลง') }}">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                    <button type="button" @click="removeRow(index)"
                                            class="rounded p-1.5 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600"
                                            aria-label="{{ __('ลบบรรทัด') }}">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- ค้นหาสินค้า: เฉพาะบรรทัดชนิดสินค้า --}}
                            <div class="grid gap-3 lg:grid-cols-12">
                                <div class="relative lg:col-span-5" x-show="row.item_type === 'product'" x-cloak>
                                    <label class="mb-1 block text-xs text-gray-600" :for="`q-search-${index}`">{{ __('สินค้า') }}</label>
                                    <input type="text" :id="`q-search-${index}`"
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
                                                    <span class="flex items-baseline justify-between gap-2">
                                                        <span class="tabular font-medium text-navy-900" x-text="product.sku"></span>
                                                        <span class="tabular shrink-0 text-[11px] text-gray-500"
                                                              x-text="`{{ __('คงเหลือ') }} ${Number(product.available).toLocaleString()}`"></span>
                                                    </span>
                                                    <span class="block truncate text-xs text-gray-500" x-text="product.name"></span>
                                                </button>
                                            </li>
                                        </template>
                                        <li x-show="matches(row).length === 0" class="px-3 py-2 text-sm text-gray-400">{{ __('ไม่พบสินค้า') }}</li>
                                    </ul>
                                </div>

                                <div :class="row.item_type === 'product' ? 'lg:col-span-7' : 'lg:col-span-12'">
                                    <label class="mb-1 block text-xs text-gray-600" :for="`q-desc-${index}`">{{ __('รายละเอียด') }}</label>
                                    <input type="text" :id="`q-desc-${index}`"
                                           x-model="row.description" :name="`items[${index}][description]`"
                                           class="form-input-base text-sm">
                                </div>
                            </div>

                            {{-- ตัวเลข: ซ่อนทั้งแถบสำหรับบรรทัดข้อความ --}}
                            <div class="mt-3 grid gap-3 lg:grid-cols-12" x-show="isMonetary(row)" x-cloak>
                                <div class="lg:col-span-2">
                                    <label class="mb-1 block text-xs text-gray-600" :for="`q-qty-${index}`">{{ __('จำนวน') }}</label>
                                    <div class="flex items-center gap-1">
                                        <input type="number" step="0.001" min="0" :id="`q-qty-${index}`"
                                               x-model="row.qty" :name="`items[${index}][qty]`"
                                               class="form-input-base tabular text-sm"
                                               :class="isOverStock(row) ? 'border-amber-400' : ''">
                                        <input type="text" x-model="row.uom" :name="`items[${index}][uom]`"
                                               class="w-16 shrink-0 rounded-md border-gray-300 py-1.5 text-xs focus:border-aqua-400 focus:ring-aqua-400"
                                               placeholder="{{ __('หน่วย') }}">
                                    </div>
                                    <p x-show="isOverStock(row)" x-cloak class="mt-1 text-[11px] text-amber-700"
                                       x-text="`{{ __('เกินยอดพร้อมขาย') }} (${Number(row.available).toLocaleString()})`"></p>
                                </div>

                                <div class="lg:col-span-2">
                                    <label class="mb-1 block text-xs text-gray-600" :for="`q-price-${index}`">{{ __('ราคา/หน่วย') }}</label>
                                    <input type="number" step="0.01" min="0" :id="`q-price-${index}`"
                                           x-model="row.unit_price" @input="onPriceInput(row)"
                                           :name="`items[${index}][unit_price]`"
                                           class="form-input-base tabular text-sm">
                                </div>

                                <div class="lg:col-span-2">
                                    <label class="mb-1 block text-xs text-gray-600" :for="`q-disc-${index}`">{{ __('ส่วนลด (%)') }}</label>
                                    <input type="number" step="0.01" min="0" max="100" :id="`q-disc-${index}`"
                                           x-model="row.discount_percent" :name="`items[${index}][discount_percent]`"
                                           class="form-input-base tabular text-sm">
                                </div>

                                <div class="lg:col-span-2">
                                    <label class="mb-1 block text-xs text-gray-600" :for="`q-lead-${index}`">{{ __('ส่งของ (วัน)') }}</label>
                                    <input type="number" step="1" min="0" :id="`q-lead-${index}`"
                                           x-model="row.lead_time_days" :name="`items[${index}][lead_time_days]`"
                                           class="form-input-base tabular text-sm">
                                </div>

                                <div class="lg:col-span-4">
                                    <span class="mb-1 block text-xs text-gray-600">{{ __('จำนวนเงิน') }}</span>
                                    <div class="flex items-baseline justify-between gap-2 rounded-md bg-white px-3 py-2">
                                        <span class="tabular text-sm font-semibold text-navy-900" x-text="money(lineTotal(row))"></span>
                                        @if ($canSeeCost)
                                            <span class="tabular text-[11px]"
                                                  :class="isLowMargin(row) ? 'text-rose-600 font-semibold' : 'text-gray-500'"
                                                  x-show="lineMarginPercent(row) !== null"
                                                  x-text="`mg ${lineMarginPercent(row)}%`"></span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <x-input-error :messages="$errors->get('items')" class="px-5 pb-4" />
            </x-card>

            <x-card :title="__('เงื่อนไขและหมายเหตุ')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="payment_terms" :label="__('เงื่อนไขการชำระเงิน')" :value="$quotation->payment_terms" />
                    <x-form.input name="delivery_terms" :label="__('เงื่อนไขการส่งมอบ')" :value="$quotation->delivery_terms" />
                    <x-form.input name="lead_time_note" :label="__('ระยะเวลาส่งของ')" :value="$quotation->lead_time_note" class="sm:col-span-2" />
                    <x-form.textarea name="terms_and_conditions" :label="__('เงื่อนไขท้ายใบ (พิมพ์ลงใบเสนอราคา)')"
                                     :value="$quotation->terms_and_conditions" rows="4" class="sm:col-span-2" />
                    <x-form.textarea name="customer_note" :label="__('หมายเหตุถึงลูกค้า')" :value="$quotation->customer_note" rows="3" />
                    <x-form.textarea name="internal_note" :label="__('บันทึกภายใน (ลูกค้าไม่เห็น)')" :value="$quotation->internal_note" rows="3" />
                </div>
            </x-card>
        </div>

        {{-- ── สรุปยอดสด ── --}}
        <div class="xl:col-span-1">
            <div class="sticky top-20 space-y-4">
                <x-card :title="__('สรุปยอด')">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-600">{{ __('รวมเป็นเงิน') }}</dt>
                            <dd class="tabular font-medium" x-text="money(subtotal)"></dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-600">{{ __('ส่วนลดท้ายบิล') }}</dt>
                            <dd class="tabular text-rose-600" x-text="'-' + money(headerDiscount)"></dd>
                        </div>
                        <div class="flex justify-between gap-2 border-t border-gray-100 pt-2">
                            <dt class="text-gray-600">{{ __('หลังหักส่วนลด') }}</dt>
                            <dd class="tabular font-medium" x-text="money(afterDiscount)"></dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-600" x-text="`{{ __('ภาษีมูลค่าเพิ่ม') }} ${vatRate}%`"></dt>
                            <dd class="tabular" x-text="money(vatAmount)"></dd>
                        </div>
                        <div class="flex justify-between gap-2 border-t-2 border-navy-900 pt-2">
                            <dt class="font-semibold text-navy-900">{{ __('ยอดสุทธิ') }}</dt>
                            <dd class="tabular text-lg font-bold text-navy-900" x-text="money(grandTotal)"></dd>
                        </div>

                        <div x-show="withholdingBase > 0" x-cloak class="flex justify-between gap-2 border-t border-dashed border-gray-200 pt-2 text-xs text-gray-500">
                            <dt>{{ __('หัก ณ ที่จ่าย 3% (ข้อมูลประกอบ)') }}</dt>
                            <dd class="tabular" x-text="money(withholdingAmount)"></dd>
                        </div>
                    </dl>
                </x-card>

                @if ($canSeeCost)
                    <x-card :title="__('margin')">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600">{{ __('ต้นทุนรวม') }}</span>
                                <span class="tabular" x-text="money(costTotal)"></span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-gray-600">{{ __('กำไรขั้นต้น') }}</span>
                                <span class="tabular font-medium" x-text="money(marginAmount)"></span>
                            </div>
                            <div class="flex items-baseline justify-between gap-2 rounded-md px-2 py-1.5"
                                 :class="isMarginLow ? 'bg-rose-50' : 'bg-emerald-50'">
                                <span class="text-xs text-gray-600">{{ __('margin') }}</span>
                                <span class="tabular text-lg font-bold"
                                      :class="isMarginLow ? 'text-rose-700' : 'text-emerald-700'"
                                      x-text="marginPercent + '%'"></span>
                            </div>
                            <p x-show="isMarginLow" x-cloak class="text-xs text-rose-700">
                                {{ __('margin ต่ำกว่าเกณฑ์ — ใบนี้จะต้องผ่านการอนุมัติก่อนส่ง') }}
                            </p>
                            <div class="flex justify-between gap-2 border-t border-gray-100 pt-2 text-xs text-gray-500">
                                <span>{{ __('ส่วนลดรวม') }}</span>
                                <span class="tabular" x-text="totalDiscountPercent + '%'"></span>
                            </div>
                        </div>
                    </x-card>
                @endif

                <div class="space-y-2">
                    <button type="submit" class="w-full rounded-md bg-navy-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-navy-800">
                        {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกเป็นร่าง') }}
                    </button>
                    <x-link-button :href="$isEdit ? route('quotations.show', $quotation) : route('quotations.index')"
                                   variant="secondary" class="w-full justify-center">
                        {{ __('ยกเลิก') }}
                    </x-link-button>
                    <p class="text-center text-xs text-gray-400">{{ __('กด Ctrl+S เพื่อบันทึก') }}</p>
                </div>
            </div>
        </div>
    </div>
</form>
