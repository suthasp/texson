<x-app-layout>
    <x-slot name="title">{{ __('ประวัติการใช้งาน') }}</x-slot>

    <x-page-header :title="__('ประวัติการใช้งาน')"
                   :subtitle="__('บันทึกทุกการเปลี่ยนสถานะเอกสาร ปรับสต็อก แก้ราคา และการเข้าถึงข้อมูลส่วนบุคคล')" />

    {{-- ── ตัวกรอง ── --}}
    <x-card class="mb-4">
        <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <x-input-label for="log" :value="__('ประเภทบันทึก')" />
                <select id="log" name="log" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-aqua-500 focus:ring-aqua-500">
                    <option value="">{{ __('ทั้งหมด') }}</option>
                    @foreach ($logNames as $name)
                        <option value="{{ $name }}" @selected(($filters['log'] ?? null) === $name)>
                            {{ $name === $pdpaLog ? __('ข้อมูลส่วนบุคคล (PDPA)') : $name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="event" :value="__('การกระทำ')" />
                <select id="event" name="event" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-aqua-500 focus:ring-aqua-500">
                    <option value="">{{ __('ทั้งหมด') }}</option>
                    @foreach ($events as $event)
                        <option value="{{ $event }}" @selected(($filters['event'] ?? null) === $event)>{{ $event }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="subject_type" :value="__('ประเภทข้อมูล')" />
                <select id="subject_type" name="subject_type" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-aqua-500 focus:ring-aqua-500">
                    <option value="">{{ __('ทั้งหมด') }}</option>
                    @foreach ($subjectTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['subject_type'] ?? null) === $type)>{{ class_basename($type) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="causer_id" :value="__('ผู้ทำรายการ')" />
                <select id="causer_id" name="causer_id" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-aqua-500 focus:ring-aqua-500">
                    <option value="">{{ __('ทุกคน') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) ($filters['causer_id'] ?? 0) === $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="days" :value="__('ย้อนหลัง')" />
                <div class="mt-1 flex gap-2">
                    <select id="days" name="days" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-aqua-500 focus:ring-aqua-500">
                        @foreach ($ranges as $range)
                            <option value="{{ $range }}" @selected((int) $filters['days'] === $range)>{{ __(':days วัน', ['days' => $range]) }}</option>
                        @endforeach
                    </select>
                    <x-primary-button>{{ __('กรอง') }}</x-primary-button>
                </div>
            </div>
        </form>
    </x-card>

    {{-- ── รายการ ── --}}
    <x-card :padded="false">
        @forelse ($activities as $activity)
            @php
                $old = $activity->properties->get('old', []);
                $new = $activity->properties->get('attributes', []);
                $changed = array_keys(is_array($new) ? $new : []);
            @endphp

            <div class="border-b border-gray-100 px-4 py-3 last:border-0 sm:px-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-900">
                            <span class="font-medium">{{ $activity->causer?->name ?? __('ระบบ') }}</span>
                            <span class="text-gray-600">{{ $activity->description }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $activity->subject_type ? class_basename($activity->subject_type) : '—' }}
                            @if ($activity->subject_id)
                                <span class="font-mono">#{{ $activity->subject_id }}</span>
                            @endif
                            @if ($activity->log_name === $pdpaLog)
                                · <x-badge color="amber">{{ __('PDPA') }}</x-badge>
                            @endif
                        </p>
                    </div>

                    <div class="text-right text-xs text-gray-500">
                        <div>{{ $activity->created_at?->translatedFormat('j M Y H:i') }}</div>
                        @if ($activity->event)
                            <x-badge color="navy" class="mt-1">{{ $activity->event }}</x-badge>
                        @endif
                    </div>
                </div>

                {{-- ค่าก่อน/หลัง ตามที่สเปกข้อ 8 บังคับให้ตรวจย้อนหลังได้ --}}
                @if ($changed !== [])
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500">
                                    <th class="py-1 pr-4 font-medium">{{ __('ฟิลด์') }}</th>
                                    <th class="py-1 pr-4 font-medium">{{ __('ก่อน') }}</th>
                                    <th class="py-1 font-medium">{{ __('หลัง') }}</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                @foreach ($changed as $field)
                                    <tr class="border-t border-gray-50">
                                        <td class="py-1 pr-4 font-mono">{{ $field }}</td>
                                        <td class="py-1 pr-4 text-rose-700">{{ \Illuminate\Support\Arr::get($old, $field) ?? '—' }}</td>
                                        <td class="py-1 text-emerald-700">{{ \Illuminate\Support\Arr::get($new, $field) ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @empty
            <x-empty-state :message="__('ไม่มีบันทึกในช่วงเวลาที่เลือก')" />
        @endforelse

        @if ($activities->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $activities->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
