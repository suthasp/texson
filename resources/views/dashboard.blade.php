<x-app-layout>
    <x-slot name="title">{{ __('แดชบอร์ด') }}</x-slot>

    <x-page-header :title="__('แดชบอร์ด')"
                   :subtitle="__('สรุปข้อมูลหลักของระบบ · ยอดขายและรายงานจะเพิ่มใน Phase 5')" />

    @php
        $tiles = [
            ['label' => __('ลูกค้า'), 'value' => $stats['customers'], 'route' => 'customers.index', 'icon' => 'users'],
            ['label' => __('สินค้า / อะไหล่'), 'value' => $stats['products'], 'route' => 'products.index', 'icon' => 'cube'],
            ['label' => __('ผู้ขาย'), 'value' => $stats['suppliers'], 'route' => 'suppliers.index', 'icon' => 'truck'],
            ['label' => __('ยี่ห้อ'), 'value' => $stats['brands'], 'route' => 'brands.index', 'icon' => 'badge'],
            ['label' => __('คลังสินค้า'), 'value' => $stats['warehouses'], 'route' => 'warehouses.index', 'icon' => 'building'],
        ];
    @endphp

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($tiles as $tile)
            <a href="{{ route($tile['route']) }}"
               class="group rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-aqua-300 hover:shadow">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium text-gray-500">{{ $tile['label'] }}</p>
                    <x-nav-icon :name="$tile['icon']" class="h-4 w-4 text-gray-300 transition group-hover:text-aqua-400" />
                </div>
                <p class="tabular mt-2 text-2xl font-semibold text-navy-900">{{ number_format($tile['value']) }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
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
