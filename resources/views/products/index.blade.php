<x-app-layout>
    <x-slot name="title">{{ __('สินค้า / อะไหล่') }}</x-slot>

    <x-page-header :title="__('สินค้า / อะไหล่')"
                   :subtitle="__('พบ :count รายการ', ['count' => number_format($products->total())])">
        <x-slot name="actions">
            @can('create', App\Models\Product::class)
                <x-link-button :href="route('products.create')">{{ __('เพิ่มสินค้า') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('products.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('SKU, part number, ชื่อ หรือรุ่น') }}"
                       class="form-input-base text-sm">
            </div>

            <div>
                <label for="category_id" class="sr-only">{{ __('หมวดหมู่') }}</label>
                <select id="category_id" name="category_id" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกหมวดหมู่') }}</option>
                    @foreach ($categories as $root)
                        <option value="{{ $root->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $root->id)>
                            {{ $root->name_th }}
                        </option>
                        @foreach ($root->children as $child)
                            <option value="{{ $child->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $child->id)>
                                &nbsp;&nbsp;— {{ $child->name_th }}
                            </option>
                        @endforeach
                    @endforeach
                </select>
            </div>

            <div>
                <label for="brand_id" class="sr-only">{{ __('ยี่ห้อ') }}</label>
                <select id="brand_id" name="brand_id" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกยี่ห้อ') }}</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected((string) ($filters['brand_id'] ?? '') === (string) $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="sr-only">{{ __('สถานะ') }}</label>
                <select id="status" name="status" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกสถานะ') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('ใช้งาน') }}</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('ปิดใช้งาน') }}</option>
                </select>
            </div>

            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="serialized" value="1" @checked(! empty($filters['serialized']))
                           class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                    {{ __('มี Serial') }}
                </label>

                <button type="submit" class="shrink-0 rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
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
                        <th scope="col" class="table-head-cell"><x-sort-link column="sku" :label="__('SKU')" /></th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="name_th" :label="__('ชื่อสินค้า')" /></th>
                        <th scope="col" class="table-head-cell">{{ __('หมวด / ยี่ห้อ') }}</th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="model" :label="__('รุ่น')" /></th>
                        <th scope="col" class="table-head-cell">{{ __('หน่วย') }}</th>
                        @if ($canViewCost)
                            <th scope="col" class="table-head-cell text-end"><x-sort-link column="cost_price" :label="__('ทุน')" /></th>
                        @endif
                        <th scope="col" class="table-head-cell text-end"><x-sort-link column="list_price" :label="__('ราคาขาย')" /></th>
                        <th scope="col" class="table-head-cell text-end">{{ __('จัดการ') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($products as $product)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">
                                <a href="{{ route('products.show', $product) }}" class="hover:text-aqua-600">{{ $product->sku }}</a>
                            </td>
                            <td class="table-cell-base">
                                <p class="max-w-xs truncate font-medium text-gray-900" title="{{ $product->name_th }}">{{ $product->name_th }}</p>
                                <div class="mt-0.5 flex flex-wrap gap-1">
                                    @unless ($product->is_active)
                                        <x-badge>{{ __('ปิดใช้งาน') }}</x-badge>
                                    @endunless
                                    @if ($product->is_serialized)
                                        <x-badge color="navy">{{ __('Serial') }}</x-badge>
                                    @endif
                                    @if ($product->track_lot)
                                        <x-badge color="amber">{{ __('Lot') }}</x-badge>
                                    @endif
                                </div>
                            </td>
                            <td class="table-cell-base text-xs text-gray-500">
                                {{ $product->category?->name_th ?? '—' }}<br>
                                {{ $product->brand?->name ?? '—' }}
                            </td>
                            <td class="table-cell-base">{{ $product->model ?? '—' }}</td>
                            <td class="table-cell-base">{{ $product->uom->label() }}</td>
                            @if ($canViewCost)
                                <td class="table-cell-base tabular text-end text-gray-500">{{ number_format((float) $product->cost_price, 2) }}</td>
                            @endif
                            <td class="table-cell-base tabular text-end font-medium text-gray-900">{{ number_format((float) $product->list_price, 2) }}</td>
                            <td class="table-cell-base text-end">
                                <div class="flex items-center justify-end gap-2">
                                    @can('update', $product)
                                        <a href="{{ route('products.edit', $product) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('แก้ไข') }}</a>
                                    @endcan
                                    @can('delete', $product)
                                        <x-delete-button :action="route('products.destroy', $product)"
                                                         :confirm="__('ยืนยันการลบสินค้า :sku?', ['sku' => $product->sku])" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canViewCost ? 8 : 7 }}">
                                <x-empty-state :message="__('ไม่พบสินค้าตามเงื่อนไขที่ค้นหา')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $products->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
