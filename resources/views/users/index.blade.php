<x-app-layout>
    <x-slot name="title">{{ __('ผู้ใช้งาน') }}</x-slot>

    <x-page-header :title="__('ผู้ใช้งานระบบ')" :subtitle="__('พบ :count คน', ['count' => number_format($users->total())])">
        <x-slot name="actions">
            @can('create', App\Models\User::class)
                <x-link-button :href="route('users.create')">{{ __('เพิ่มผู้ใช้') }}</x-link-button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap gap-3">
            <div class="min-w-0 flex-1 sm:max-w-sm">
                <label for="q" class="sr-only">{{ __('ค้นหา') }}</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="{{ __('ชื่อ, อีเมล หรือรหัสพนักงาน') }}" class="form-input-base text-sm">
            </div>

            <div>
                <label for="role" class="sr-only">{{ __('บทบาท') }}</label>
                <select id="role" name="role" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกบทบาท') }}</option>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="sr-only">{{ __('สถานะ') }}</label>
                <select id="status" name="status" class="form-input-base text-sm">
                    <option value="">{{ __('ทุกสถานะ') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('ใช้งาน') }}</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('ปิดใช้งาน') }}</option>
                </select>
            </div>

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
                        <th scope="col" class="table-head-cell"><x-sort-link column="employee_code" :label="__('รหัสพนักงาน')" /></th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="name" :label="__('ชื่อ')" /></th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="email" :label="__('อีเมล')" /></th>
                        <th scope="col" class="table-head-cell">{{ __('บทบาท') }}</th>
                        <th scope="col" class="table-head-cell"><x-sort-link column="last_login_at" :label="__('เข้าระบบล่าสุด')" /></th>
                        <th scope="col" class="table-head-cell">{{ __('สถานะ') }}</th>
                        <th scope="col" class="table-head-cell text-end">{{ __('จัดการ') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr class="transition hover:bg-gray-50">
                            <td class="table-cell-base tabular text-gray-500">{{ $user->employee_code ?: '—' }}</td>
                            <td class="table-cell-base font-medium text-gray-900">{{ $user->name }}</td>
                            <td class="table-cell-base text-gray-600">{{ $user->email }}</td>
                            <td class="table-cell-base">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($user->roles as $role)
                                        <x-badge :color="$role->name === 'admin' ? 'navy' : 'gray'">
                                            {{ App\Enums\RoleName::from($role->name)->label() }}
                                        </x-badge>
                                    @endforeach
                                </div>
                            </td>
                            <td class="table-cell-base tabular text-xs text-gray-500">
                                {{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? __('ยังไม่เคยเข้า') }}
                            </td>
                            <td class="table-cell-base">
                                <x-badge :color="$user->is_active ? 'green' : 'gray'">
                                    {{ $user->is_active ? __('ใช้งาน') : __('ปิดใช้งาน') }}
                                </x-badge>
                            </td>
                            <td class="table-cell-base text-end">
                                <div class="flex items-center justify-end gap-2">
                                    @can('update', $user)
                                        <a href="{{ route('users.edit', $user) }}" class="text-xs font-medium text-aqua-600 hover:text-aqua-700">{{ __('แก้ไข') }}</a>
                                    @endcan
                                    @can('delete', $user)
                                        <x-delete-button :action="route('users.destroy', $user)"
                                                         :confirm="__('ยืนยันการลบผู้ใช้ :name?', ['name' => $user->name])" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :message="__('ไม่พบผู้ใช้ตามเงื่อนไขที่ค้นหา')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">{{ $users->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
