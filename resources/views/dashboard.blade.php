<x-app-layout>
    <x-slot name="title">{{ __('แดชบอร์ด') }}</x-slot>

    <x-page-header :title="__('แดชบอร์ด')"
                   :subtitle="__('สรุปข้อมูลหลักและสถานะคลัง · ยอดขายและรายงานจะเพิ่มใน Phase 5')" />

    @php
        $tiles = [
            ['label' => __('ลูกค้า'), 'value' => $stats['customers'], 'route' => 'customers.index', 'icon' => 'users'],
            ['label' => __('สินค้า / อะไหล่'), 'value' => $stats['products'], 'route' => 'products.index', 'icon' => 'cube'],
            ['label' => __('ผู้ขาย'), 'value' => $stats['suppliers'], 'route' => 'suppliers.index', 'icon' => 'truck'],
        ];

        if ($canSeeQuotations) {
            $tiles[] = ['label' => __('ใบเสนอราคาที่ยังเปิดอยู่'), 'value' => $quotationStats['open'], 'route' => 'quotations.index', 'icon' => 'document'];
            $tiles[] = [
                'label' => __('รออนุมัติ'),
                'value' => $quotationStats['pending_approval'],
                'route' => 'quotations.index',
                'params' => ['status' => 'pending_approval'],
                'icon' => 'clipboard',
                'alert' => $quotationStats['pending_approval'] > 0,
            ];
            $tiles[] = [
                'label' => __('ใกล้หมดอายุใน 7 วัน'),
                'value' => $quotationStats['expiring'],
                'route' => 'quotations.index',
                'params' => ['expiring' => 1],
                'icon' => 'chart',
                'alert' => $quotationStats['expiring'] > 0,
            ];
        }

        if ($canSeeOrders) {
            $tiles[] = [
                'label' => __('ใบสั่งขายที่ยังไม่ปิด'),
                'value' => $orderStats['open'],
                'route' => 'sales-orders.index',
                'params' => ['open' => 1],
                'icon' => 'clipboard',
            ];
        }

        if ($canSeeStock) {
            $tiles[] = ['label' => __('สินค้าเหลือน้อย'), 'value' => $lowStockCount, 'route' => 'stock.index', 'icon' => 'stack', 'alert' => $lowStockCount > 0];
            $tiles[] = ['label' => __('ประกันหมดใน 90 วัน'), 'value' => $warrantyExpiring, 'route' => 'serial-numbers.index', 'icon' => 'badge'];
        }
    @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @foreach ($tiles as $tile)
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

    @if ($awaitingApproval->isNotEmpty() || $expiringQuotations->isNotEmpty())
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
                                <p class="truncate text-xs text-gray-500">
                                    {{ $quotation->customer->name_th }} · {{ $quotation->salesUser->name }}
                                </p>
                            </div>
                            <span class="tabular shrink-0 font-semibold text-navy-900">
                                {{ number_format((float) $quotation->grand_total, 2) }}
                            </span>
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
        </div>
    @endif

    @if ($ordersToShip->isNotEmpty())
        <x-card class="mt-6" :padded="false">
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

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @if ($canSeeStock)
            <x-card :padded="false" class="lg:col-span-2">
                <x-slot name="header">
                    <div>
                        <h2 class="text-sm font-semibold text-navy-900">{{ __('สินค้าที่คงเหลือต่ำกว่าจุดสั่งซื้อ') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('เทียบยอดพร้อมขาย (คงเหลือ − จอง) กับสต็อกขั้นต่ำของสินค้า') }}</p>
                    </div>
                    @if ($lowStockCount > 0)
                        <a href="{{ route('stock.index', ['low_stock' => 1]) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">
                            {{ __('ดูทั้งหมด :count รายการ', ['count' => number_format($lowStockCount)]) }}
                        </a>
                    @endif
                </x-slot>

                @forelse ($lowStock as $level)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm last:border-0">
                        <div class="min-w-0">
                            <a href="{{ route('products.show', $level->product) }}" class="tabular truncate font-medium text-navy-900 hover:text-aqua-600">
                                {{ $level->product->sku }}
                            </a>
                            <p class="truncate text-xs text-gray-500">{{ $level->product->name_th }} · {{ $level->warehouse->code }}</p>
                        </div>
                        <div class="shrink-0 text-end">
                            <p class="tabular font-semibold text-amber-700">{{ number_format((float) $level->qty_available, 3) }}</p>
                            <p class="tabular text-xs text-gray-400">{{ __('ขั้นต่ำ') }} {{ number_format((float) $level->product->min_stock, 3) }}</p>
                        </div>
                    </div>
                @empty
                    <x-empty-state :message="__('ไม่มีสินค้าที่ต่ำกว่าจุดสั่งซื้อ')" />
                @endforelse
            </x-card>
        @endif

        <x-card :title="__('สินค้าที่เพิ่มล่าสุด')" :padded="false">
            @forelse ($recentProducts as $product)
                <a href="{{ route('products.show', $product) }}"
                   class="flex items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm transition last:border-0 hover:bg-gray-50">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-navy-900">{{ $product->name_th }}</p>
                        <p class="tabular truncate text-xs text-gray-500">{{ $product->sku }} · {{ $product->category?->name_th }}</p>
                    </div>
                    <span class="tabular shrink-0 text-sm text-gray-600">{{ number_format((float) $product->list_price, 2) }}</span>
                </a>
            @empty
                <x-empty-state :message="__('ยังไม่มีสินค้าในระบบ')" />
            @endforelse
        </x-card>

        <x-card :title="__('ลูกค้าที่เพิ่มล่าสุด')" :padded="false">
            @forelse ($recentCustomers as $customer)
                <a href="{{ route('customers.show', $customer) }}"
                   class="flex items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm transition last:border-0 hover:bg-gray-50">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-navy-900">{{ $customer->name_th }}</p>
                        <p class="tabular truncate text-xs text-gray-500">{{ $customer->code }} · {{ $customer->province ?? '—' }}</p>
                    </div>
                    <x-badge :color="$customer->price_tier->value === 'project' ? 'aqua' : 'gray'">
                        {{ $customer->price_tier->label() }}
                    </x-badge>
                </a>
            @empty
                <x-empty-state :message="__('ยังไม่มีลูกค้าในระบบ')" />
            @endforelse
        </x-card>
    </div>
</x-app-layout>
