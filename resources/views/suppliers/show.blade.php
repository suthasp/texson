<x-app-layout>
    <x-slot name="title">{{ $supplier->name }}</x-slot>

    <x-page-header :title="$supplier->name" :subtitle="$supplier->code" :back="route('suppliers.index')">
        <x-slot name="actions">
            @can('update', $supplier)
                <x-link-button :href="route('suppliers.edit', $supplier)" variant="secondary">{{ __('แก้ไข') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('ข้อมูลผู้ขาย')">
            <dl class="space-y-3 text-sm">
                @foreach ([
                    __('เลขผู้เสียภาษี') => $supplier->tax_id,
                    __('ผู้ติดต่อ') => $supplier->contact_name,
                    __('โทรศัพท์') => $supplier->phone,
                    __('อีเมล') => $supplier->email,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs text-gray-500">{{ $label }}</dt>
                        <dd class="text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                    </div>
                @endforeach

                <div>
                    <dt class="text-xs text-gray-500">{{ __('ระยะเวลาส่งของ') }}</dt>
                    <dd class="tabular text-gray-900">{{ $supplier->lead_time_days }} {{ __('วัน') }}</dd>
                </div>

                <div>
                    <dt class="text-xs text-gray-500">{{ __('สถานะ') }}</dt>
                    <dd class="mt-0.5">
                        <x-badge :color="$supplier->is_active ? 'green' : 'gray'">
                            {{ $supplier->is_active ? __('ใช้งาน') : __('ปิดใช้งาน') }}
                        </x-badge>
                    </dd>
                </div>
            </dl>

            @if ($supplier->notes)
                <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-3 text-sm text-gray-600">{{ $supplier->notes }}</p>
            @endif
        </x-card>

        <x-card :title="__('สินค้าที่ซื้อจากผู้ขายรายนี้')" :padded="false" class="lg:col-span-2">
            @forelse ($supplier->products as $product)
                <a href="{{ route('products.show', $product) }}"
                   class="flex items-center justify-between gap-3 border-b border-gray-50 px-5 py-3 text-sm transition last:border-0 hover:bg-gray-50">
                    <div class="min-w-0">
                        <p class="flex items-center gap-2 truncate font-medium text-navy-900">
                            {{ $product->name_th }}
                            @if ($product->pivot->is_preferred)
                                <x-badge color="aqua">{{ __('หลัก') }}</x-badge>
                            @endif
                        </p>
                        <p class="tabular truncate text-xs text-gray-500">
                            {{ $product->sku }}
                            @if ($product->pivot->supplier_sku)
                                · {{ __('รหัสผู้ขาย') }}: {{ $product->pivot->supplier_sku }}
                            @endif
                        </p>
                    </div>
                    <span class="tabular shrink-0 text-sm text-gray-600">{{ number_format((float) $product->pivot->cost_price, 2) }}</span>
                </a>
            @empty
                <x-empty-state :message="__('ยังไม่มีสินค้าผูกกับผู้ขายรายนี้')" />
            @endforelse
        </x-card>
    </div>
</x-app-layout>
