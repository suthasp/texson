<x-app-layout>
    <x-slot name="title">{{ __('ทะเบียน Serial') }}</x-slot>

    <x-page-header :title="__('ทะเบียน Serial')"
                   :subtitle="__('พบ :count ชิ้น · สถานะเปลี่ยนจากเอกสารเท่านั้น แก้มือไม่ได้', ['count' => number_format($serials->total())])" />

    <x-card class="mb-4">
        <form method="GET" action="{{ route('serial-numbers.index') }}" class="flex flex-wrap gap-3">
            <div class="min-w-0 flex-1 sm:max-w-sm">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('Serial หรือ SKU') }}" class="form-input-base text-sm">
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

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="expiring" value="1" @checked(! empty($filters['expiring']))
                       class="h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                {{ __('ประกันหมดใน 90 วัน') }}
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
                        <th scope="col" class="table-head-cell">{{ __('Serial') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สินค้า') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('คลัง') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ลูกค้า') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('ประกันถึง') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($serials as $serial)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">{{ $serial->serial_no }}</td>
                            <td class="table-cell-base">
                                <a href="{{ route('products.show', $serial->product) }}" class="tabular text-xs text-aqua-600 hover:text-aqua-700">{{ $serial->product->sku }}</a>
                                <p class="max-w-xs truncate text-gray-700">{{ $serial->product->name_th }}</p>
                            </td>
                            <td class="table-cell-base">
                                @if ($serial->warehouse)
                                    <x-badge color="navy">{{ $serial->warehouse->code }}</x-badge>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="table-cell-base">
                                <x-badge :color="$serial->status->badgeColor()">{{ $serial->status->label() }}</x-badge>
                            </td>
                            <td class="table-cell-base text-xs text-gray-500">{{ $serial->customer?->name_th ?? '—' }}</td>
                            <td class="table-cell-base tabular text-xs">
                                @if ($serial->warranty_end)
                                    <span class="{{ $serial->isUnderWarranty() ? 'text-emerald-700' : 'text-rose-600' }}">
                                        {{ $serial->warranty_end->translatedFormat('d M Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state :message="__('ยังไม่มี serial ในระบบ — serial จะถูกสร้างตอน post ใบรับสินค้า')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($serials->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $serials->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
