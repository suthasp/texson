<x-app-layout>
    <x-slot name="title">{{ $customer->name_th }}</x-slot>

    <x-page-header :title="$customer->name_th"
                   :subtitle="$customer->code . ' · ' . $customer->branchLabel()"
                   :back="route('customers.index')">
        <x-slot name="actions">
            @can('update', $customer)
                <x-link-button :href="route('customers.edit', $customer)" variant="secondary">{{ __('แก้ไข') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            {{-- ── ผู้ติดต่อ ── --}}
            <x-card :padded="false">
                <x-slot name="header">
                    <h2 class="text-sm font-semibold text-navy-900">{{ __('ผู้ติดต่อ') }}</h2>
                    @can('update', $customer)
                        <a href="{{ route('customers.contacts.create', $customer) }}"
                           class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('+ เพิ่มผู้ติดต่อ') }}</a>
                    @endcan
                </x-slot>

                @forelse ($customer->contacts as $contact)
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-50 px-5 py-3 last:border-0">
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 text-sm font-medium text-gray-900">
                                {{ $contact->name }}
                                @if ($contact->is_primary)
                                    <x-badge color="aqua">{{ __('ผู้ติดต่อหลัก') }}</x-badge>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ collect([$contact->position, $contact->phone, $contact->email, $contact->line_id])->filter()->implode(' · ') ?: '—' }}
                            </p>
                        </div>

                        @can('update', $customer)
                            <div class="flex items-center gap-2">
                                <a href="{{ route('customers.contacts.edit', [$customer, $contact]) }}"
                                   class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('แก้ไข') }}</a>
                                <x-delete-button :action="route('customers.contacts.destroy', [$customer, $contact])"
                                                 :confirm="__('ยืนยันการลบผู้ติดต่อ :name?', ['name' => $contact->name])" />
                            </div>
                        @endcan
                    </div>
                @empty
                    <x-empty-state :message="__('ยังไม่มีผู้ติดต่อ')" />
                @endforelse
            </x-card>

            {{-- ── หน้างาน ── --}}
            <x-card :padded="false">
                <x-slot name="header">
                    <div>
                        <h2 class="text-sm font-semibold text-navy-900">{{ __('หน้างาน') }}</h2>
                        <p class="text-xs text-gray-500">{{ __('ใช้เลือกในใบเสนอราคา และจะผูกกับงาน PM ใน Phase 2') }}</p>
                    </div>
                    @can('update', $customer)
                        <a href="{{ route('customers.sites.create', $customer) }}"
                           class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('+ เพิ่มหน้างาน') }}</a>
                    @endcan
                </x-slot>

                @forelse ($customer->sites as $site)
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-50 px-5 py-3 last:border-0">
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 text-sm font-medium text-gray-900">
                                {{ $site->site_name }}
                                <span class="tabular text-xs text-gray-400">{{ $site->site_code }}</span>
                                @unless ($site->is_active)
                                    <x-badge>{{ __('ปิดใช้งาน') }}</x-badge>
                                @endunless
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ collect([$site->address_line, $site->province])->filter()->implode(' ') ?: '—' }}
                                @if ($site->primaryContact)
                                    · {{ __('ผู้ติดต่อ') }}: {{ $site->primaryContact->name }}
                                @endif
                            </p>
                            @if ($site->access_note)
                                <p class="mt-1 text-xs text-amber-700">{{ __('การเข้าพื้นที่') }}: {{ $site->access_note }}</p>
                            @endif
                        </div>

                        @can('update', $customer)
                            <div class="flex items-center gap-2">
                                <a href="{{ route('customers.sites.edit', [$customer, $site]) }}"
                                   class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('แก้ไข') }}</a>
                                <x-delete-button :action="route('customers.sites.destroy', [$customer, $site])"
                                                 :confirm="__('ยืนยันการลบหน้างาน :name?', ['name' => $site->site_name])" />
                            </div>
                        @endcan
                    </div>
                @empty
                    <x-empty-state :message="__('ยังไม่มีหน้างาน')" />
                @endforelse
            </x-card>
        </div>

        {{-- ── สรุปข้อมูล ── --}}
        <div class="space-y-4">
            <x-card :title="__('ข้อมูลลูกค้า')">
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        __('ชื่ออังกฤษ') => $customer->name_en,
                        __('เลขผู้เสียภาษี') => $customer->tax_id,
                        __('ที่อยู่') => $customer->fullAddress(),
                        __('โทรศัพท์') => $customer->phone,
                        __('อีเมล') => $customer->email,
                        __('เงื่อนไขการชำระเงิน') => $customer->payment_terms,
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-gray-500">{{ $label }}</dt>
                            <dd class="text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach

                    <div>
                        <dt class="text-xs text-gray-500">{{ __('ระดับราคา') }}</dt>
                        <dd class="mt-0.5"><x-badge color="aqua">{{ $customer->price_tier->label() }}</x-badge></dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500">{{ __('เครดิต') }}</dt>
                        <dd class="tabular text-gray-900">{{ $customer->credit_term_days }} {{ __('วัน') }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-gray-500">{{ __('สถานะ') }}</dt>
                        <dd class="mt-0.5">
                            <x-badge :color="$customer->is_active ? 'green' : 'gray'">
                                {{ $customer->is_active ? __('ใช้งาน') : __('ปิดใช้งาน') }}
                            </x-badge>
                        </dd>
                    </div>
                </dl>
            </x-card>

            @if ($customer->notes)
                <x-card :title="__('หมายเหตุภายใน')">
                    <p class="whitespace-pre-line text-sm text-gray-700">{{ $customer->notes }}</p>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
