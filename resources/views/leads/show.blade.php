<x-app-layout>
    <x-slot name="title">{{ $lead->name }}</x-slot>

    <x-page-header :title="$lead->name"
                   :subtitle="$lead->created_at?->translatedFormat('j F Y เวลา H:i น.')"
                   :back="route('leads.index')">
        <x-slot name="actions">
            <x-badge :color="$lead->status->badgeColor()">{{ $lead->status->label() }}</x-badge>

            @can('delete', $lead)
                <x-delete-button :action="route('leads.destroy', $lead)"
                                 :confirm="__('ลบคำขอนี้? ข้อมูลจะถูกซ่อนจากรายการแต่ยังกู้คืนได้')" />
            @endcan
        </x-slot>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">

            <x-card :title="__('รายละเอียดคำขอ')">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach ([
                        __('ชื่อ–นามสกุล') => $lead->name,
                        __('บริษัท / องค์กร') => $lead->company,
                        __('เบอร์โทร / อีเมล') => $lead->contact,
                        __('บริการที่สนใจ') => $lead->service_interest?->label(),
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs text-gray-500">{{ $label }}</dt>
                            <dd class="text-sm text-gray-900">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($lead->message)
                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <p class="text-xs text-gray-500">{{ __('รายละเอียดเพิ่มเติม') }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-gray-800">{{ $lead->message }}</p>
                    </div>
                @endif
            </x-card>

            @can('updateAny', App\Models\ContactLead::class)
                <x-card :title="__('การติดตาม')">
                    <form method="POST" action="{{ route('leads.update', $lead) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        @if ($nextStatuses !== [])
                            <div>
                                <x-input-label for="status" :value="__('เปลี่ยนสถานะเป็น')" />
                                <select id="status" name="status" class="form-input-base mt-1 text-sm">
                                    <option value="">{{ __('— ไม่เปลี่ยน —') }}</option>
                                    @foreach ($nextStatuses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-1" />
                            </div>
                        @else
                            <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">
                                {{ __('คำขอนี้ปิดแล้ว เปลี่ยนสถานะไม่ได้ ถ้าลูกค้าติดต่อมาอีกจะเป็นคำขอใหม่') }}
                            </p>
                        @endif

                        <div>
                            <x-input-label for="internal_note" :value="__('บันทึกภายใน')" />
                            <textarea id="internal_note" name="internal_note" rows="4" maxlength="2000"
                                      class="form-input-base mt-1 text-sm"
                                      placeholder="{{ __('สรุปสิ่งที่คุยกับลูกค้า หรือขั้นตอนต่อไป') }}">{{ old('internal_note', $lead->internal_note) }}</textarea>
                            <x-input-error :messages="$errors->get('internal_note')" class="mt-1" />
                        </div>

                        <x-primary-button>{{ __('บันทึก') }}</x-primary-button>
                    </form>
                </x-card>
            @endcan
        </div>

        <div class="space-y-4">
            <x-card :title="__('สถานะการดูแล')">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-gray-600">{{ __('ผู้ดูแล') }}</dt>
                        <dd class="font-medium text-gray-900">{{ $lead->handler?->name ?? __('ยังไม่มีใครรับ') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-gray-600">{{ __('รับเรื่องเมื่อ') }}</dt>
                        <dd class="text-gray-900">{{ $lead->handled_at?->translatedFormat('j M Y H:i') ?? '—' }}</dd>
                    </div>
                </dl>
            </x-card>

            {{-- ร่องรอยที่ใช้ตรวจตอนโดนยิงสแปม --}}
            <x-card :title="__('ที่มาของคำขอ')">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-gray-600">{{ __('ภาษาที่ใช้กรอก') }}</dt>
                        <dd class="text-gray-900">{{ $lead->locale === 'th' ? __('ไทย') : __('อังกฤษ') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-gray-600">{{ __('หมายเลข IP') }}</dt>
                        <dd class="font-mono text-xs text-gray-900">{{ $lead->ip ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($lead->user_agent)
                    <p class="mt-3 break-words border-t border-gray-100 pt-3 text-xs text-gray-500">{{ $lead->user_agent }}</p>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
