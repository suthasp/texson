@php
    use App\Enums\SettingKey;

    $canUpdate = auth()->user()->can('update', App\Models\Setting::class);
@endphp

<x-app-layout>
    <x-slot name="title">{{ __('ตั้งค่าระบบ') }}</x-slot>

    <x-page-header :title="__('ตั้งค่าระบบ')"
                   :subtitle="__('ข้อมูลที่ขึ้นหัวใบเสนอราคา ค่าเริ่มต้นของเอกสาร และเกณฑ์การอนุมัติ')" />

    <div class="grid gap-4 lg:grid-cols-4">
        {{-- ── แท็บกลุ่ม ── --}}
        <nav class="lg:col-span-1">
            <ul class="space-y-1">
                @foreach ($groups as $key => $label)
                    <li>
                        <a href="{{ route('settings.index', ['group' => $key]) }}"
                           @if ($key === $active) aria-current="page" @endif
                           class="block rounded-lg px-3 py-2 text-sm transition {{ $key === $active ? 'bg-navy-900 font-semibold text-white' : 'text-gray-700 hover:bg-gray-100' }}">
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="lg:col-span-3">
            <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="group" value="{{ $active }}">

                <x-card :title="$groups[$active]">
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($keys as $key)
                            @php
                                $name = 'values['.$key->value.']';
                                $current = $values[$key->value] ?? '';
                                $span = $key->isMultiline() ? 'sm:col-span-2' : '';
                            @endphp

                            @if ($key->isFile())
                                <div class="space-y-1 {{ $span }}">
                                    <label for="{{ $key->value }}" class="block text-sm font-medium text-gray-700">{{ $key->label() }}</label>

                                    @if (filled($current))
                                        <img src="{{ route('settings.asset', $key->value) }}" alt="{{ $key->label() }}"
                                             class="mb-2 max-h-16 rounded border border-gray-200 bg-white p-1">
                                    @endif

                                    <input id="{{ $key->value }}"
                                           name="{{ $key === SettingKey::CompanyLogoPath ? 'logo' : 'signature' }}"
                                           type="file" accept="image/png,image/jpeg"
                                           @disabled(! $canUpdate)
                                           class="block w-full text-sm text-gray-600 file:me-3 file:rounded-md file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white">
                                    <p class="text-xs text-gray-500">{{ __('PNG หรือ JPEG ขนาดไม่เกิน 2 MB — เก็บในโฟลเดอร์ private ไม่เปิดสาธารณะ') }}</p>
                                    <x-input-error :messages="$errors->get($key === SettingKey::CompanyLogoPath ? 'logo' : 'signature')" />
                                </div>
                            @elseif ($key->isMultiline())
                                <div class="space-y-1 {{ $span }}">
                                    <label for="{{ $key->value }}" class="block text-sm font-medium text-gray-700">{{ $key->label() }}</label>
                                    <textarea id="{{ $key->value }}" name="{{ $name }}" rows="4"
                                              @readonly(! $canUpdate)
                                              class="form-input-base">{{ old($name, $current) }}</textarea>
                                    <x-input-error :messages="$errors->get($name)" />
                                </div>
                            @else
                                <div class="space-y-1">
                                    <label for="{{ $key->value }}" class="block text-sm font-medium text-gray-700">{{ $key->label() }}</label>
                                    <input id="{{ $key->value }}" name="{{ $name }}" type="text"
                                           value="{{ old($name, $current) }}"
                                           @readonly(! $canUpdate)
                                           class="form-input-base {{ $active === 'approval' ? 'tabular' : '' }}">
                                    <x-input-error :messages="$errors->get($name)" />
                                </div>
                            @endif
                        @endforeach
                    </div>
                </x-card>

                @if ($active === 'approval')
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                        {{ __('ใบเสนอราคาที่ "เกิน" เกณฑ์ส่วนลด "ต่ำกว่า" เกณฑ์ margin หรือ "เกิน" เกณฑ์ยอดสุทธิ จะส่งให้ลูกค้าไม่ได้จนกว่าจะมีผู้จัดการฝ่ายขายอนุมัติ') }}
                    </div>
                @endif

                @if ($canUpdate)
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
                            {{ __('บันทึกค่าตั้ง') }}
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
