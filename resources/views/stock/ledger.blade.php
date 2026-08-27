<x-app-layout>
    <x-slot name="title">{{ __('ประวัติการเคลื่อนไหวสต็อก') }}</x-slot>

    <x-page-header :title="__('ประวัติการเคลื่อนไหวสต็อก')"
                   :subtitle="__('บันทึกแบบ append-only — แก้ไขหรือลบรายการเดิมไม่ได้ การแก้ยอดต้องออกใบปรับปรุงใหม่')"
                   :back="route('stock.index')" />

    <x-card class="mb-4">
        <form method="GET" action="{{ route('stock.ledger') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="warehouse_id" class="mb-1 block text-xs text-gray-600">{{ __('คลัง') }}</label>
                <select id="warehouse_id" name="warehouse_id" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกคลัง') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse_id'] ?? '') === (string) $warehouse->id)>
                            {{ $warehouse->code }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="type" class="mb-1 block text-xs text-gray-600">{{ __('ประเภท') }}</label>
                <select id="type" name="type" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกประเภท') }}</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
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

            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                    {{ __('กรอง') }}
                </button>
                <x-link-button :href="route('stock.ledger')" variant="secondary">{{ __('ล้าง') }}</x-link-button>
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="table-head-cell">{{ __('วันเวลา') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สินค้า') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('คลัง') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ประเภท') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('จำนวน') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('ยอดหลังรายการ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('เอกสาร / หมายเหตุ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ผู้ทำรายการ') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($movements as $movement)
                        @php $qty = (float) $movement->qty; @endphp
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular text-xs text-gray-500">
                                {{ $movement->moved_at->translatedFormat('d M Y H:i') }}
                            </td>
                            <td class="table-cell-base">
                                <a href="{{ route('products.show', $movement->product) }}" class="tabular font-medium text-navy-900 hover:text-aqua-600">
                                    {{ $movement->product->sku }}
                                </a>
                                <p class="max-w-xs truncate text-xs text-gray-500">{{ $movement->product->name_th }}</p>
                            </td>
                            <td class="table-cell-base"><x-badge color="navy">{{ $movement->warehouse->code }}</x-badge></td>
                            <td class="table-cell-base">
                                <x-badge :color="$movement->type->badgeColor()">{{ $movement->type->label() }}</x-badge>
                            </td>
                            <td class="table-cell-base tabular text-end font-medium {{ $qty < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                                {{ $qty > 0 ? '+' : '' }}{{ number_format($qty, 3) }}
                            </td>
                            <td class="table-cell-base tabular text-end text-gray-700">{{ number_format((float) $movement->balance_after, 3) }}</td>
                            <td class="table-cell-base">
                                <p class="max-w-xs truncate text-xs text-gray-600" title="{{ $movement->note }}">{{ $movement->note ?: '—' }}</p>
                                @if ($movement->lot_no)
                                    <p class="tabular text-xs text-gray-400">Lot: {{ $movement->lot_no }}</p>
                                @endif
                            </td>
                            <td class="table-cell-base text-xs text-gray-500">{{ $movement->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state :message="__('ยังไม่มีการเคลื่อนไหวตามเงื่อนไขที่กรอง')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($movements->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $movements->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
