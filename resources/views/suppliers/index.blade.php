<x-app-layout>
    <x-slot name="title">{{ __('ผู้ขาย') }}</x-slot>

    <x-page-header :title="__('ผู้ขาย')" :subtitle="__('พบ :count รายการ', ['count' => number_format($suppliers->total())])">
        <x-slot name="actions">
            @can('create', App\Models\Supplier::class)
                <x-link-button :href="route('suppliers.create')">{{ __('เพิ่มผู้ขาย') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('suppliers.index') }}" class="flex flex-wrap gap-3">
            <div class="min-w-0 flex-1 sm:max-w-sm">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('รหัส, ชื่อ, ผู้ติดต่อ หรือเบอร์โทร') }}" class="form-input-base text-sm">
            </div>

            <div>
                <label for="status" class="sr-only">{{ __('สถานะ') }}</label>
                <select id="status" name="status" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกสถานะ') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('ใช้งาน') }}</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('ปิดใช้งาน') }}</option>
                </select>
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
                        <th scope="col" class="table-head-cell"><x-sort-link column="code" :label="__('รหัส')" /></th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="name" :label="__('ชื่อผู้ขาย')" /></th>
                        <th scope="col" class="table-head-cell">{{ __('ผู้ติดต่อ') }}</th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="lead_time_days" :label="__('Lead time')" /></th>
                        <th scope="col" class="table-head-cell text-end">{{ __('สินค้า') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('จัดการ') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($suppliers as $supplier)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="hover:text-aqua-600">{{ $supplier->code }}</a>
                            </td>
                            <td class="table-cell-base font-medium text-gray-900">{{ $supplier->name }}</td>
                            <td class="table-cell-base text-xs text-gray-500">
                                {{ collect([$supplier->contact_name, $supplier->phone])->filter()->implode(' · ') ?: '—' }}
                            </td>
                            <td class="table-cell-base tabular">{{ $supplier->lead_time_days }} {{ __('วัน') }}</td>
                            <td class="table-cell-base tabular text-end">{{ $supplier->products_count }}</td>
                            <td class="table-cell-base">
                                <x-badge :color="$supplier->is_active ? 'green' : 'gray'">
                                    {{ $supplier->is_active ? __('ใช้งาน') : __('ปิดใช้งาน') }}
                                </x-badge>
                            </td>
                            <td class="table-cell-base text-end">
                                <div class="flex items-center justify-end gap-2">
                                    @can('update', $supplier)
                                        <a href="{{ route('suppliers.edit', $supplier) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('แก้ไข') }}</a>
                                    @endcan
                                    @can('delete', $supplier)
                                        <x-delete-button :action="route('suppliers.destroy', $supplier)"
                                                         :confirm="__('ยืนยันการลบผู้ขาย :name?', ['name' => $supplier->name])" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :message="__('ไม่พบผู้ขายตามเงื่อนไขที่ค้นหา')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($suppliers->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $suppliers->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
