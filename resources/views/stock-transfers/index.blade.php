<x-app-layout>
    <x-slot name="title">{{ __('ใบโอนคลัง') }}</x-slot>

    <x-page-header :title="__('ใบโอนคลัง')" :subtitle="__('พบ :count ใบ', ['count' => number_format($transfers->total())])">
        <x-slot name="actions">
            @can('create', App\Models\StockTransfer::class)
                <x-link-button :href="route('stock-transfers.create')">{{ __('สร้างใบโอน') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('stock-transfers.index') }}" class="flex flex-wrap gap-3">
            <div class="min-w-0 flex-1 sm:max-w-sm">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('เลขที่ใบโอน') }}" class="form-input-base text-sm">
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
                        <th scope="col" class="table-head-cell">{{ __('วันที่โอน') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('จากคลัง') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ไปคลัง') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('รายการ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ผู้สร้าง') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($transfers as $transfer)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">
                                <a href="{{ route('stock-transfers.show', $transfer) }}" class="hover:text-aqua-600">{{ $transfer->transfer_no }}</a>
                            </td>
                            <td class="table-cell-base tabular">{{ $transfer->transfer_date->translatedFormat('d M Y') }}</td>
                            <td class="table-cell-base"><x-badge>{{ $transfer->fromWarehouse->code }}</x-badge></td>
                            <td class="table-cell-base"><x-badge color="navy">{{ $transfer->toWarehouse->code }}</x-badge></td>
                            <td class="table-cell-base tabular text-end">{{ $transfer->items_count }}</td>
                            <td class="table-cell-base"><x-badge :color="$transfer->status->badgeColor()">{{ $transfer->status->label() }}</x-badge></td>
                            <td class="table-cell-base text-xs text-gray-500">{{ $transfer->creator?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :message="__('ยังไม่มีใบโอนคลัง')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($transfers->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $transfers->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
