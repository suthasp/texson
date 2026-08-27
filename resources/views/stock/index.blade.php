<x-app-layout>
    <x-slot name="title">{{ __('สต็อกคงเหลือ') }}</x-slot>

    <x-page-header :title="__('สต็อกคงเหลือ')"
                   :subtitle="__('พบ :count รายการ', ['count' => number_format($levels->total())])">
        <x-slot name="actions">
            @can('viewLedger', App\Models\StockLevel::class)
                <x-link-button :href="route('stock.ledger')" variant="secondary">{{ __('ประวัติการเคลื่อนไหว') }}</x-link-button>
            @endcan
            @can('create', App\Models\GoodsReceipt::class)
                <x-link-button :href="route('goods-receipts.create')">{{ __('รับสินค้าเข้า') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    @if ($lowStockCount > 0 && ! ($filters['low_stock'] ?? false))
        <a href="{{ route('stock.index', ['low_stock' => 1]) }}"
           class="mb-4 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 transition hover:bg-amber-100">
            <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <span class="flex-1">
                {{ __('มีสินค้า :count รายการที่คงเหลือต่ำกว่าจุดสั่งซื้อ', ['count' => number_format($lowStockCount)]) }}
            </span>
            <span class="shrink-0 font-medium underline">{{ __('ดูรายการ') }}</span>
        </a>
    @endif

    <x-card class="mb-4">
        <form method="GET" action="{{ route('stock.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('SKU, part number, ชื่อ หรือรุ่น') }}" class="form-input-base text-sm">
            </div>

            <div>
                <label for="warehouse_id" class="sr-only">{{ __('คลัง') }}</label>
                <select id="warehouse_id" name="warehouse_id" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกคลัง') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>
                            {{ $warehouse->code }} — {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="category_id" class="sr-only">{{ __('หมวดหมู่') }}</label>
                <select id="category_id" name="category_id" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกหมวดหมู่') }}</option>
                    @foreach ($categories as $root)
                        <option value="{{ $root->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $root->id)>{{ $root->name_th }}</option>
                        @foreach ($root->children as $child)
                            <option value="{{ $child->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $child->id)>&nbsp;&nbsp;— {{ $child->name_th }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="low_stock" value="1" @checked(! empty($filters['low_stock']))
                           class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                    {{ __('เหลือน้อย') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="include_zero" value="1" @checked(! empty($filters['include_zero']))
                           class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                    {{ __('รวมยอด 0') }}
                </label>
                <button type="submit" class="rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                    {{ __('ค้นหา') }}
                </button>
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="table-head-cell">{{ __('SKU') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สินค้า') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('คลัง') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('คงเหลือ') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('จองแล้ว') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('พร้อมขาย') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('จุดสั่งซื้อ') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($levels as $level)
                        @php $isLow = $level->isBelowMinimum(); @endphp
                        <tr class="transition hover:bg-gray-50 {{ $isLow ? 'bg-amber-50/40' : '' }}">
                            <td class="table-cell-base tabular font-medium text-navy-900">
                                <a href="{{ route('products.show', $level->product) }}" class="hover:text-aqua-600">{{ $level->product->sku }}</a>
                            </td>
                            <td class="table-cell-base">
                                <p class="max-w-xs truncate text-gray-900" title="{{ $level->product->name_th }}">{{ $level->product->name_th }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $level->product->category?->name_th }}</p>
                            </td>
                            <td class="table-cell-base">
                                <x-badge color="navy">{{ $level->warehouse->code }}</x-badge>
                            </td>
                            <td class="table-cell-base tabular text-end font-medium">{{ number_format((float) $level->qty_on_hand, 3) }}</td>
                            <td class="table-cell-base tabular text-end text-gray-500">{{ number_format((float) $level->qty_reserved, 3) }}</td>
                            <td class="table-cell-base tabular text-end font-semibold {{ $isLow ? 'text-amber-700' : 'text-emerald-700' }}">
                                {{ number_format((float) $level->qty_available, 3) }}
                                @if ($isLow)
                                    <span class="ms-1 text-xs font-normal">{{ __('เหลือน้อย') }}</span>
                                @endif
                            </td>
                            <td class="table-cell-base tabular text-end text-gray-400">{{ number_format((float) $level->product->min_stock, 3) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state :message="__('ไม่พบยอดคงเหลือตามเงื่อนไขที่ค้นหา')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($levels->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $levels->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
