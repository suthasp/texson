@php
    /** @var App\Models\User $user */
    $isEdit = $user->exists;
    $selectedRoles = old('roles', $assignedRoles);
@endphp

<form method="POST" action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}" class="space-y-4">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-card :title="__('ข้อมูลผู้ใช้')">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="employee_code" :label="__('รหัสพนักงาน')" :value="$user->employee_code" />
            <x-form.input name="name" :label="__('ชื่อ-นามสกุล')" :value="$user->name" required />
            <x-form.input name="email" :label="__('อีเมล')" :value="$user->email" type="email" required />
            <x-form.input name="phone" :label="__('โทรศัพท์')" :value="$user->phone" type="tel" />
        </div>
    </x-card>

    <x-card :title="__('รหัสผ่าน')">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="password" :label="__('รหัสผ่าน')" type="password"
                          :required="! $isEdit" autocomplete="new-password"
                          :help="$isEdit ? __('เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยนรหัสผ่าน') : __('อย่างน้อย 10 ตัว ต้องมีตัวอักษรและตัวเลข')" />
            <x-form.input name="password_confirmation" :label="__('ยืนยันรหัสผ่าน')" type="password"
                          :required="! $isEdit" autocomplete="new-password" />
        </div>
    </x-card>

    <x-card :title="__('บทบาทและสิทธิ์')">
        <div class="space-y-2">
            @foreach ($roles as $value => $label)
                <label class="flex items-start gap-2.5 rounded-lg border border-gray-100 p-3 transition hover:bg-gray-50">
                    <input type="checkbox" name="roles[]" value="{{ $value }}"
                           @checked(in_array($value, $selectedRoles, true))
                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-aqua-500 focus:ring-aqua-400">
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-gray-900">{{ $label }}</span>
                        <span class="tabular block text-xs text-gray-400">{{ $value }}</span>
                    </span>
                </label>
            @endforeach

            <x-input-error :messages="$errors->get('roles')" class="mt-1" />
        </div>

        <div class="mt-4 border-t border-gray-100 pt-4">
            <x-form.checkbox name="is_active" :label="__('เปิดใช้งานบัญชีนี้')" :checked="$user->is_active ?? true"
                             :help="__('ปิดใช้งานแล้วผู้ใช้จะยังอยู่ในระบบแต่เข้าใช้งานไม่ได้')" />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-3">
        <x-link-button :href="route('users.index')" variant="secondary">{{ __('ยกเลิก') }}</x-link-button>
        <button type="submit" class="rounded-md bg-navy-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-navy-800">
            {{ $isEdit ? __('บันทึกการแก้ไข') : __('สร้างผู้ใช้') }}
        </button>
    </div>
</form>
