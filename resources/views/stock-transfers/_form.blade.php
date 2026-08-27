@php
    /** @var App\Models\StockTransfer $transfer */
    $isEdit = $transfer->exists;

    $rows = old('items', $isEdit
        ? $transfer->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'search' => $item->product->sku . ' — ' . $item->product->name_th,
            'uom' => $item->product->uom->label(),
            'is_serialized' => (bool) $item->product->is_serialized,
            'qty' => (string) $item->qty,
            'lot_no' => $item->lot_no,
            'serial_numbers' => implode("\n", $item->serial_numbers ?? []),
            'open' => false,
        ])->values()->all()
        : []);

    $blank = [
        'product_id' => '', 'search' => '', 'uom' => '', 'is_serialized' => false,
        'qty' => '', 'lot_no' => '', 'serial_numbers' => '', 'open' => false,
    ];

    $warehouseOptions = $warehouses->mapWithKeys(fn ($w) => [$w->id => $w->code . ' — ' . $w->name])->all();
@endphp

<form method="POST"
      action="{{ $isEdit ? route('stock-transfers.update', $transfer) : route('stock-transfers.store') }}"
      x-data="stockLines({ products: @js($products), rows: @js($rows), blank: @js($blank) })"
      class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card :title="__('ข้อมูลใบโอน')">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.select name="from_warehouse_id" :label="__('คลังต้นทาง')" :options="$warehouseOptions"
                           :selected="$transfer->from_warehouse_id" required />
            <x-form.select name="to_warehouse_id" :label="__('คลังปลายทาง')" :options="$warehouseOptions"
                           :selected="$transfer->to_warehouse_id" required />
            <x-form.input name="transfer_date" :label="__('วันที่โอน')" type="date"
                          :value="optional($transfer->transfer_date)->format('Y-m-d') ?? now()->format('Y-m-d')" required />
        </div>
    </x-card>

    @include('partials._stock-line-editor', [
        'title' => __('รายการที่โอน'),
        'showCost' => false,
        'showSerial' => true,
        'qtyField' => 'qty',
    ])

    <x-card :title="__('หมายเหตุ')">
        <x-form.textarea name="note" :label="__('หมายเหตุ')" :value="$transfer->note" rows="3" />
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="$isEdit ? route('stock-transfers.show', $transfer) : route('stock-transfers.index')" variant="secondary">
            {{ __('ยกเลิก') }}
        </x-link-button>
        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('บันทึกเป็นร่าง') }}
        </button>
    </div>
</form>
