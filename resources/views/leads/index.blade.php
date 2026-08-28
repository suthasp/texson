<x-app-layout>
    <x-slot name="title">{{ __('คำขอติดต่อ') }}</x-slot>

    <x-page-header :title="__('คำขอติดต่อจากหน้าเว็บ')"
                   :subtitle="__('คำขอที่ยังไม่ปิด :count เรื่อง', ['count' => number_format($openCount)])" />

    <x-card class="mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[14rem] flex-1">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('ชื่อ บริษัท เบอร์โทร หรือข้อความ') }}"
                       class="form-input-base w-full text-sm">
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

            <label class="flex shrink-0 items-center gap-2 whitespace-nowrap text-sm text-gray-600">
                <input type="checkbox" name="open" value="1" @checked($filters['open'] ?? false)
                       class="rounded border-gray-300 text-navy-900 focus:ring-aqua-500">
                {{ __('เฉพาะที่ยังไม่ปิด') }}
            </label>

            <button type="submit" class="shrink-0 rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                {{ __('ค้นหา') }}
            </button>
        </form>
    </x-card>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="table-head-cell"><x-sort-link column="created_at" :label="__('วันที่')" /></th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="name" :label="__('ผู้ติดต่อ')" /></th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="company" :label="__('บริษัท')" /></th>
                        <th scope="col" class="table-head-cell">{{ __('บริการที่สนใจ') }}</th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="status" :label="__('สถานะ')" /></th>
                        <th scope="col" class="table-head-cell">{{ __('ผู้ดูแล') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($leads as $lead)
                        <tr class="transition hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                {{ $lead->created_at?->translatedFormat('j M Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('leads.show', $lead) }}" class="font-medium text-navy-900 hover:text-aqua-600">
                                    {{ $lead->name }}
                                </a>
                                <div class="text-xs text-gray-500">{{ $lead->contact }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $lead->company ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $lead->service_interest?->label() ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <x-badge :color="$lead->status->badgeColor()">{{ $lead->status->label() }}</x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $lead->handler?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state :message="__('ยังไม่มีคำขอติดต่อเข้ามา')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($leads->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $leads->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
