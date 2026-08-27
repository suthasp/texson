<x-app-layout>
    <x-slot name="title">{{ __('ใบปรับปรุงสต็อก') }}</x-slot>

    <x-page-header :title="__('ใบปรับปรุงสต็อก')" :subtitle="__('พบ :count ใบ', ['count' => number_format($adjustments->total())])">
        <x-slot name="actions">
            @can('create', App\Models\StockAdjustment::class)
                <x-link-button :href="route('stock-adjustments.create')">{{ __('สร้างใบปรับปรุง') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('stock-adjustments.index') }}" class="flex flex-wrap gap-3">
            <div class="min-w-0 flex-1 sm:max-w-xs">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('เลขที่ใบปรับปรุง') }}" class="form-input-base text-sm">
            </div>
            <div>
                <label for="reason" class="sr-only">{{ __('เหตุผล') }}</label>
                <select id="reason" name="reason" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกเหตุผล') }}</option>
                    @foreach ($reasons as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['reason'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
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
                        <th scope="col" class="table-head-cell">{{ __('วันที่') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('คลัง') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('เหตุผล') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('รายการ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ผู้สร้าง') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($adjustments as $adjustment)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">
                                <a href="{{ route('stock-adjustments.show', $adjustment) }}" class="hover:text-aqua-600">{{ $adjustment->adjust_no }}</a>
                            </td>
                            <td class="table-cell-base tabular">{{ $adjustment->adjusted_at->translatedFormat('d M Y') }}</td>
                            <td class="table-cell-base"><x-badge color="navy">{{ $adjustment->warehouse->code }}</x-badge></td>
                            <td class="table-cell-base text-xs">{{ $adjustment->reason->label() }}</td>
                            <td class="table-cell-base tabular text-end">{{ $adjustment->items_count }}</td>
                            <td class="table-cell-base"><x-badge :color="$adjustment->status->badgeColor()">{{ $adjustment->status->label() }}</x-badge></td>
                            <td class="table-cell-base text-xs text-gray-500">{{ $adjustment->creator?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :message="__('ยังไม่มีใบปรับปรุงสต็อก')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($adjustments->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $adjustments->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
