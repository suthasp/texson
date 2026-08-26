<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('admin สร้างผู้ใช้พร้อมกำหนด role ได้', function (): void {
    actingAsRole(RoleName::Admin);

    $this->post(route('users.store'), [
        'employee_code' => 'EMP999',
        'name' => 'พนักงานขายใหม่',
        'email' => 'newsales@texson.local',
        'phone' => '081-000-0000',
        'password' => 'texson12345',
        'password_confirmation' => 'texson12345',
        'roles' => [RoleName::Sales->value],
        'is_active' => '1',
    ])->assertRedirect(route('users.index'));

    $user = User::where('email', 'newsales@texson.local')->firstOrFail();

    expect($user->hasRole(RoleName::Sales->value))->toBeTrue()
        ->and($user->employee_code)->toBe('EMP999')
        ->and(Hash::check('texson12345', $user->password))->toBeTrue();
});

it('บังคับรหัสผ่านอย่างน้อย 10 ตัวและต้องมีตัวเลข', function (): void {
    actingAsRole(RoleName::Admin);

    $this->post(route('users.store'), [
        'name' => 'รหัสสั้นเกินไป',
        'email' => 'short@texson.local',
        'password' => 'abc123',
        'password_confirmation' => 'abc123',
        'roles' => [RoleName::Viewer->value],
    ])->assertSessionHasErrors('password');

    $this->post(route('users.store'), [
        'name' => 'ไม่มีตัวเลข',
        'email' => 'nodigit@texson.local',
        'password' => 'abcdefghijkl',
        'password_confirmation' => 'abcdefghijkl',
        'roles' => [RoleName::Viewer->value],
    ])->assertSessionHasErrors('password');

    expect(User::where('email', 'short@texson.local')->exists())->toBeFalse()
        ->and(User::where('email', 'nodigit@texson.local')->exists())->toBeFalse();
});

it('แก้ไขผู้ใช้โดยเว้นรหัสผ่านว่างแล้วรหัสผ่านเดิมยังใช้ได้', function (): void {
    actingAsRole(RoleName::Admin);

    $user = User::factory()->create(['password' => Hash::make('originalpass99')]);
    $user->assignRole(RoleName::Sales->value);
    $originalHash = $user->password;

    $this->put(route('users.update', $user), [
        'name' => 'ชื่อใหม่',
        'email' => $user->email,
        'password' => '',
        'password_confirmation' => '',
        'roles' => [RoleName::Warehouse->value],
        'is_active' => '1',
    ])->assertRedirect(route('users.index'));

    $user->refresh();

    expect($user->name)->toBe('ชื่อใหม่')
        ->and($user->password)->toBe($originalHash)
        ->and($user->hasRole(RoleName::Warehouse->value))->toBeTrue()
        ->and($user->hasRole(RoleName::Sales->value))->toBeFalse();
});

it('ลบบัญชีตัวเองไม่ได้', function (): void {
    $admin = actingAsRole(RoleName::Admin);

    $this->delete(route('users.destroy', $admin))->assertForbidden();

    expect(User::find($admin->id))->not->toBeNull();
});

it('บัญชีที่ถูกปิดใช้งานเข้าสู่ระบบไม่ได้', function (): void {
    seedRoles();
    $user = User::factory()->inactive()->create(['password' => Hash::make('texson12345')]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'texson12345',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('บันทึกเวลาเข้าระบบล่าสุดตอนล็อกอินสำเร็จ', function (): void {
    seedRoles();
    $user = User::factory()->create(['password' => Hash::make('texson12345'), 'last_login_at' => null]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'texson12345',
    ])->assertRedirect(route('dashboard'));

    expect($user->refresh()->last_login_at)->not->toBeNull();
});

it('ไม่เปิดให้สมัครสมาชิกเอง — ผู้ใช้ต้องถูกสร้างโดยผู้ดูแลระบบ', function (): void {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
});
