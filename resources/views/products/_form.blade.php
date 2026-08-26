@php
    /** @var App\Models\Product $product */
    $isEdit = $product->exists;

    $categoryOptions = $categories->mapWithKeys(fn ($c) => [$c->id => $c->fullName()])->all();
    $brandOptions = $brands->pluck('name', 'id')->all();

    // spec เก็บใน DB เป็น object — แปลงกลับเป็นแถว key/value ให้ฟอร์มแก้ไขได้
    $specRows = old('spec', collect($product->spec ?? [])
        ->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])
        ->values()
        ->all());

    $supplierRows = old('suppliers', $product->relationLoaded('suppliers')
        ? $product->suppliers->map(fn ($s) => [
            'supplier_id' => $s->id,
            'supplier_sku' => $s->pivot->supplier_sku,
            'cost_price' => $s->pivot->cost_price,
            'lead_time_days' => $s->pivot->lead_time_days,
            'is_preferred' => (bool) $s->pivot->is_preferred,
        ])->values()->all()
        : []);
@endphp

<form method="POST" action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card :title="__('ข้อมูลสินค้า')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-form.input name="sku" :label="__('รหัสสินค้า (SKU)')" :value="$product->sku" required
                          :help="__('เช่น UPS-APC-SRT10K')" />
            <x-form.input name="name_th" :label="__('ชื่อสินค้า (ไทย)')" :value="$product->name_th" required class="lg:col-span-2" />
            <x-form.input name="name_en" :label="__('ชื่อสินค้า (อังกฤษ)')" :value="$product->name_en" class="lg:col-span-2" />

            <x-form.select name="category_id" :label="__('หมวดหมู่')" :options="$categoryOptions"
                           :selected="$product->category_id" :placeholder="__('— เลือกหมวดหมู่ —')" required />
            <x-form.select name="brand_id" :label="__('ยี่ห้อ')" :options="$brandOptions"
                           :selected="$product->brand_id" :placeholder="__('— ไม่ระบุ —')" />

            <x-form.input name="model" :label="__('รุ่น')" :value="$product->model" />
            <x-form.input name="part_number" :label="__('Part Number')" :value="$product->part_number" />
            <x-form.select name="uom" :label="__('หน่วยนับ')" :options="$uoms" :selected="$product->uom?->value" required />
        </div>
    </x-card>

    <x-card :title="__('ราคา')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.input name="cost_price" :label="__('ราคาทุน')" :value="$product->cost_price ?? '0.00'"
                          type="number" step="0.01" min="0" required class="tabular"
                          :help="__('ใช้คำนวณ margin — ไม่แสดงในใบเสนอราคา')" />
            <x-form.input name="list_price" :label="__('ราคามาตรฐาน')" :value="$product->list_price ?? '0.00'"
                          type="number" step="0.01" min="0" required />
            <x-form.input name="dealer_price" :label="__('ราคาตัวแทนจำหน่าย')" :value="$product->dealer_price ?? '0.00'"
                          type="number" step="0.01" min="0" required />
            <x-form.input name="project_price" :label="__('ราคาโครงการ')" :value="$product->project_price ?? '0.00'"
                          type="number" step="0.01" min="0" required />
        </div>
    </x-card>

    <x-card :title="__('การควบคุมสต็อก')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.input name="min_stock" :label="__('สต็อกขั้นต่ำ')" :value="$product->min_stock ?? '0'"
                          type="number" step="0.001" min="0" required
                          :help="__('ต่ำกว่านี้จะขึ้นเตือนใน Low Stock')" />
            <x-form.input name="reorder_qty" :label="__('จำนวนสั่งซื้อซ้ำ')" :value="$product->reorder_qty ?? '0'"
                          type="number" step="0.001" min="0" required />
            <x-form.input name="lead_time_days" :label="__('ระยะเวลาสั่งของ (วัน)')" :value="$product->lead_time_days ?? 0"
                          type="number" min="0" max="3650" required />
            <x-form.input name="warranty_months" :label="__('ระยะประกัน (เดือน)')" :value="$product->warranty_months ?? 0"
                          type="number" min="0" max="600" required
                          :help="__('ใช้คำนวณวันหมดประกันตอนส่งของ')" />

            <div class="space-y-3 sm:col-span-2 lg:col-span-4">
                <x-form.checkbox name="is_serialized" :label="__('ติดตาม Serial Number รายชิ้น')" :checked="$product->is_serialized"
                                 :help="__('เปิดสำหรับ UPS และแบตเตอรี่ — ตอนส่งของจะบังคับเลือก serial ให้ครบจำนวน')" />
                <x-form.checkbox name="track_lot" :label="__('ติดตาม Lot')" :checked="$product->track_lot" />
                <x-form.checkbox name="is_active" :label="__('เปิดใช้งานสินค้ารายการนี้')" :checked="$product->is_active ?? true" />
            </div>
        </div>
    </x-card>

    {{-- ── สเปกทางเทคนิคแบบ key/value ── --}}
    <x-card x-data="{ rows: @js($specRows) }">
        <x-slot name="header">
            <div>
                <h2 class="text-sm font-semibold text-navy-900">{{ __('สเปกทางเทคนิค') }}</h2>
                <p class="text-xs text-gray-500">{{ __('เช่น kva = 10, phase = 3P, voltage = 380') }}</p>
            </div>
            <button type="button" @click="rows.push({ key: '', value: '' })"
                    class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('+ เพิ่มบรรทัด') }}</button>
        </x-slot>

        <div class="space-y-2">
            <template x-for="(row, index) in rows" :key="index">
                <div class="flex items-center gap-2">
                    <input type="text" x-model="row.key" :name="`spec[${index}][key]`"
                           placeholder="{{ __('ชื่อสเปก') }}" class="form-input-base text-sm sm:max-w-xs">
                    <input type="text" x-model="row.value" :name="`spec[${index}][value]`"
                           placeholder="{{ __('ค่า') }}" class="form-input-base text-sm">
                    <button type="button" @click="rows.splice(index, 1)"
                            class="shrink-0 rounded-md p-2 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600"
                            aria-label="{{ __('ลบบรรทัด') }}">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </template>

            <p x-show="rows.length === 0" class="py-2 text-sm text-gray-400">{{ __('ยังไม่มีสเปก') }}</p>
        </div>
    </x-card>

    {{-- ── ผู้ขายที่ซื้อสินค้านี้ได้ ── --}}
    <x-card x-data="{ rows: @js($supplierRows) }">
        <x-slot name="header">
            <div>
                <h2 class="text-sm font-semibold text-navy-900">{{ __('ผู้ขาย') }}</h2>
                <p class="text-xs text-gray-500">{{ __('ผู้ขายหลักตั้งได้รายเดียว — ถ้าเลือกหลายรายระบบจะเก็บรายแรก') }}</p>
            </div>
            <button type="button" @click="rows.push({ supplier_id: '', supplier_sku: '', cost_price: '', lead_time_days: '', is_preferred: false })"
                    class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('+ เพิ่มผู้ขาย') }}</button>
        </x-slot>

        <div class="space-y-3">
            <template x-for="(row, index) in rows" :key="index">
                <div class="grid items-end gap-2 rounded-lg border border-gray-100 bg-gray-50/60 p-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="mb-1 block text-xs text-gray-600">{{ __('ผู้ขาย') }}</label>
                        <select x-model="row.supplier_id" :name="`suppliers[${index}][supplier_id]`" class="form-input-base text-sm">
                            <option value="">{{ __('— เลือก —') }}</option>
                            @foreach ($allSuppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs text-gray-600">{{ __('รหัสของผู้ขาย') }}</label>
                        <input type="text" x-model="row.supplier_sku" :name="`suppliers[${index}][supplier_sku]`" class="form-input-base text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs text-gray-600">{{ __('ราคาทุน') }}</label>
                        <input type="number" step="0.01" min="0" x-model="row.cost_price" :name="`suppliers[${index}][cost_price]`" class="form-input-base tabular text-sm">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs text-gray-600">{{ __('Lead time (วัน)') }}</label>
                        <input type="number" min="0" x-model="row.lead_time_days" :name="`suppliers[${index}][lead_time_days]`" class="form-input-base tabular text-sm">
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" :name="`suppliers[${index}][is_preferred]`" value="0">
                            <input type="checkbox" x-model="row.is_preferred" :name="`suppliers[${index}][is_preferred]`" value="1"
                                   class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                            {{ __('หลัก') }}
                        </label>

                        <button type="button" @click="rows.splice(index, 1)"
                                class="rounded-md p-2 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600"
                                aria-label="{{ __('ลบผู้ขาย') }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </template>

            <p x-show="rows.length === 0" class="py-2 text-sm text-gray-400">{{ __('ยังไม่ได้ผูกผู้ขาย') }}</p>
        </div>
    </x-card>

    <x-card :title="__('รายละเอียดเพิ่มเติม')">
        <x-form.textarea name="description" :label="__('รายละเอียด')" :value="$product->description" rows="4" />
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="$isEdit ? route('products.show', $product) : route('products.index')" variant="secondary">
            {{ __('ยกเลิก') }}
        </x-link-button>

        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกสินค้า') }}
        </button>
    </div>
</form>
