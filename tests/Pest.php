<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * สร้าง role และ permission ทั้งชุด — จำเป็นก่อนทดสอบอะไรก็ตามที่แตะสิทธิ์
 */
function seedRoles(): void
{
    test()->seed(RolePermissionSeeder::class);
}

/**
 * สร้างผู้ใช้พร้อม role แล้วล็อกอินให้เลย
 */
function actingAsRole(RoleName $role): User
{
    seedRoles();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole($role->value);

    test()->actingAs($user);

    return $user;
}

/**
 * สร้างผู้ใช้พร้อม role โดยไม่ล็อกอิน
 */
function userWithRole(RoleName $role): User
{
    seedRoles();

    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}
