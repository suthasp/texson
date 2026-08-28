@php
    /** @var App\Models\Delivery $delivery */
    use App\Enums\StockDocumentStatus;

    $order = $delivery->salesOrder;
@endphp

<x-app-layout>
    <x-slot name="title">{{ $delivery->delivery_no }}</x-slot>

    <x-page-header :title="$delivery->delivery_no"
                   :subtitle="__('ใบส่งของ · :date · :customer', [
                       'date' => $delivery->delivery_date->translatedFormat('d M Y'),
                       'customer' => $order->customer->name_th,
                   ])"
                   :back="route('sales-orders.show', $order)">
        <x-slot name="actions">
            @can('update', $delivery)
                <x-link-button :href="route('deliveries.edit', $delivery)" variant="secondary">{{ __('แก้ไข') }}</x-link-button>
            @endcan

            @can('post', $delivery)
                <form method="POST" action="{{ route('deliveries.post', $delivery) }}"
                      x-data
                      @submit.prevent="confirm(@js(__('บันทึกใบนี้เข้าสต็อกเลยไหม? ของจะถูกตัดออกจากคลังจริงและย้อนกลับไม่ได้'))) && $el.submit()">
                    @csrf
                    <button type="submit" class="rounded-md bg-aqua-400 px-4 py-2 text-sm font-medium text-navy-900 transition hover:bg-aqua-300">
                        {{ __('บันทึกตัดสต็อก') }}
                    </button>
                </form>
            @endcan

            @can('delete', $delivery)
                <x-delete-button :action="route('deliveries.destroy', $delivery)"
                                 :label="__('ยกเลิกใบ')"
                                 :confirm="__('ยืนยันการยกเลิกใบส่งของ :no?', ['no' => $delivery->delivery_no])" />
            @endcan
        </x-slot>
    </x-page-header>

    @if ($delivery->status === StockDocumentStatus::Draft)
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
            {{ __('ใบนี้ยังเป็นร่าง — ของยังไม่ถูกตัดออกจากคลัง กด "บันทึกตัดสต็อก" เมื่อจ่ายของจริงแล้ว') }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('รายการที่ส่ง')" :padded="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="table-head-cell">{{ __('รหัส') }}</th>
                                <th scope="col" class="table-head-cell">{{ __('รายละเอียด') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('จำนวนที่ส่ง') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($delivery->items as $item)
                                @php $orderItem = $item->salesOrderItem; @endphp
                                <tr>
                                    <td class="table-cell-base tabular font-medium text-navy-900">{{ $orderItem->sku_snapshot ?? '—' }}</td>
                                    <td class="table-cell-base">
                                        <p class="max-w-md whitespace-pre-line">{{ $orderItem->description }}</p>
                                        @if ($item->lot_no)
                                            <p class="tabular text-xs text-gray-400">Lot: {{ $item->lot_no }}</p>
                                        @endif
                                        @if (filled($item->serials()))
                                            <p class="tabular mt-1 text-xs text-gray-500">
                                                {{ __('Serial') }}: {{ implode(', ', array_slice($item->serials(), 0, 5)) }}
                                                @if (count($item->serials()) > 5)
                                                    {{ __('และอีก :count ตัว', ['count' => count($item->serials()) - 5]) }}
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                    <td class="table-cell-base tabular text-end">
                                        {{ number_format((float) $item->qty, 3) }}
                                        <span class="text-xs text-gray-400">{{ $orderItem->uom }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($delivery->movements->isNotEmpty())
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
                                @foreach ($delivery->movements as $movement)
                                    <tr>
                                        <td class="table-cell-base tabular text-xs text-gray-500">{{ $movement->moved_at->translatedFormat('d M Y H:i') }}</td>
                                        <td class="table-cell-base tabular">{{ $movement->product->sku }}</td>
                                        <td class="table-cell-base tabular text-end text-rose-700">{{ number_format((float) $movement->qty, 3) }}</td>
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
                        <dd class="mt-0.5"><x-badge :color="$delivery->status->badgeColor()">{{ $delivery->status->label() }}</x-badge></dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500">{{ __('ใบสั่งขาย') }}</dt>
                        <dd>
                            <a href="{{ route('sales-orders.show', $order) }}" class="tabular text-aqua-600 hover:underline">{{ $order->so_no }}</a>
                        </dd>
                    </div>

                    @foreach ([
                        __('ลูกค้า') => $order->customer->name_th,
                        __('หน้างาน') => $order->site?->site_name,
                        __('คลังที่จ่ายของ') => $delivery->warehouse->code . ' — ' . $delivery->warehouse->name,
                        __('ชื่อผู้รับของ') => $delivery->receiver_name,
                        __('รถ / ผู้ส่ง') => $delivery->vehicle_note,
                        __('ผู้สร้าง') => $delivery->creator?->name,
                        __('ผู้บันทึกตัดสต็อก') => $delivery->poster?->name,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-gray-500">{{ $label }}</dt>
                            <dd class="text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach

                    @if ($delivery->posted_at)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('ตัดสต็อกเมื่อ') }}</dt>
                            <dd class="tabular text-gray-900">{{ $delivery->posted_at->translatedFormat('d M Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @if ($delivery->note)
                <x-card :title="__('หมายเหตุ')">
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ $delivery->note }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
