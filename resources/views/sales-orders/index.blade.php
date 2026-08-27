<x-app-layout>
    <x-slot name="title">{{ __('ใบสั่งขาย') }}</x-slot>

    <x-page-header :title="__('ใบสั่งขาย')" :subtitle="__('พบ :count ใบ', ['count' => number_format($orders->total())])" />

    <x-card class="mb-4">
        <form method="GET" action="{{ route('sales-orders.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1 sm:max-w-xs">
                <label for="q" class="mb-1 block text-xs text-gray-600">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('เลขที่ใบ, PO ลูกค้า หรือชื่อลูกค้า') }}" class="form-input-base text-sm">
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

            <label class="flex items-center gap-2 pb-2 text-sm text-gray-700">
                <input type="checkbox" name="open" value="1" @checked($filters['open'] ?? false)
                       class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                {{ __('เฉพาะใบที่ยังต้องส่งของ') }}
            </label>

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
                        <th scope="col" class="table-head-cell">{{ __('ลูกค้า') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('วันที่สั่ง') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('คลัง') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('ยอดสุทธิ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ผู้ขาย') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($orders as $order)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">
                                <a href="{{ route('sales-orders.show', $order) }}" class="hover:text-aqua-600">{{ $order->so_no }}</a>
                                @if ($order->customer_po_no)
                                    <p class="text-xs font-normal text-gray-400">PO: {{ $order->customer_po_no }}</p>
                                @endif
                            </td>
                            <td class="table-cell-base">
                                <p class="max-w-xs truncate">{{ $order->customer->name_th }}</p>
                                <p class="tabular text-xs text-gray-400">{{ $order->customer->code }}</p>
                            </td>
                            <td class="table-cell-base tabular">{{ $order->order_date->translatedFormat('d M Y') }}</td>
                            <td class="table-cell-base"><x-badge color="navy">{{ $order->warehouse->code }}</x-badge></td>
                            <td class="table-cell-base tabular text-end font-medium">{{ number_format((float) $order->grand_total, 2) }}</td>
                            <td class="table-cell-base">
                                <x-badge :color="$order->status->badgeColor()">{{ $order->status->label() }}</x-badge>
                            </td>
                            <td class="table-cell-base text-xs text-gray-500">{{ $order->salesUser->name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state :message="__('ยังไม่มีใบสั่งขาย — สร้างได้จากใบเสนอราคาที่ลูกค้าตอบรับแล้ว')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($orders->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $orders->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
