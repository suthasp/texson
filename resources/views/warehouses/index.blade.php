<x-app-layout>
    <x-slot name="title">{{ __('คลังสินค้า') }}</x-slot>

    <x-page-header :title="__('คลังสินค้า')" :subtitle="__('ยอดคงเหลือรายคลังจะเปิดใช้ใน Phase 2')">
        <x-slot name="actions">
            @can('create', App\Models\Warehouse::class)
                <x-link-button :href="route('warehouses.create')">{{ __('เพิ่มคลัง') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="table-head-cell">{{ __('รหัส') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ชื่อคลัง') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ที่อยู่') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('จัดการ') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($warehouses as $warehouse)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">{{ $warehouse->code }}</td>
                            <td class="table-cell-base">
                                <span class="font-medium text-gray-900">{{ $warehouse->name }}</span>
                                @if ($warehouse->is_default)
                                    <x-badge color="aqua" class="ms-2">{{ __('คลังเริ่มต้น') }}</x-badge>
                                @endif
                            </td>
                            <td class="table-cell-base text-xs text-gray-500">{{ $warehouse->address ?: '—' }}</td>
                            <td class="table-cell-base">
                                <x-badge :color="$warehouse->is_active ? 'green' : 'gray'">
                                    {{ $warehouse->is_active ? __('ใช้งาน') : __('ปิดใช้งาน') }}
                                </x-badge>
                            </td>
                            <td class="table-cell-base text-end">
                                <div class="flex items-center justify-end gap-2">
                                    @can('update', $warehouse)
                                        <a href="{{ route('warehouses.edit', $warehouse) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('แก้ไข') }}</a>
                                    @endcan
                                    @can('delete', $warehouse)
                                        <x-delete-button :action="route('warehouses.destroy', $warehouse)"
                                                         :confirm="__('ยืนยันการลบคลัง :name?', ['name' => $warehouse->name])" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state :message="__('ยังไม่มีคลังสินค้า')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
