@php
    /** @var App\Models\GoodsReceipt $receipt */
    $isEdit = $receipt->exists;

    $rows = old('items', $isEdit
        ? $receipt->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'search' => $item->product->sku . ' — ' . $item->product->name_th,
            'uom' => $item->product->uom->label(),
            'is_serialized' => (bool) $item->product->is_serialized,
            'qty' => (string) $item->qty,
            'unit_cost' => (string) $item->unit_cost,
            'lot_no' => $item->lot_no,
            'serial_numbers' => implode("\n", $item->serial_numbers ?? []),
            'open' => false,
        ])->values()->all()
        : []);

    $blank = [
        'product_id' => '', 'search' => '', 'uom' => '', 'is_serialized' => false,
        'qty' => '', 'unit_cost' => '', 'lot_no' => '', 'serial_numbers' => '', 'open' => false,
    ];
@endphp

<form method="POST"
      action="{{ $isEdit ? route('goods-receipts.update', $receipt) : route('goods-receipts.store') }}"
      x-data="stockLines({ products: @js($products), rows: @js($rows), blank: @js($blank) })"
      class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card :title="__('ข้อมูลใบรับสินค้า')">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form.select name="warehouse_id" :label="__('คลังที่รับเข้า')"
                           :options="$warehouses->mapWithKeys(fn ($w) => [$w->id => $w->code . ' — ' . $w->name])->all()"
                           :selected="$receipt->warehouse_id ?? $warehouses->firstWhere('is_default', true)?->id"
                           required />

            <x-form.select name="supplier_id" :label="__('ผู้ขาย')"
                           :options="$suppliers->pluck('name', 'id')->all()"
                           :selected="$receipt->supplier_id"
                           :placeholder="__('— ไม่ระบุ —')" />

            <x-form.input name="reference_no" :label="__('เลขที่อ้างอิงของผู้ขาย')" :value="$receipt->reference_no"
                          :help="__('เลขใบส่งของหรือ PO')" />

            <x-form.input name="received_date" :label="__('วันที่รับ')" type="date"
                          :value="optional($receipt->received_date)->format('Y-m-d') ?? now()->format('Y-m-d')" required />
        </div>
    </x-card>

    @include('partials._stock-line-editor', [
        'title' => __('รายการที่รับเข้า'),
        'showCost' => true,
        'showSerial' => true,
        'qtyField' => 'qty',
    ])

    <x-card :title="__('หมายเหตุ')">
        <x-form.textarea name="note" :label="__('หมายเหตุ')" :value="$receipt->note" rows="3" />
    </x-card>

    <div class="flex flex-wrap items-center justify-end gap-3">
        <p x-show="hasSerialProblem()" x-cloak class="me-auto text-sm text-amber-700">
            {{ __('มีบรรทัดที่จำนวน serial ไม่ตรงกับจำนวนสินค้า — บันทึกเป็นร่างได้ แต่จะ post ไม่ผ่าน') }}
        </p>

        <x-link-button :href="$isEdit ? route('goods-receipts.show', $receipt) : route('goods-receipts.index')" variant="secondary">
            {{ __('ยกเลิก') }}
        </x-link-button>

        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกเป็นร่าง') }}
        </button>
    </div>
</form>
