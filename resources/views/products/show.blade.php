<x-app-layout>
    <x-slot name="title">{{ $product->name_th }}</x-slot>

    <x-page-header :title="$product->name_th"
                   :subtitle="$product->sku . ($product->model ? ' · ' . $product->model : '')"
                   :back="route('products.index')">
        <x-slot name="actions">
            @can('update', $product)
                <x-link-button :href="route('products.edit', $product)" variant="secondary">{{ __('แก้ไข') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            <x-card :title="__('ราคา')">
                @php
                    $prices = [
                        __('ราคามาตรฐาน') => $product->list_price,
                        __('ราคาตัวแทนจำหน่าย') => $product->dealer_price,
                        __('ราคาโครงการ') => $product->project_price,
                    ];
                @endphp

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($prices as $label => $value)
                        <div>
                            <p class="text-xs text-gray-500">{{ $label }}</p>
                            <p class="tabular mt-0.5 text-lg font-semibold text-navy-900">{{ number_format((float) $value, 2) }}</p>
                        </div>
                    @endforeach

                    @if ($canViewCost)
                        <div class="rounded-lg bg-amber-50 px-3 py-2">
                            <p class="text-xs text-amber-700">{{ __('ราคาทุน') }}</p>
                            <p class="tabular mt-0.5 text-lg font-semibold text-amber-900">{{ number_format((float) $product->cost_price, 2) }}</p>
                            @php
                                $listPrice = (float) $product->list_price;
                                $margin = $listPrice > 0 ? (($listPrice - (float) $product->cost_price) / $listPrice) * 100 : null;
                            @endphp
                            @if ($margin !== null)
                                <p class="tabular text-xs {{ $margin < 10 ? 'text-rose-600' : 'text-emerald-700' }}">
                                    {{ __('margin') }} {{ number_format($margin, 1) }}%
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            </x-card>

            @if (filled($product->spec))
                <x-card :title="__('สเปกทางเทคนิค')" :padded="false">
                    <dl class="divide-y divide-gray-50">
                        @foreach ($product->spec as $key => $value)
                            <div class="flex justify-between gap-4 px-5 py-2.5 text-sm">
                                <dt class="text-gray-500">{{ $key }}</dt>
                                <dd class="tabular font-medium text-gray-900">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            @endif

            <x-card :title="__('ผู้ขาย')" :padded="false">
                @forelse ($product->suppliers as $supplier)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 last:border-0">
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 text-sm font-medium text-gray-900">
                                {{ $supplier->name }}
                                @if ($supplier->pivot->is_preferred)
                                    <x-badge color="aqua">{{ __('ผู้ขายหลัก') }}</x-badge>
                                @endif
                            </p>
                            <p class="tabular text-xs text-gray-500">
                                {{ $supplier->pivot->supplier_sku ?: '—' }} ·
                                {{ __('Lead time') }} {{ $supplier->pivot->lead_time_days }} {{ __('วัน') }}
                            </p>
                        </div>
                        @if ($canViewCost)
                            <span class="tabular text-sm text-gray-700">{{ number_format((float) $supplier->pivot->cost_price, 2) }}</span>
                        @endif
                    </div>
                @empty
                    <x-empty-state :message="__('ยังไม่ได้ผูกผู้ขายกับสินค้านี้')" />
                @endforelse
            </x-card>

            @if ($product->description)
                <x-card :title="__('รายละเอียด')">
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ $product->description }}</p>
                </x-card>
            @endif
        </div>

        <div class="space-y-4">
            <x-card :title="__('ข้อมูลสินค้า')">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        __('ชื่ออังกฤษ') => $product->name_en,
                        __('หมวดหมู่') => $product->category?->fullName(),
                        __('ยี่ห้อ') => $product->brand?->name,
                        __('รุ่น') => $product->model,
                        __('Part Number') => $product->part_number,
                        __('หน่วยนับ') => $product->uom->label(),
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-gray-500">{{ $label }}</dt>
                            <dd class="text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            <x-card :title="__('การควบคุมสต็อก')">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('สต็อกขั้นต่ำ') }}</dt>
                        <dd class="tabular text-gray-900">{{ number_format((float) $product->min_stock, 3) }} {{ $product->uom->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('จำนวนสั่งซื้อซ้ำ') }}</dt>
                        <dd class="tabular text-gray-900">{{ number_format((float) $product->reorder_qty, 3) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('ระยะเวลาสั่งของ') }}</dt>
                        <dd class="tabular text-gray-900">{{ $product->lead_time_days }} {{ __('วัน') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('ระยะประกัน') }}</dt>
                        <dd class="tabular text-gray-900">{{ $product->warranty_months }} {{ __('เดือน') }}</dd>
                    </div>

                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <x-badge :color="$product->is_active ? 'green' : 'gray'">
                            {{ $product->is_active ? __('ใช้งาน') : __('ปิดใช้งาน') }}
                        </x-badge>
                        @if ($product->is_serialized)
                            <x-badge color="navy">{{ __('ติดตาม Serial') }}</x-badge>
                        @endif
                        @if ($product->track_lot)
                            <x-badge color="amber">{{ __('ติดตาม Lot') }}</x-badge>
                        @endif
                    </div>
                </dl>

                <p class="mt-4 border-t border-gray-100 pt-3 text-xs text-gray-400">
                    {{ __('ยอดคงเหลือรายคลังจะแสดงที่นี่ใน Phase 2') }}
                </p>
            </x-card>
        </div>
    </div>
</x-app-layout>
