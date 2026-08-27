<x-app-layout>
    <x-slot name="title">{{ __('ใบเสนอราคา') }}</x-slot>

    <x-page-header :title="__('ใบเสนอราคา')" :subtitle="__('พบ :count ใบ', ['count' => number_format($quotations->total())])">
        <x-slot name="actions">
            @can('create', App\Models\Quotation::class)
                <x-link-button :href="route('quotations.create')">{{ __('สร้างใบเสนอราคา') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('quotations.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1 sm:max-w-xs">
                <label for="q" class="mb-1 block text-xs text-gray-600">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('เลขที่ใบ หรือชื่อลูกค้า') }}" class="form-input-base text-sm">
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
                <label for="from" class="mb-1 block text-xs text-gray-600">{{ __('ตั้งแต่วันที่') }}</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-input-base text-sm">
            </div>

            <div>
                <label for="to" class="mb-1 block text-xs text-gray-600">{{ __('ถึงวันที่') }}</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-input-base text-sm">
            </div>

            <label class="flex items-center gap-2 pb-2 text-sm text-gray-700">
                <input type="checkbox" name="expiring" value="1" @checked($filters['expiring'] ?? false)
                       class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                {{ __('ใกล้หมดอายุใน 7 วัน') }}
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
                        <th scope="col" class="table-head-cell">{{ __('วันที่ออก') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ยืนราคาถึง') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('ยอดสุทธิ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ผู้ขาย') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($quotations as $quotation)
                        @php $daysLeft = $quotation->daysUntilExpiry(); @endphp
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">
                                <a href="{{ route('quotations.show', $quotation) }}" class="hover:text-aqua-600">{{ $quotation->displayNo() }}</a>
                                @if ($quotation->isSuperseded())
                                    <span class="ms-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-normal text-gray-500">{{ __('ถูกแทนที่') }}</span>
                                @endif
                            </td>
                            <td class="table-cell-base">
                                <p class="max-w-xs truncate">{{ $quotation->customer->name_th }}</p>
                                <p class="tabular text-xs text-gray-400">{{ $quotation->customer->code }}</p>
                            </td>
                            <td class="table-cell-base tabular">{{ $quotation->issue_date->translatedFormat('d M Y') }}</td>
                            <td class="table-cell-base tabular">
                                {{ $quotation->valid_until->translatedFormat('d M Y') }}
                                @if ($quotation->status === App\Enums\QuotationStatus::Sent && $daysLeft >= 0 && $daysLeft <= 7)
                                    <span class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] text-amber-800">{{ __('เหลือ :days วัน', ['days' => $daysLeft]) }}</span>
                                @endif
                            </td>
                            <td class="table-cell-base tabular text-end font-medium">{{ number_format((float) $quotation->grand_total, 2) }}</td>
                            <td class="table-cell-base">
                                <x-badge :color="$quotation->status->badgeColor()">{{ $quotation->status->label() }}</x-badge>
                                @if ($quotation->status === App\Enums\QuotationStatus::PendingApproval && $quotation->approved_at)
                                    <x-badge color="green" class="ms-1">{{ __('อนุมัติแล้ว') }}</x-badge>
                                @endif
                            </td>
                            <td class="table-cell-base text-xs text-gray-500">{{ $quotation->salesUser->name }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state :message="__('ยังไม่มีใบเสนอราคา')">
                                    <x-slot name="action">
                                        @can('create', App\Models\Quotation::class)
                                            <x-link-button :href="route('quotations.create')">{{ __('สร้างใบแรก') }}</x-link-button>
                                        @endcan
                                    </x-slot>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($quotations->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $quotations->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
