@php
    /** @var App\Models\StockAdjustment $adjustment */
    $isEdit = $adjustment->exists;

    $rows = old('items', $isEdit
        ? $adjustment->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'search' => $item->product->sku . ' — ' . $item->product->name_th,
            'uom' => $item->product->uom->label(),
            'is_serialized' => (bool) $item->product->is_serialized,
            'qty_counted' => (string) $item->qty_counted,
            'lot_no' => $item->lot_no,
            'open' => false,
        ])->values()->all()
        : []);

    $blank = [
        'product_id' => '', 'search' => '', 'uom' => '', 'is_serialized' => false,
        'qty_counted' => '', 'lot_no' => '', 'open' => false,
    ];
@endphp

<form method="POST"
      action="{{ $isEdit ? route('stock-adjustments.update', $adjustment) : route('stock-adjustments.store') }}"
      x-data="stockLines({ products: @js($products), rows: @js($rows), blank: @js($blank) })"
      class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card :title="__('ข้อมูลใบปรับปรุง')">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.select name="warehouse_id" :label="__('คลัง')"
                           :options="$warehouses->mapWithKeys(fn ($w) => [$w->id => $w->code . ' — ' . $w->name])->all()"
                           :selected="$adjustment->warehouse_id ?? $warehouses->firstWhere('is_default', true)?->id"
                           required />

            <x-form.select name="reason" :label="__('เหตุผล')" :options="$reasons"
                           :selected="$adjustment->reason?->value" required />

            <x-form.input name="adjusted_at" :label="__('วันที่ปรับปรุง')" type="date"
                          :value="optional($adjustment->adjusted_at)->format('Y-m-d') ?? now()->format('Y-m-d')" required />
        </div>
    </x-card>

    <div class="rounded-lg border border-aqua-200 bg-aqua-50 px-4 py-3 text-sm text-aqua-900">
        {{ __('กรอก "จำนวนที่นับได้" เป็นยอดจริงหน้างาน ระบบจะคำนวณผลต่างกับยอดในระบบให้เองตอน post') }}
    </div>

    @include('partials._stock-line-editor', [
        'title' => __('รายการที่ปรับปรุง'),
        'showCost' => false,
        'showSerial' => false,
        'qtyField' => 'qty_counted',
    ])

    <x-card :title="__('หมายเหตุ')">
        <x-form.textarea name="note" :label="__('หมายเหตุ')" :value="$adjustment->note" rows="3"
                         :help="__('ระบุสาเหตุให้ชัด เช่น ผลการตรวจนับประจำเดือน หรือของเสียหายจากน้ำรั่ว')" />
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="$isEdit ? route('stock-adjustments.show', $adjustment) : route('stock-adjustments.index')" variant="secondary">
            {{ __('ยกเลิก') }}
        </x-link-button>
        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกเป็นร่าง') }}
        </button>
    </div>
</form>
