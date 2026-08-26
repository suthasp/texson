<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoleName;
use App\Http\Requests\Concerns\AuthorizesResource;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    use AuthorizesResource;

    /** @return class-string<User> */
    protected function resourceClass(): string
    {
        return User::class;
    }

    protected function resourceRouteKey(): string
    {
        return 'user';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isCreate = $userId === null;

        return [
            'employee_code' => ['nullable', 'string', 'max:30', Rule::unique('users', 'employee_code')->ignore($userId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],

            // แก้ไขผู้ใช้เดิมแล้วเว้นรหัสผ่านว่าง = ไม่เปลี่ยนรหัสผ่าน
            'password' => [$isCreate ? 'required' : 'nullable', 'confirmed', Password::defaults()],

            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(RoleName::class)],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'employee_code' => __('รหัสพนักงาน'),
            'name' => __('ชื่อ-นามสกุล'),
            'email' => __('อีเมล'),
            'phone' => __('โทรศัพท์'),
            'password' => __('รหัสผ่าน'),
            'roles' => __('บทบาท'),
        ];
    }
}
