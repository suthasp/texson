<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * การยืนยันตัวตนของ API และรูปแบบ error ที่สเปกข้อ 6 กำหนด
 */
it('ยิง API โดยไม่มี token ได้ 401 พร้อมข้อความ ไม่ใช่ redirect ไปหน้า login', function (string $path): void {
    $this->getJson($path)
        ->assertStatus(401)
        // ยืนยันว่าเป็นข้อความของเราจริง ไม่ใช่ 'Unauthenticated.' ตัว default
        // ที่บังเอิญมีคีย์ message เหมือนกัน
        ->assertJsonPath('message', 'ต้องยืนยันตัวตนก่อน — แนบ header Authorization: Bearer {token}');
})->with([
    '/api/v1/products',
    '/api/v1/customers',
    '/api/v1/quotations',
    '/api/v1/reports/low-stock',
]);

it('ยิง API โดยไม่ส่ง Accept header ก็ยังได้ 401 JSON ไม่ใช่ HTML', function (): void {
    // client ที่ลืมส่ง Accept: application/json ต้องไม่เจอหน้า login เป็น HTML
    $response = $this->get('/api/v1/products');

    $response->assertStatus(401);

    expect($response->headers->get('content-type'))->toContain('application/json');
});

it('ออก token ด้วยอีเมลและรหัสผ่านที่ถูกต้องได้', function (): void {
    seedRoles();

    $user = User::factory()->create(['password' => 'texson1234', 'is_active' => true]);
    $user->assignRole(RoleName::Sales->value);

    $response = $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'texson1234',
        'device_name' => 'เครื่องนับสต็อก A',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['token', 'device_name', 'user' => ['id', 'name', 'email', 'roles']], 'meta']);

    expect($user->fresh()->tokens()->count())->toBe(1);

    // token ที่ได้ใช้เรียก endpoint อื่นได้จริง
    $token = $response->json('data.token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('รหัสผ่านผิดได้ 422 และไม่บอกใบ้ว่าอีเมลมีอยู่จริงหรือไม่', function (): void {
    $user = User::factory()->create(['password' => 'texson1234']);

    $wrongPassword = $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'ผิดแน่นอน',
        'device_name' => 'test',
    ])->assertStatus(422)->json('message');

    $unknownEmail = $this->postJson('/api/v1/auth/token', [
        'email' => 'ไม่มีคนนี้@texson.local',
        'password' => 'texson1234',
        'device_name' => 'test',
    ])->assertStatus(422)->json('message');

    expect($wrongPassword)->toBe($unknownEmail);
});

it('บัญชีที่ถูกปิดใช้งานขอ token ไม่ได้', function (): void {
    $user = User::factory()->create(['password' => 'texson1234', 'is_active' => false]);

    $this->postJson('/api/v1/auth/token', [
        'email' => $user->email,
        'password' => 'texson1234',
        'device_name' => 'test',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('ล็อกอินซ้ำจากเครื่องเดิมไม่ทำให้ token ค้างสะสม', function (): void {
    $user = User::factory()->create(['password' => 'texson1234']);

    foreach (range(1, 3) as $ignored) {
        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'texson1234',
            'device_name' => 'iPad คลัง',
        ])->assertStatus(201);
    }

    expect($user->fresh()->tokens()->count())->toBe(1);
});

it('ขอ token เกิน 5 ครั้งต่อนาทีถูกบล็อก (spec 8)', function (): void {
    $payload = ['email' => 'nobody@texson.local', 'password' => 'x', 'device_name' => 'test'];

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/token', $payload);
    }

    $this->postJson('/api/v1/auth/token', $payload)->assertStatus(429);
});

it('เพิกถอน token ปัจจุบันแล้วใช้ต่อไม่ได้', function (): void {
    $user = userWithRole(RoleName::Sales);

    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/v1/auth/token')
        ->assertOk()
        ->assertJsonPath('data.revoked', true);

    expect($user->fresh()->tokens()->count())->toBe(0);

    // guard จำผู้ใช้ที่ resolve ไปแล้วไว้ในโปรเซสเดียวกัน — ต้องล้างก่อนจึงจะจำลอง request ใหม่ได้จริง
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});

it('me คืนสิทธิ์ทั้งหมดของผู้ใช้ให้ client ใช้ตัดสินใจโชว์เมนู', function (): void {
    $user = userWithRole(RoleName::Warehouse);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.roles', [RoleName::Warehouse->value])
        ->assertJsonStructure(['data', 'meta' => ['permissions']]);

    expect($this->getJson('/api/v1/auth/me')->json('meta.permissions'))
        ->toContain('goods_receipt.post')
        ->not->toContain('quotation.approve');
});

it('endpoint ที่ไม่มีอยู่ตอบ 404 เป็น JSON', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Admin));

    $this->getJson('/api/v1/ไม่มีอันนี้')
        ->assertStatus(404)
        ->assertJsonStructure(['message']);
});

it('ขอทรัพยากรที่ไม่มีอยู่ตอบ 404 พร้อมบอกว่าไม่พบอะไร', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Admin));

    $this->getJson('/api/v1/products/999999')
        ->assertStatus(404)
        ->assertJsonPath('message', 'ไม่พบ Product ที่ระบุ');
});
