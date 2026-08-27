<x-app-layout>
    <x-slot name="title">{{ $adjustment->adjust_no }}</x-slot>

    <x-page-header :title="$adjustment->adjust_no"
                   :subtitle="__('ใบปรับปรุงสต็อก · :reason', ['reason' => $adjustment->reason->label()])"
                   :back="route('stock-adjustments.index')">
        <x-slot name="actions">
            @can('update', $adjustment)
                <x-link-button :href="route('stock-adjustments.edit', $adjustment)" variant="secondary">{{ __('แก้ไข') }}</x-link-button>
            @endcan

            @can('post', $adjustment)
                <form method="POST" action="{{ route('stock-adjustments.post', $adjustment) }}"
                      onsubmit="return confirm(@js(__('ปรับปรุงสต็อกตามใบนี้เลยไหม? หลัง post แล้วแก้ไขไม่ได้อีก')))">
                    @csrf
                    <button type="submit" class="rounded-md bg-aqua-400 px-4 py-2 text-sm font-medium text-navy-900 transition hover:bg-aqua-300">
                        {{ __('ยืนยันการปรับปรุง') }}
                    </button>
                </form>
            @endcan

            @can('delete', $adjustment)
                <x-delete-button :action="route('stock-adjustments.destroy', $adjustment)"
                                 :label="__('ยกเลิกใบ')"
                                 :confirm="__('ยืนยันการยกเลิกใบ :no?', ['no' => $adjustment->adjust_no])" />
            @endcan
        </x-slot>
    </x-page-header>

    @if ($adjustment->status === App\Enums\StockDocumentStatus::Draft)
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
            {{ __('ใบนี้ยังเป็นร่าง — ผลต่างด้านล่างคำนวณจากยอดปัจจุบัน และจะคำนวณใหม่อีกครั้งตอน post') }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('รายการที่ปรับปรุง')" :padded="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="table-head-cell">{{ __('SKU') }}</th>
                                <th scope="col" class="table-head-cell">{{ __('สินค้า') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('ยอดในระบบ') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('นับได้จริง') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('ผลต่าง') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($adjustment->items as $item)
                                @php $diff = (float) $item->qty_diff; @endphp
                                <tr>
                                    <td class="table-cell-base tabular font-medium text-navy-900">{{ $item->product->sku }}</td>
                                    <td class="table-cell-base">
                                        <p class="max-w-xs truncate">{{ $item->product->name_th }}</p>
                                        @if ($item->lot_no)
                                            <p class="tabular text-xs text-gray-400">Lot: {{ $item->lot_no }}</p>
                                        @endif
                                    </td>
                                    <td class="table-cell-base tabular text-end text-gray-500">{{ number_format((float) $item->qty_system, 3) }}</td>
                                    <td class="table-cell-base tabular text-end font-medium">{{ number_format((float) $item->qty_counted, 3) }}</td>
                                    <td class="table-cell-base tabular text-end font-semibold {{ $diff < 0 ? 'text-rose-600' : ($diff > 0 ? 'text-emerald-700' : 'text-gray-400') }}">
                                        {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 3) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($adjustment->movements->isNotEmpty())
                <x-card :title="__('รายการที่เขียนลง ledger')" :padded="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="table-head-cell">{{ __('สินค้า') }}</th>
                                    <th scope="col" class="table-head-cell">{{ __('ประเภท') }}</th>
                                    <th scope="col" class="table-head-cell text-end">{{ __('จำนวน') }}</th>
                                    <th scope="col" class="table-head-cell text-end">{{ __('ยอดหลังรายการ') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($adjustment->movements as $movement)
                                    @php $qty = (float) $movement->qty; @endphp
                                    <tr>
                                        <td class="table-cell-base tabular">{{ $movement->product->sku }}</td>
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
                    <dd class="mt-0.5"><x-badge :color="$adjustment->status->badgeColor()">{{ $adjustment->status->label() }}</x-badge></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('เหตุผล') }}</dt>
                    <dd class="text-gray-900">{{ $adjustment->reason->label() }}</dd>
                    <dd class="text-xs text-gray-500">{{ $adjustment->reason->description() }}</dd>
                </div>
                @foreach ([
                    __('คลัง') => $adjustment->warehouse->code . ' — ' . $adjustment->warehouse->name,
                    __('วันที่ปรับปรุง') => $adjustment->adjusted_at->translatedFormat('d M Y H:i'),
                    __('ผู้สร้าง') => $adjustment->creator?->name,
                    __('ผู้ยืนยัน') => $adjustment->poster?->name,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs text-gray-500">{{ $label }}</dt>
                        <dd class="text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($adjustment->note)
                <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-3 text-sm text-gray-600">{{ $adjustment->note }}</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
