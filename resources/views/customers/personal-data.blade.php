<x-app-layout>
    <x-slot name="title">{{ __('ข้อมูลส่วนบุคคล') }} · {{ $customer->code }}</x-slot>

    <x-page-header :title="__('ข้อมูลส่วนบุคคล (PDPA)')"
                   :subtitle="$customer->code . ' · ' . $customer->name_th"
                   :back="route('customers.show', $customer)">
        <x-slot name="actions">
            <x-link-button :href="route('customers.personal-data.download', $customer)" variant="secondary">
                {{ __('ดาวน์โหลดสำเนา (JSON)') }}
            </x-link-button>
        </x-slot>
    </x-page-header>

    @if ($customer->isAnonymized())
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ __('ข้อมูลส่วนบุคคลของลูกค้ารายนี้ถูกลบตามคำขอเมื่อ :at แล้ว ที่เหลือคือตัวเลขที่เอกสารภาษีอ้างถึงเท่านั้น', [
                'at' => $customer->anonymized_at?->translatedFormat('j F Y H:i'),
            ]) }}
        </div>
    @elseif ($customer->trashed())
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
            <span>{{ __('ลูกค้ารายนี้ถูกลบไปแล้ว (soft delete) ข้อมูลยังอยู่ครบและกู้กลับมาได้') }}</span>

            @can('restore', $customer)
                <form method="POST" action="{{ route('customers.restore', $customer) }}">
                    @csrf
                    <x-secondary-button type="submit">{{ __('กู้คืน') }}</x-secondary-button>
                </form>
            @endcan
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            {{-- ── ข้อมูลที่ระบบเก็บไว้ ── --}}
            <x-card :title="__('ข้อมูลที่ระบบเก็บไว้')">
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    @foreach ([
                        __('ชื่อ (ไทย)') => $data['customer']['name_th'],
                        __('ชื่อ (อังกฤษ)') => $data['customer']['name_en'],
                        __('เลขประจำตัวผู้เสียภาษี') => $data['customer']['tax_id'],
                        __('ที่อยู่') => $data['customer']['address'],
                        __('โทรศัพท์') => $data['customer']['phone'],
                        __('อีเมล') => $data['customer']['email'],
                        __('บันทึกภายใน') => $data['customer']['notes'],
                        __('สร้างเมื่อ') => $data['customer']['created_at'],
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-gray-500">{{ $label }}</dt>
                            <dd class="text-sm text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>

            {{-- ── ผู้ติดต่อ ── --}}
            <x-card :title="__('ผู้ติดต่อ (:count คน)', ['count' => count($data['contacts'])])" :padded="false">
                @forelse ($data['contacts'] as $contact)
                    <div class="border-b border-gray-50 px-5 py-3 last:border-0">
                        <p class="text-sm font-medium text-gray-900">{{ $contact['name'] }}</p>
                        <p class="text-xs text-gray-500">
                            {{ collect([$contact['position'], $contact['phone'], $contact['email'], $contact['line_id']])->filter()->implode(' · ') ?: '—' }}
                        </p>
                    </div>
                @empty
                    <x-empty-state :message="__('ไม่มีข้อมูลผู้ติดต่อ')" />
                @endforelse
            </x-card>

            {{-- ── หน้างาน ── --}}
            <x-card :title="__('หน้างาน (:count แห่ง)', ['count' => count($data['sites'])])" :padded="false">
                @forelse ($data['sites'] as $site)
                    <div class="border-b border-gray-50 px-5 py-3 last:border-0">
                        <p class="text-sm font-medium text-gray-900">{{ $site['site_code'] }} · {{ $site['site_name'] }}</p>
                        <p class="text-xs text-gray-500">
                            {{ collect([$site['address_line'], $site['province'], $site['primary_contact']])->filter()->implode(' · ') ?: '—' }}
                        </p>
                    </div>
                @empty
                    <x-empty-state :message="__('ไม่มีหน้างานที่บันทึกไว้')" />
                @endforelse
            </x-card>

            {{-- ── ประวัติการเข้าถึง ── --}}
            <x-card :title="__('ใครเข้าถึงข้อมูลนี้บ้าง')" :padded="false">
                <x-slot name="header">
                    <p class="text-xs text-gray-500">{{ __('การเปิดดูถูกยุบเป็นวันละครั้งต่อคน ส่วนการส่งออกบันทึกทุกครั้ง') }}</p>
                </x-slot>

                @forelse ($accessLog as $entry)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-50 px-5 py-2.5 text-sm last:border-0">
                        <div class="min-w-0">
                            <span class="font-medium text-gray-900">{{ $entry->causer?->name ?? __('ระบบ') }}</span>
                            <span class="text-gray-500">· {{ $entry->description }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            @if ($entry->properties->get('ip'))
                                <span class="font-mono">{{ $entry->properties->get('ip') }}</span>
                            @endif
                            <span>{{ $entry->created_at?->translatedFormat('j M Y H:i') }}</span>
                        </div>
                    </div>
                @empty
                    <x-empty-state :message="__('ยังไม่มีการเข้าถึงที่บันทึกไว้')" />
                @endforelse
            </x-card>
        </div>

        <div class="space-y-4">

            {{-- ── เอกสารที่ผูกอยู่ ── --}}
            <x-card :title="__('เอกสารที่อ้างถึงลูกค้ารายนี้')">
                <dl class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-600">{{ __('ใบเสนอราคา') }}</dt>
                        <dd class="font-medium text-gray-900">{{ number_format(count($data['documents']['quotations'])) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-gray-600">{{ __('ใบสั่งขาย') }}</dt>
                        <dd class="font-medium text-gray-900">{{ number_format(count($data['documents']['sales_orders'])) }}</dd>
                    </div>
                </dl>

                <p class="mt-3 border-t border-gray-100 pt-3 text-xs text-gray-500">
                    @if ($deletableOutright)
                        {{ __('ยังไม่มีเอกสารผูกอยู่ ลบข้อมูลออกจากระบบได้ทั้งหมด') }}
                    @else
                        {{ __('เอกสารภาษีต้องเก็บ 5 ปีตามกฎหมาย จึงลบเอกสารไม่ได้ — ระบบจะลบเฉพาะข้อมูลส่วนบุคคลและเก็บตัวเลขไว้') }}
                    @endif
                </p>
            </x-card>

            {{-- ── คำขอลบ ── --}}
            @if ($canErase && ! $customer->isAnonymized())
                <x-card class="border-rose-200">
                    <x-slot name="header">
                        <h2 class="text-sm font-semibold text-rose-700">{{ __('ลบข้อมูลตามคำขอ') }}</h2>
                    </x-slot>

                    <p class="text-xs text-gray-600">
                        {{ $deletableOutright
                            ? __('ลูกค้ารายนี้จะถูกลบออกจากระบบทั้งหมด ย้อนกลับไม่ได้')
                            : __('ชื่อ เลขผู้เสียภาษี ที่อยู่ เบอร์ อีเมล และผู้ติดต่อทั้งหมดจะถูกลบถาวร ย้อนกลับไม่ได้') }}
                    </p>

                    <form method="POST" action="{{ route('customers.personal-data.erase', $customer) }}" class="mt-4 space-y-3"
                          x-data
                          @submit.prevent="confirm(@js(__('ยืนยันลบข้อมูลส่วนบุคคลถาวร? ย้อนกลับไม่ได้'))) && $el.submit()">
                        @csrf
                        @method('DELETE')

                        <div>
                            <x-input-label for="reason" :value="__('เหตุผลของคำขอ')" />
                            <x-text-input id="reason" name="reason" class="mt-1 block w-full" required
                                          :value="old('reason')"
                                          :placeholder="__('เช่น ลูกค้าใช้สิทธิ์ขอให้ลบตามมาตรา 33')" />
                            <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="confirmation" :value="__('พิมพ์ :code เพื่อยืนยัน', ['code' => $customer->code])" />
                            <x-text-input id="confirmation" name="confirmation" class="mt-1 block w-full font-mono" required autocomplete="off" />
                            <x-input-error :messages="$errors->get('confirmation')" class="mt-1" />
                        </div>

                        <x-danger-button class="w-full justify-center">
                            {{ __('ลบข้อมูลส่วนบุคคล') }}
                        </x-danger-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
