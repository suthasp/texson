<x-app-layout>
    <x-slot name="title">{{ $transfer->transfer_no }}</x-slot>

    <x-page-header :title="$transfer->transfer_no"
                   :subtitle="__('ใบโอนคลัง · :from → :to', ['from' => $transfer->fromWarehouse->code, 'to' => $transfer->toWarehouse->code])"
                   :back="route('stock-transfers.index')">
        <x-slot name="actions">
            @can('update', $transfer)
                <x-link-button :href="route('stock-transfers.edit', $transfer)" variant="secondary">{{ __('แก้ไข') }}</x-link-button>
            @endcan

            @can('post', $transfer)
                <form method="POST" action="{{ route('stock-transfers.post', $transfer) }}"
                      onsubmit="return confirm(@js(__('โอนตามใบนี้เลยไหม? หลัง post แล้วแก้ไขไม่ได้อีก')))">
                    @csrf
                    <button type="submit" class="rounded-md bg-aqua-400 px-4 py-2 text-sm font-medium text-navy-900 transition hover:bg-aqua-300">
                        {{ __('ยืนยันการโอน') }}
                    </button>
                </form>
            @endcan

            @can('delete', $transfer)
                <x-delete-button :action="route('stock-transfers.destroy', $transfer)"
                                 :label="__('ยกเลิกใบ')"
                                 :confirm="__('ยืนยันการยกเลิกใบ :no?', ['no' => $transfer->transfer_no])" />
            @endcan
        </x-slot>
    </x-page-header>

    @if ($transfer->status === App\Enums\StockDocumentStatus::Draft)
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
            {{ __('ใบนี้ยังเป็นร่าง — ยังไม่กระทบยอดสต็อกทั้งสองคลัง') }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('รายการที่โอน')" :padded="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="table-head-cell">{{ __('SKU') }}</th>
                                <th scope="col" class="table-head-cell">{{ __('สินค้า') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('จำนวน') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($transfer->items as $item)
                                <tr>
                                    <td class="table-cell-base tabular font-medium text-navy-900">{{ $item->product->sku }}</td>
                                    <td class="table-cell-base">
                                        <p class="max-w-xs truncate">{{ $item->product->name_th }}</p>
                                        @if (filled($item->serial_numbers))
                                            <p class="tabular mt-1 text-xs text-gray-500">{{ __('Serial') }}: {{ implode(', ', $item->serial_numbers) }}</p>
                                        @endif
                                    </td>
                                    <td class="table-cell-base tabular text-end">{{ number_format((float) $item->qty, 3) }} {{ $item->product->uom->label() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($transfer->movements->isNotEmpty())
                <x-card :title="__('รายการที่เขียนลง ledger')" :padded="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="table-head-cell">{{ __('สินค้า') }}</th>
                                    <th scope="col" class="table-head-cell">{{ __('คลัง') }}</th>
                                    <th scope="col" class="table-head-cell">{{ __('ประเภท') }}</th>
                                    <th scope="col" class="table-head-cell text-end">{{ __('จำนวน') }}</th>
                                    <th scope="col" class="table-head-cell text-end">{{ __('ยอดหลังรายการ') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($transfer->movements as $movement)
                                    @php $qty = (float) $movement->qty; @endphp
                                    <tr>
                                        <td class="table-cell-base tabular">{{ $movement->product->sku }}</td>
                                        <td class="table-cell-base"><x-badge color="navy">{{ $movement->warehouse->code }}</x-badge></td>
                                        <td class="table-cell-base"><x-badge :color="$movement->type->badgeColor()">{{ $movement->type->label() }}</x-badge></td>
                                        <td class="table-cell-base tabular text-end {{ $qty < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                                            {{ $qty > 0 ? '+' : '' }}{{ number_format($qty, 3) }}
                                        </td>
                                        <td class="table-cell-base tabular text-end">{{ number_format((float) $movement->balance_after, 3) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        <x-card :title="__('ข้อมูลเอกสาร')">
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('สถานะ') }}</dt>
                    <dd class="mt-0.5"><x-badge :color="$transfer->status->badgeColor()">{{ $transfer->status->label() }}</x-badge></dd>
                </div>
                @foreach ([
                    __('วันที่โอน') => $transfer->transfer_date->translatedFormat('d M Y'),
                    __('คลังต้นทาง') => $transfer->fromWarehouse->code . ' — ' . $transfer->fromWarehouse->name,
                    __('คลังปลายทาง') => $transfer->toWarehouse->code . ' — ' . $transfer->toWarehouse->name,
                    __('ผู้สร้าง') => $transfer->creator?->name,
                    __('ผู้ยืนยันการโอน') => $transfer->poster?->name,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs text-gray-500">{{ $label }}</dt>
                        <dd class="text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($transfer->note)
                <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-3 text-sm text-gray-600">{{ $transfer->note }}</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
