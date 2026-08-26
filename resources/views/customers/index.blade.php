<x-app-layout>
    <x-slot name="title">{{ __('ลูกค้า') }}</x-slot>

    <x-page-header :title="__('ลูกค้า')"
                   :subtitle="trans_choice('พบ :count รายการ|พบ :count รายการ', $customers->total(), ['count' => number_format($customers->total())])">
        <x-slot name="actions">
            @can('create', App\Models\Customer::class)
                <x-link-button :href="route('customers.create')">{{ __('เพิ่มลูกค้า') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('customers.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('รหัส, ชื่อ, เลขผู้เสียภาษี หรือเบอร์โทร') }}"
                       class="form-input-base text-sm">
            </div>

            <div>
                <label for="price_tier" class="sr-only">{{ __('ระดับราคา') }}</label>
                <select id="price_tier" name="price_tier" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกระดับราคา') }}</option>
                    @foreach ($priceTiers as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['price_tier'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="province" class="sr-only">{{ __('จังหวัด') }}</label>
                <select id="province" name="province" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกจังหวัด') }}</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province }}" @selected(($filters['province'] ?? '') === $province)>{{ $province }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <label for="status" class="sr-only">{{ __('สถานะ') }}</label>
                <select id="status" name="status" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกสถานะ') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('ใช้งาน') }}</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('ปิดใช้งาน') }}</option>
                </select>

                <button type="submit" class="shrink-0 rounded-md bg-navy-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                    {{ __('ค้นหา') }}
                </button>
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="table-head-cell"><x-sort-link column="code" :label="__('รหัส')" /></th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="name_th" :label="__('ชื่อลูกค้า')" /></th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="province" :label="__('จังหวัด')" /></th>
                        <th scope="col" class="table-head-cell">{{ __('ระดับราคา') }}</th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="credit_term_days" :label="__('เครดิต')" /></th>
                        <th scope="col" class="table-head-cell">{{ __('ผู้ติดต่อ / หน้างาน') }}</th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('จัดการ') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($customers as $customer)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular font-medium text-navy-900">
                                <a href="{{ route('customers.show', $customer) }}" class="hover:text-aqua-600">{{ $customer->code }}</a>
                            </td>
                            <td class="table-cell-base">
                                <p class="font-medium text-gray-900">{{ $customer->name_th }}</p>
                                @if ($customer->tax_id)
                                    <p class="tabular text-xs text-gray-500">{{ $customer->tax_id }} · {{ $customer->branchLabel() }}</p>
                                @endif
                            </td>
                            <td class="table-cell-base">{{ $customer->province ?? '—' }}</td>
                            <td class="table-cell-base">
                                <x-badge :color="$customer->price_tier->value === 'project' ? 'aqua' : ($customer->price_tier->value === 'dealer' ? 'navy' : 'gray')">
                                    {{ $customer->price_tier->label() }}
                                </x-badge>
                            </td>
                            <td class="table-cell-base tabular">{{ $customer->credit_term_days }} {{ __('วัน') }}</td>
                            <td class="table-cell-base tabular text-gray-500">{{ $customer->contacts_count }} / {{ $customer->sites_count }}</td>
                            <td class="table-cell-base">
                                <x-badge :color="$customer->is_active ? 'green' : 'gray'">
                                    {{ $customer->is_active ? __('ใช้งาน') : __('ปิดใช้งาน') }}
                                </x-badge>
                            </td>
                            <td class="table-cell-base text-end">
                                <div class="flex items-center justify-end gap-2">
                                    @can('update', $customer)
                                        <a href="{{ route('customers.edit', $customer) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('แก้ไข') }}</a>
                                    @endcan
                                    @can('delete', $customer)
                                        <x-delete-button :action="route('customers.destroy', $customer)"
                                                         :confirm="__('ยืนยันการลบลูกค้า :name?', ['name' => $customer->name_th])" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state :message="__('ไม่พบลูกค้าตามเงื่อนไขที่ค้นหา')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $customers->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
