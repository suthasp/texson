@php
    $money = static fn ($value): string => number_format((float) $value, 2);
@endphp

<x-app-layout>
    <x-slot name="title">{{ __('แดชบอร์ด') }}</x-slot>

    <x-page-header :title="__('แดชบอร์ด')"
                   :subtitle="__('ภาพรวมงานขายและคลัง ณ :date', ['date' => now()->translatedFormat('d M Y')])">
        @if ($canSeeReports)
            <x-slot name="actions">
                <x-link-button :href="route('reports.index')" variant="secondary">{{ __('ดูรายงานเต็ม') }}</x-link-button>
            </x-slot>
        @endif
    </x-page-header>

    {{-- ── ยอดขาย (spec 7) ── --}}
    @if ($canSeeOrders)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-navy-200 bg-navy-900 p-4 shadow-sm">
                <p class="text-xs font-medium text-navy-200">{{ __('ยอดขายเดือนนี้') }}</p>
                <p class="tabular mt-2 text-2xl font-semibold text-white">{{ $money($salesThisMonth['ordered']) }}</p>
                <p class="mt-1 text-xs text-navy-300">{{ __(':count ใบสั่งขาย', ['count' => $salesThisMonth['order_count']]) }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500">{{ __('ยอดขายปีนี้') }}</p>
                <p class="tabular mt-2 text-2xl font-semibold text-navy-900">{{ $money($salesThisYear['ordered']) }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ __(':count ใบสั่งขาย', ['count' => $salesThisYear['order_count']]) }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-gray-500">{{ __('ส่งมอบครบแล้ว (ปีนี้)') }}</p>
                <p class="tabular mt-2 text-2xl font-semibold text-emerald-700">{{ $money($salesThisYear['delivered']) }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ __(':count ใบ', ['count' => $salesThisYear['delivered_count']]) }}</p>
            </div>

            @if ($canSeeQuotations)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium text-gray-500">{{ __('win rate ปีนี้') }}</p>
                    <p class="tabular mt-2 text-2xl font-semibold text-navy-900">
                        {{ number_format((float) $quotationsThisYear['win_rate'], 1) }}%
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        {{ __('ตอบรับ :won จาก :decided ใบ', [
                            'won' => $quotationsThisYear['accepted_count'],
                            'decided' => $quotationsThisYear['decided_count'],
                        ]) }}
                    </p>
                </div>
            @endif
        </div>
    @endif

    {{-- ── งานที่ต้องลงมือทำ ── --}}
    @php
        $actionTiles = [];

        if ($canSeeQuotations) {
            $actionTiles[] = [
                'label' => __('ใบเสนอราคารออนุมัติ'),
                'value' => $actions['quotations_pending_approval'],
                'route' => 'quotations.index',
                'params' => ['status' => 'pending_approval'],
                'icon' => 'document',
                'alert' => $actions['quotations_pending_approval'] > 0,
            ];
            $actionTiles[] = [
                'label' => __('ใกล้หมดอายุใน 7 วัน'),
                'value' => $actions['quotations_expiring'],
                'route' => 'quotations.index',
                'params' => ['expiring' => 1],
                'icon' => 'chart',
                'alert' => $actions['quotations_expiring'] > 0,
            ];
        }

        if ($canSeeOrders) {
            $actionTiles[] = [
                'label' => __('ใบสั่งขายรอยืนยัน'),
                'value' => $actions['orders_pending_confirm'],
                'route' => 'sales-orders.index',
                'params' => ['status' => 'pending'],
                'icon' => 'clipboard',
                'alert' => $actions['orders_pending_confirm'] > 0,
            ];
            $actionTiles[] = [
                'label' => __('รอส่งของ'),
                'value' => $actions['orders_to_ship'],
                'route' => 'sales-orders.index',
                'params' => ['open' => 1],
                'icon' => 'truck',
            ];
        }

        if ($canSeeStock) {
            $actionTiles[] = [
                'label' => __('สินค้าเหลือน้อย'),
                'value' => $lowStockCount,
                'route' => 'stock.index',
                'params' => ['low_stock' => 1],
                'icon' => 'stack',
                'alert' => $lowStockCount > 0,
            ];
            $actionTiles[] = [
                'label' => __('ประกันหมดใน 90 วัน'),
                'value' => $warrantyExpiring,
                'route' => 'serial-numbers.index',
                'icon' => 'badge',
            ];
        }
    @endphp

    @if ($actionTiles !== [])
        <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($actionTiles as $tile)
                <a href="{{ route($tile['route'], $tile['params'] ?? []) }}"
                   class="group rounded-xl border bg-white p-4 shadow-sm transition hover:shadow
                          {{ ($tile['alert'] ?? false) ? 'border-amber-300 bg-amber-50/60' : 'border-gray-200 hover:border-aqua-300' }}">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium {{ ($tile['alert'] ?? false) ? 'text-amber-800' : 'text-gray-500' }}">{{ $tile['label'] }}</p>
                        <x-nav-icon :name="$tile['icon']" class="h-4 w-4 {{ ($tile['alert'] ?? false) ? 'text-amber-500' : 'text-gray-300 group-hover:text-aqua-400' }} transition" />
                    </div>
                    <p class="tabular mt-2 text-2xl font-semibold {{ ($tile['alert'] ?? false) ? 'text-amber-900' : 'text-navy-900' }}">
                        {{ number_format($tile['value']) }}
                    </p>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ── คิวงาน ── --}}
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @if ($awaitingApproval->isNotEmpty())
            <x-card :padded="false">
                <x-slot name="header">
                    <div>
                        <h2 class="text-sm font-semibold text-navy-900">{{ __('ใบเสนอราคารออนุมัติจากคุณ') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('เข้าเกณฑ์ส่วนลด margin หรือยอดสุทธิที่ตั้งไว้') }}</p>
                    </div>
                </x-slot>

                @foreach ($awaitingApproval as $quotation)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm last:border-0">
                        <div class="min-w-0">
                            <a href="{{ route('quotations.show', $quotation) }}" class="tabular font-medium text-navy-900 hover:text-aqua-600">
                                {{ $quotation->displayNo() }}
                            </a>
                            <p class="truncate text-xs text-gray-500">{{ $quotation->customer->name_th }} · {{ $quotation->salesUser->name }}</p>
                        </div>
                        <span class="tabular shrink-0 font-semibold text-navy-900">{{ $money($quotation->grand_total) }}</span>
                    </div>
                @endforeach
            </x-card>
        @endif

        @if ($ordersToShip->isNotEmpty())
            <x-card :padded="false">
                <x-slot name="header">
                    <div>
                        <h2 class="text-sm font-semibold text-navy-900">{{ __('ใบสั่งขายที่รอส่งของ') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('เรียงตามวันที่ลูกค้าต้องการรับของ') }}</p>
                    </div>
                    <a href="{{ route('sales-orders.index', ['open' => 1]) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">
                        {{ __('ดูทั้งหมด') }}
                    </a>
                </x-slot>

                @foreach ($ordersToShip as $order)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm last:border-0">
                        <div class="min-w-0">
                            <a href="{{ route('sales-orders.show', $order) }}" class="tabular font-medium text-navy-900 hover:text-aqua-600">
                                {{ $order->so_no }}
                            </a>
                            <p class="truncate text-xs text-gray-500">
                                {{ $order->customer->name_th }} · {{ $order->warehouse->code }}
                                @if ($order->required_date)
                                    · {{ __('ต้องการ :date', ['date' => $order->required_date->translatedFormat('d M Y')]) }}
                                @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($order->hasShortage())
                                <x-badge color="amber">{{ __('ของขาด :qty', ['qty' => rtrim(rtrim($order->shortageQty(), '0'), '.')]) }}</x-badge>
                            @endif
                            <x-badge :color="$order->status->badgeColor()">
                                {{ __('ส่งแล้ว :percent%', ['percent' => number_format((float) $order->deliveryProgressPercent(), 0)]) }}
                            </x-badge>
                        </div>
                    </div>
                @endforeach
            </x-card>
        @endif

        @if ($expiringQuotations->isNotEmpty())
            <x-card :padded="false">
                <x-slot name="header">
                    <div>
                        <h2 class="text-sm font-semibold text-navy-900">{{ __('ใบที่ใกล้หมดอายุใน 7 วัน') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('เลยวันยืนราคาแล้วระบบจะเปลี่ยนเป็นหมดอายุอัตโนมัติทุกเช้า') }}</p>
                    </div>
                    <a href="{{ route('quotations.index', ['expiring' => 1]) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">
                        {{ __('ดูทั้งหมด') }}
                    </a>
                </x-slot>

                @foreach ($expiringQuotations as $quotation)
                    @php $daysLeft = $quotation->daysUntilExpiry(); @endphp
                    <div class="flex items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm last:border-0">
                        <div class="min-w-0">
                            <a href="{{ route('quotations.show', $quotation) }}" class="tabular font-medium text-navy-900 hover:text-aqua-600">
                                {{ $quotation->displayNo() }}
                            </a>
                            <p class="truncate text-xs text-gray-500">{{ $quotation->customer->name_th }}</p>
                        </div>
                        <x-badge :color="$daysLeft <= 2 ? 'red' : 'amber'" class="shrink-0">
                            {{ $daysLeft <= 0 ? __('หมดอายุวันนี้') : __('เหลือ :days วัน', ['days' => $daysLeft]) }}
                        </x-badge>
                    </div>
                @endforeach
            </x-card>
        @endif

        @if ($canSeeStock && $lowStock->isNotEmpty())
            <x-card :padded="false">
                <x-slot name="header">
                    <div>
                        <h2 class="text-sm font-semibold text-navy-900">{{ __('สินค้าที่คงเหลือต่ำกว่าจุดสั่งซื้อ') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('เทียบยอดพร้อมขาย (คงเหลือ − จอง) กับสต็อกขั้นต่ำ') }}</p>
                    </div>
                    <a href="{{ route('stock.index', ['low_stock' => 1]) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">
                        {{ __('ดูทั้งหมด :count รายการ', ['count' => number_format($lowStockCount)]) }}
                    </a>
                </x-slot>

                @foreach ($lowStock as $level)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm last:border-0">
                        <div class="min-w-0">
                            <a href="{{ route('products.show', $level->product) }}" class="tabular truncate font-medium text-navy-900 hover:text-aqua-600">
                                {{ $level->product->sku }}
                            </a>
                            <p class="truncate text-xs text-gray-500">{{ $level->product->name_th }} · {{ $level->warehouse->code }}</p>
                        </div>
                        <div class="shrink-0 text-end">
                            <p class="tabular font-medium text-amber-700">{{ number_format((float) $level->qty_available, 3) }}</p>
                            <p class="tabular text-xs text-gray-400">{{ __('ขั้นต่ำ :min', ['min' => number_format((float) $level->product->min_stock, 3)]) }}</p>
                        </div>
                    </div>
                @endforeach
            </x-card>
        @endif
    </div>

    {{-- ── เอกสารคลังที่ยังเป็นร่าง ── --}}
    @if ($canSeeStock && array_sum($draftDocuments) > 0)
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-aqua-200 bg-aqua-50 px-4 py-3 text-sm text-aqua-900">
            <span class="font-medium">{{ __('เอกสารที่ยังเป็นร่าง รอกด post') }}:</span>
            @if ($draftDocuments['goods_receipts'] > 0)
                <a href="{{ route('goods-receipts.index', ['status' => 'draft']) }}" class="underline">
                    {{ __('ใบรับสินค้า :count ใบ', ['count' => $draftDocuments['goods_receipts']]) }}
                </a>
            @endif
            @if ($draftDocuments['transfers'] > 0)
                <a href="{{ route('stock-transfers.index', ['status' => 'draft']) }}" class="underline">
                    {{ __('ใบโอน :count ใบ', ['count' => $draftDocuments['transfers']]) }}
                </a>
            @endif
            @if ($draftDocuments['adjustments'] > 0)
                <a href="{{ route('stock-adjustments.index', ['status' => 'draft']) }}" class="underline">
                    {{ __('ใบปรับปรุง :count ใบ', ['count' => $draftDocuments['adjustments']]) }}
                </a>
            @endif
        </div>
    @endif
</x-app-layout>
