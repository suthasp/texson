<x-app-layout>
    <x-slot name="title">{{ __('ยี่ห้อ') }}</x-slot>

    <x-page-header :title="__('ยี่ห้อ')" :subtitle="__('พบ :count รายการ', ['count' => number_format($brands->total())])">
        <x-slot name="actions">
            @can('create', App\Models\Brand::class)
                <x-link-button :href="route('brands.create')">{{ __('เพิ่มยี่ห้อ') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('brands.index') }}" class="flex flex-wrap gap-3">
            <div class="min-w-0 flex-1 sm:max-w-sm">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('ชื่อยี่ห้อ') }}" class="form-input-base text-sm">
            </div>
            <button type="submit" class="rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                {{ __('ค้นหา') }}
            </button>
        </form>
    </x-card>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="table-head-cell">{{ __('ชื่อยี่ห้อ') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('สินค้า') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('จัดการ') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($brands as $brand)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base font-medium text-gray-900">{{ $brand->name }}</td>
                            <td class="table-cell-base tabular text-end">{{ $brand->products_count }}</td>
                            <td class="table-cell-base">
                                <x-badge :color="$brand->is_active ? 'green' : 'gray'">
                                    {{ $brand->is_active ? __('ใช้งาน') : __('ปิดใช้งาน') }}
                                </x-badge>
                            </td>
                            <td class="table-cell-base text-end">
                                <div class="flex items-center justify-end gap-2">
                                    @can('update', $brand)
                                        <a href="{{ route('brands.edit', $brand) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('แก้ไข') }}</a>
                                    @endcan
                                    @can('delete', $brand)
                                        <x-delete-button :action="route('brands.destroy', $brand)"
                                                         :confirm="__('ยืนยันการลบยี่ห้อ :name?', ['name' => $brand->name])" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state :message="__('ยังไม่มียี่ห้อ')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($brands->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $brands->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
