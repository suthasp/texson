<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create(Arr::except($data, ['roles', 'password_confirmation']));
            $user->syncRoles($data['roles'] ?? []);

            return $user;
        });
    }

    /**
     * เว้นช่องรหัสผ่านว่าง = ไม่เปลี่ยนรหัสผ่านเดิม
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $attributes = Arr::except($data, ['roles', 'password_confirmation']);

            if (blank($attributes['password'] ?? null)) {
                unset($attributes['password']);
            }

            $user->update($attributes);
            $user->syncRoles($data['roles'] ?? []);

            return $user->refresh();
        });
    }
}
