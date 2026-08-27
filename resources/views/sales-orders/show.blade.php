@php
    /** @var App\Models\SalesOrder $order */
    use App\Enums\PermissionName;
    use App\Enums\SalesOrderStatus;
    use App\Enums\StockDocumentStatus;

    $canSeeCost = auth()->user()->can(PermissionName::ProductViewCost->value);
@endphp

<x-app-layout>
    <x-slot name="title">{{ $order->so_no }}</x-slot>

    <x-page-header :title="$order->so_no"
                   :subtitle="__(':customer · สั่งเมื่อ :date', [
                       'customer' => $order->customer->name_th,
                       'date' => $order->order_date->translatedFormat('d M Y'),
                   ])"
                   :back="route('sales-orders.index')">
        <x-slot name="actions">
            @can('update', $order)
                <x-link-button :href="route('sales-orders.edit', $order)" variant="secondary">{{ __('แก้ไขหัวใบ') }}</x-link-button>
            @endcan

            @can('confirm', $order)
                <form method="POST" action="{{ route('sales-orders.confirm', $order) }}"
                      onsubmit="return confirm(@js(__('ยืนยันใบนี้และจองของในคลัง? หลังยืนยันแล้วแก้หัวใบไม่ได้อีก')))">
                    @csrf
                    <button type="submit" class="rounded-md bg-aqua-400 px-4 py-2 text-sm font-medium text-navy-900 transition hover:bg-aqua-300">
                        {{ __('ยืนยันและจองของ') }}
                    </button>
                </form>
            @endcan

            @can('deliver', $order)
                <x-link-button :href="route('sales-orders.deliveries.create', $order)">{{ __('ออกใบส่งของ') }}</x-link-button>
            @endcan

            @can('cancel', $order)
                <button type="button" x-data @click="$dispatch('open-modal', 'cancel-order')"
                        class="rounded-md border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50">
                    {{ __('ยกเลิกใบ') }}
                </button>
            @endcan
        </x-slot>
    </x-page-header>

    {{-- ── แถบสถานะ ── --}}
    <x-card class="mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <x-badge :color="$order->status->badgeColor()" class="text-sm">{{ $order->status->label() }}</x-badge>

            @if ($order->quotation)
                <a href="{{ route('quotations.show', $order->quotation) }}" class="tabular text-sm text-aqua-600 hover:underline">
                    {{ __('จากใบเสนอราคา :no', ['no' => $order->quotation->displayNo()]) }}
                </a>
            @endif

            @if ($order->confirmed_at)
                <span class="text-xs text-gray-500">
                    {{ __('ยืนยันโดย :name เมื่อ :at', [
                        'name' => $order->confirmer?->name ?? '—',
                        'at' => $order->confirmed_at->translatedFormat('d M Y H:i'),
                    ]) }}
                </span>
            @endif

            @if (! $order->status->isClosed() && $order->status !== SalesOrderStatus::Pending)
                <span class="ms-auto text-sm text-gray-600">
                    {{ __('ส่งของแล้ว :percent%', ['percent' => number_format((float) $order->deliveryProgressPercent(), 0)]) }}
                </span>
            @endif
        </div>

        @if ($order->status === SalesOrderStatus::Pending)
            <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                {{ __('ใบนี้ยังไม่ยืนยัน — ของยังไม่ถูกจองในคลัง กด "ยืนยันและจองของ" เมื่อได้รับใบสั่งซื้อจากลูกค้าแล้ว') }}
            </div>
        @endif

        @if ($order->hasShortage())
            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm font-medium text-amber-900">
                    {{ __('จองของได้ไม่ครบ — ขาดอีก :qty ชิ้น (backorder)', [
                        'qty' => rtrim(rtrim($order->shortageQty(), '0'), '.'),
                    ]) }}
                </p>
                <p class="mt-0.5 text-sm text-amber-800">
                    {{ __('ส่งเท่าที่มีไปก่อนได้ ส่วนที่เหลือจะส่งได้เมื่อรับของเข้าคลัง :warehouse เพิ่ม', [
                        'warehouse' => $order->warehouse->code,
                    ]) }}
                </p>
            </div>
        @endif
    </x-card>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- ── รายการ ── --}}
            <x-card :title="__('รายการ')" :padded="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="table-head-cell w-10">#</th>
                                <th scope="col" class="table-head-cell">{{ __('รหัส / รายละเอียด') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('สั่ง') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('จอง') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('ส่งแล้ว') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('ราคา/หน่วย') }}</th>
                                <th scope="col" class="table-head-cell text-end">{{ __('จำนวนเงิน') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="table-cell-base tabular text-gray-400">{{ $item->line_no }}</td>
                                    <td class="table-cell-base">
                                        <div class="flex items-start gap-2">
                                            @unless ($item->item_type === App\Enums\QuotationItemType::Product)
                                                <x-badge color="navy" class="mt-0.5 shrink-0">{{ $item->item_type->label() }}</x-badge>
                                            @endunless
                                            <div class="min-w-0">
                                                @if ($item->sku_snapshot)
                                                    <p class="tabular text-xs font-medium text-navy-900">{{ $item->sku_snapshot }}</p>
                                                @endif
                                                <p class="max-w-md whitespace-pre-line">{{ $item->description }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="table-cell-base tabular text-end">
                                        {{ number_format((float) $item->qty_ordered, 3) }}
                                        <span class="text-xs text-gray-400">{{ $item->uom }}</span>
                                    </td>
                                    <td class="table-cell-base tabular text-end">
                                        @if ($item->isStockable())
                                            <span class="{{ bccomp($item->shortageQty(), '0', 3) > 0 ? 'font-semibold text-amber-700' : '' }}">
                                                {{ number_format((float) $item->qty_reserved, 3) }}
                                            </span>
                                            @if (bccomp($item->shortageQty(), '0', 3) > 0)
                                                <p class="text-xs text-amber-700">{{ __('ขาด :qty', ['qty' => rtrim(rtrim($item->shortageQty(), '0'), '.')]) }}</p>
                                            @endif
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="table-cell-base tabular text-end {{ $item->isFullyDelivered() ? 'text-emerald-700' : '' }}">
                                        {{ number_format((float) $item->qty_delivered, 3) }}
                                    </td>
                                    <td class="table-cell-base tabular text-end">{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td class="table-cell-base tabular text-end font-medium">{{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="6" class="table-cell-base text-end font-medium">{{ __('ยอดสุทธิ (รวม VAT)') }}</td>
                                <td class="table-cell-base tabular text-end font-semibold text-navy-900">
                                    {{ number_format((float) $order->grand_total, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>

            {{-- ── ใบส่งของ ── --}}
            <x-card :padded="false">
                <x-slot name="header">
                    <h2 class="text-sm font-semibold text-navy-900">{{ __('ใบส่งของ') }}</h2>
                    @can('deliver', $order)
                        <x-link-button :href="route('sales-orders.deliveries.create', $order)" variant="secondary">
                            {{ __('+ ออกใบส่งของ') }}
                        </x-link-button>
                    @endcan
                </x-slot>

                @forelse ($order->deliveries as $delivery)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm last:border-0">
                        <div class="min-w-0">
                            <a href="{{ route('deliveries.show', $delivery) }}" class="tabular font-medium text-navy-900 hover:text-aqua-600">
                                {{ $delivery->delivery_no }}
                            </a>
                            <p class="text-xs text-gray-500">
                                {{ $delivery->delivery_date->translatedFormat('d M Y') }} ·
                                {{ __(':count รายการ', ['count' => $delivery->items->count()]) }} ·
                                {{ $delivery->warehouse->code }}
                            </p>
                        </div>
                        <x-badge :color="$delivery->status->badgeColor()">{{ $delivery->status->label() }}</x-badge>
                    </div>
                @empty
                    <div class="px-5 py-6 text-center text-sm text-gray-500">
                        {{ $order->status->canDeliver()
                            ? __('ยังไม่มีใบส่งของ — กด "ออกใบส่งของ" เพื่อเริ่มจัดของ')
                            : __('ยังออกใบส่งของไม่ได้จนกว่าจะยืนยันใบสั่งขาย') }}
                    </div>
                @endforelse
            </x-card>

            @if ($order->serialNumbers->isNotEmpty())
                <x-card :title="__('Serial ที่ส่งมอบไปแล้ว')" :padded="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="table-head-cell">{{ __('Serial') }}</th>
                                    <th scope="col" class="table-head-cell">{{ __('สินค้า') }}</th>
                                    <th scope="col" class="table-head-cell">{{ __('รับประกันถึง') }}</th>
                                    <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($order->serialNumbers as $serial)
                                    <tr>
                                        <td class="table-cell-base tabular font-medium text-navy-900">{{ $serial->serial_no }}</td>
                                        <td class="table-cell-base tabular text-xs text-gray-500">{{ $serial->product->sku }}</td>
                                        <td class="table-cell-base tabular">{{ $serial->warranty_end?->translatedFormat('d M Y') ?? '—' }}</td>
                                        <td class="table-cell-base">
                                            <x-badge :color="$serial->status->badgeColor()">{{ $serial->status->label() }}</x-badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- ── แผงข้อมูล ── --}}
        <div class="space-y-4">
            <x-card :title="__('ข้อมูลเอกสาร')">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        __('ลูกค้า') => $order->customer->name_th,
                        __('หน้างาน') => $order->site?->site_name,
                        __('คลังที่จ่ายของ') => $order->warehouse->code . ' — ' . $order->warehouse->name,
                        __('เลขที่ PO ลูกค้า') => $order->customer_po_no,
                        __('วันที่ต้องการรับของ') => $order->required_date?->translatedFormat('d M Y'),
                        __('เงื่อนไขชำระเงิน') => $order->payment_terms,
                        __('เงื่อนไขส่งมอบ') => $order->delivery_terms,
                        __('ผู้ขาย') => $order->salesUser->name,
                        __('ผู้สร้าง') => $order->creator?->name,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-gray-500">{{ $label }}</dt>
                            <dd class="text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach

                    @if ($order->customer_po_file)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('ไฟล์ใบสั่งซื้อ') }}</dt>
                            <dd>
                                <a href="{{ route('sales-orders.po-file', $order) }}" target="_blank" rel="noopener"
                                   class="text-aqua-600 hover:underline">{{ __('เปิดไฟล์') }}</a>
                            </dd>
                        </div>
                    @endif

                    @if ($order->cancel_reason)
                        <div>
                            <dt class="text-xs text-gray-500">{{ __('เหตุผลที่ยกเลิก') }}</dt>
                            <dd class="text-gray-900">{{ $order->cancel_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </x-card>

            @if ($canSeeCost)
                <x-card :title="__('margin')">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-600">{{ __('ต้นทุนรวม') }}</span>
                            <span class="tabular">{{ number_format((float) $order->cost_total, 2) }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-600">{{ __('กำไรขั้นต้น') }}</span>
                            <span class="tabular font-medium">{{ number_format((float) $order->marginAmount(), 2) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between gap-2 rounded-md bg-emerald-50 px-2 py-1.5">
                            <span class="text-xs text-gray-600">{{ __('margin') }}</span>
                            <span class="tabular text-lg font-bold text-emerald-700">
                                {{ number_format((float) $order->marginPercent(), 2) }}%
                            </span>
                        </div>
                    </div>
                </x-card>
            @endif

            @if ($order->note || $order->internal_note)
                <x-card :title="__('หมายเหตุ')">
                    @if ($order->note)
                        <p class="whitespace-pre-line text-sm text-gray-700">{{ $order->note }}</p>
                    @endif
                    @if ($order->internal_note)
                        <p class="mt-3 whitespace-pre-line border-t border-gray-100 pt-3 text-sm text-gray-600">
                            <span class="text-xs text-gray-400">{{ __('บันทึกภายใน') }}</span><br>
                            {{ $order->internal_note }}
                        </p>
                    @endif
                </x-card>
            @endif
        </div>
    </div>

    @can('cancel', $order)
        <x-modal name="cancel-order" focusable>
            <form method="POST" action="{{ route('sales-orders.cancel', $order) }}" class="space-y-4 p-6">
                @csrf
                <h2 class="text-base font-semibold text-navy-900">{{ __('ยกเลิกใบสั่งขาย :no', ['no' => $order->so_no]) }}</h2>
                <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    {{ __('ของที่จองไว้ทั้งหมดจะถูกคืนเข้าคลังทันที · ของที่ส่งออกไปแล้วไม่ถูกดึงกลับ') }}
                </p>
                <x-form.textarea name="reason" :label="__('เหตุผล')" rows="3" :help="__('เช่น ลูกค้าเลื่อนโครงการ')" />
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('ปิด') }}</x-secondary-button>
                    <button type="submit" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-rose-500">
                        {{ __('ยืนยันการยกเลิก') }}
                    </button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
