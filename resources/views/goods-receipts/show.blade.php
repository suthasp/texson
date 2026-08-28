<x-app-layout>
    <x-slot name="title">{{ $receipt->receipt_no }}</x-slot>

    <x-page-header :title="$receipt->receipt_no"
                   :subtitle="__('ใบรับสินค้า · :date', ['date' => $receipt->received_date->translatedFormat('d M Y')])"
                   :back="route('goods-receipts.index')">
        <x-slot name="actions">
            @can('update', $receipt)
                <x-link-button :href="route('goods-receipts.edit', $receipt)" variant="secondary">{{ __('แก้ไข') }}</x-link-button>
            @endcan

            @can('post', $receipt)
                <form method="POST" action="{{ route('goods-receipts.post', $receipt) }}"
                      x-data
                      @submit.prevent="confirm(@js(__('บันทึกใบนี้เข้าสต็อกเลยไหม? หลัง post แล้วแก้ไขไม่ได้อีก'))) && $el.submit()">
                    @csrf
                    <button type="submit" class="rounded-md bg-aqua-400 px-4 py-2 text-sm font-medium text-navy-900 transition hover:bg-aqua-300">
                        {{ __('บันทึกเข้าสต็อก') }}
                    </button>
                </form>
            @endcan

            @can('delete', $receipt)
                <x-delete-button :action="route('goods-receipts.destroy', $receipt)"
                                 :label="__('ยกเลิกใบ')"
                                 :confirm="__('ยืนยันการยกเลิกใบ :no?', ['no' => $receipt->receipt_no])" />
            @endcan
        </x-slot>
    </x-page-header>

    @if ($receipt->status === App\Enums\StockDocumentStatus::Draft)
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
            {{ __('ใบนี้ยังเป็นร่าง — ยังไม่กระทบยอดสต็อก กด "บันทึกเข้าสต็อก" เมื่อตรวจของครบแล้ว') }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('รายการที่รับ')" :padded="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="table-head-cell">{{ __('SKU') }}</th>
                                <th scope="col" class="table-head-cell">{{ __('สินค้า') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('จำนวน') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('ราคาทุน/หน่วย') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('รวม') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($receipt->items as $item)
                                <tr>
                                    <td class="table-cell-base tabular font-medium text-navy-900">{{ $item->product->sku }}</td>
                                    <td class="table-cell-base">
                                        <p class="max-w-xs truncate">{{ $item->product->name_th }}</p>
                                        @if ($item->lot_no)
                                            <p class="tabular text-xs text-gray-400">Lot: {{ $item->lot_no }}</p>
                                        @endif
                                        @if (filled($item->serial_numbers))
                                            <p class="tabular mt-1 text-xs text-gray-500">
                                                {{ __('Serial') }}: {{ implode(', ', array_slice($item->serial_numbers, 0, 5)) }}
                                                @if (count($item->serial_numbers) > 5)
                                                    {{ __('และอีก :count ตัว', ['count' => count($item->serial_numbers) - 5]) }}
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                    <td class="table-cell-base tabular text-end">{{ number_format((float) $item->qty, 3) }} {{ $item->product->uom->label() }}</td>
                                    <td class="table-cell-base tabular text-end">{{ number_format((float) $item->unit_cost, 2) }}</td>
                                    <td class="table-cell-base tabular text-end font-medium">{{ number_format((float) $item->lineTotal(), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="4" class="table-cell-base text-end font-medium">{{ __('มูลค่ารวม') }}</td>
                                <td class="table-cell-base tabular text-end font-semibold text-navy-900">
                                    {{ number_format((float) $receipt->items->sum(fn ($i) => (float) $i->lineTotal()), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>

            @if ($receipt->movements->isNotEmpty())
                <x-card :title="__('รายการที่เขียนลง ledger')" :padded="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="table-head-cell">{{ __('วันเวลา') }}</th>
                                    <th scope="col" class="table-head-cell">{{ __('สินค้า') }}</th>
                                    <th scope="col" class="table-head-cell text-end">{{ __('จำนวน') }}</th>
                                    <th scope="col" class="table-head-cell text-end">{{ __('ยอดหลังรายการ') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($receipt->movements as $movement)
                                    <tr>
                                        <td class="table-cell-base tabular text-xs text-gray-500">{{ $movement->moved_at->translatedFormat('d M Y H:i') }}</td>
                                        <td class="table-cell-base tabular">{{ $movement->product->sku }}</td>
                                        <td class="table-cell-base tabular text-end text-emerald-700">+{{ number_format((float) $movement->qty, 3) }}</td>
                                        <td class="table-cell-base tabular text-end">{{ number_format((float) $movement->balance_after, 3) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="space-y-4">
            <x-card :title="__('ข้อมูลเอกสาร')">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('สถานะ') }}</dt>
                        <dd class="mt-0.5"><x-badge :color="$receipt->status->badgeColor()">{{ $receipt->status->label() }}</x-badge></dd>
                    </div>
                    @foreach ([
                        __('ผู้ขาย') => $receipt->supplier?->name,
                        __('คลังที่รับเข้า') => $receipt->warehouse->code . ' — ' . $receipt->warehouse->name,
                        __('เลขที่อ้างอิง') => $receipt->reference_no,
                        __('ผู้สร้าง') => $receipt->creator?->name,
                        __('ผู้บันทึกเข้าสต็อก') => $receipt->poster?->name,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-gray-500">{{ $label }}</dt>
                            <dd class="text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach

                    @if ($receipt->posted_at)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('บันทึกเข้าสต็อกเมื่อ') }}</dt>
                            <dd class="tabular text-gray-900">{{ $receipt->posted_at->translatedFormat('d M Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @if ($receipt->note)
                <x-card :title="__('หมายเหตุ')">
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ $receipt->note }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
