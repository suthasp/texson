<x-app-layout>
    <x-slot name="title">{{ __('ใบรับสินค้า') }}</x-slot>

    <x-page-header :title="__('ใบรับสินค้า')" :subtitle="__('พบ :count ใบ', ['count' => number_format($receipts->total())])">
        <x-slot name="actions">
            @can('create', App\Models\GoodsReceipt::class)
                <x-link-button :href="route('goods-receipts.create')">{{ __('รับสินค้าเข้า') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('goods-receipts.index') }}" class="flex flex-wrap gap-3">
            <div class="min-w-0 flex-1 sm:max-w-sm">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('เลขที่ใบ หรือเลขอ้างอิงของผู้ขาย') }}" class="form-input-base text-sm">
            </div>

            <div>
                <label for="status" class="sr-only">{{ __('สถานะ') }}</label>
                <select id="status" name="status" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกสถานะ') }}</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="warehouse_id" class="sr-only">{{ __('คลัง') }}</label>
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
                        <th scope="col" class="table-head-cell">{{ __('วันที่รับ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ผู้ขาย') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('คลัง') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('รายการ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ผู้สร้าง') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($receipts as $receipt)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">
                                <a href="{{ route('goods-receipts.show', $receipt) }}" class="hover:text-aqua-600">{{ $receipt->receipt_no }}</a>
                                @if ($receipt->reference_no)
                                    <p class="text-xs font-normal text-gray-400">{{ $receipt->reference_no }}</p>
                                @endif
                            </td>
                            <td class="table-cell-base tabular">{{ $receipt->received_date->translatedFormat('d M Y') }}</td>
                            <td class="table-cell-base">
                                <p class="max-w-xs truncate">{{ $receipt->supplier?->name ?? '—' }}</p>
                            </td>
                            <td class="table-cell-base"><x-badge color="navy">{{ $receipt->warehouse->code }}</x-badge></td>
                            <td class="table-cell-base tabular text-end">{{ $receipt->items_count }}</td>
                            <td class="table-cell-base">
                                <x-badge :color="$receipt->status->badgeColor()">{{ $receipt->status->label() }}</x-badge>
                            </td>
                            <td class="table-cell-base text-xs text-gray-500">{{ $receipt->creator?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :message="__('ยังไม่มีใบรับสินค้า')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($receipts->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $receipts->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
