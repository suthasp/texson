<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * รหัสผ่านที่ hash ไว้ครั้งเดียวแล้วใช้ซ้ำ — hash ทุกครั้งจะทำให้เทสต์ช้ามาก
     */
    protected static ?string $password = null;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'employee_code' => strtoupper(fake()->unique()->bothify('EMP####')),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * สร้างผู้ใช้พร้อม role — ต้องมี RolePermissionSeeder รันมาก่อน
     */
    public function withRole(RoleName $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->assignRole($role->value);
        });
    }
}
