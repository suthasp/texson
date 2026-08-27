<x-app-layout>
    <x-slot name="title">{{ __('ใบส่งของ') }}</x-slot>

    <x-page-header :title="__('ใบส่งของ')" :subtitle="__('พบ :count ใบ', ['count' => number_format($deliveries->total())])" />

    <x-card class="mb-4">
        <form method="GET" action="{{ route('deliveries.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1 sm:max-w-xs">
                <label for="q" class="mb-1 block text-xs text-gray-600">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('เลขที่ใบส่งของ, ใบสั่งขาย หรือชื่อผู้รับ') }}" class="form-input-base text-sm">
            </div>

            <div>
                <label for="status" class="mb-1 block text-xs text-gray-600">{{ __('สถานะ') }}</label>
                <select id="status" name="status" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกสถานะ') }}</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="warehouse_id" class="mb-1 block text-xs text-gray-600">{{ __('คลัง') }}</label>
                <select id="warehouse_id" name="warehouse_id" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกคลัง') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->code }}</option>
                    @endforeach
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
                        <th scope="col" class="table-head-cell">{{ __('เลขที่') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ใบสั่งขาย') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ลูกค้า') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('วันที่ส่ง') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('คลัง') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('รายการ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($deliveries as $delivery)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">
                                <a href="{{ route('deliveries.show', $delivery) }}" class="hover:text-aqua-600">{{ $delivery->delivery_no }}</a>
                            </td>
                            <td class="table-cell-base tabular">
                                <a href="{{ route('sales-orders.show', $delivery->sales_order_id) }}" class="text-aqua-600 hover:underline">
                                    {{ $delivery->salesOrder->so_no }}
                                </a>
                            </td>
                            <td class="table-cell-base">
                                <p class="max-w-xs truncate">{{ $delivery->salesOrder->customer->name_th }}</p>
                            </td>
                            <td class="table-cell-base tabular">{{ $delivery->delivery_date->translatedFormat('d M Y') }}</td>
                            <td class="table-cell-base"><x-badge color="navy">{{ $delivery->warehouse->code }}</x-badge></td>
                            <td class="table-cell-base tabular text-end">{{ $delivery->items_count }}</td>
                            <td class="table-cell-base">
                                <x-badge :color="$delivery->status->badgeColor()">{{ $delivery->status->label() }}</x-badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state :message="__('ยังไม่มีใบส่งของ — ออกได้จากใบสั่งขายที่ยืนยันแล้ว')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($deliveries->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $deliveries->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
