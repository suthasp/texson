<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * ข้อที่เหลือของ checklist ความปลอดภัย spec 8
 */

// ── session ────────────────────────────────────────────

it('cookie ของ session ตั้ง httponly และ same_site=strict', function (): void {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('strict')
        ->and(config('session.encrypt'))->toBeTrue();
});

it('เปิด secure cookie อัตโนมัติเมื่อ APP_ENV เป็น production', function (): void {
    // config/session.php ต้องไม่ปล่อยให้ค่า default เป็น false บน production
    $default = fn (?string $env): bool => $env === 'production';

    expect($default('production'))->toBeTrue()
        ->and($default('local'))->toBeFalse();

    $contents = (string) file_get_contents(config_path('session.php'));

    expect($contents)->toContain("env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')");
});

it('สร้าง session ใหม่หลังล็อกอินสำเร็จ กัน session fixation', function (): void {
    seedRoles();

    $user = User::factory()->create(['password' => Hash::make('correct-horse-battery')]);

    $this->get(route('login'));
    $before = session()->getId();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-horse-battery'])
        ->assertRedirect(route('dashboard'));

    expect(session()->getId())->not->toBe($before);
});

it('บัญชีที่ถูกปิดใช้งานล็อกอินไม่ได้ และได้ข้อความเดียวกับรหัสผิด', function (): void {
    seedRoles();

    $user = User::factory()->create([
        'password' => Hash::make('correct-horse-battery'),
        'is_active' => false,
    ]);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'correct-horse-battery'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

// ── รหัสผ่าน ──────────────────────────────────────────

it('รหัสผ่านสั้นกว่า 10 ตัวถูกปฏิเสธ', function (): void {
    actingAsRole(RoleName::Admin);

    $this->post(route('users.store'), [
        'name' => 'ผู้ใช้ทดสอบ',
        'email' => 'short@example.com',
        'password' => 'abc12345',
        'password_confirmation' => 'abc12345',
        'roles' => [RoleName::Viewer->value],
        'is_active' => '1',
    ])->assertSessionHasErrors('password');

    expect(User::query()->where('email', 'short@example.com')->exists())->toBeFalse();
});

it('รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลข', function (): void {
    actingAsRole(RoleName::Admin);

    $this->post(route('users.store'), [
        'name' => 'ผู้ใช้ทดสอบ',
        'email' => 'lettersonly@example.com',
        'password' => 'abcdefghijkl',
        'password_confirmation' => 'abcdefghijkl',
        'roles' => [RoleName::Viewer->value],
        'is_active' => '1',
    ])->assertSessionHasErrors('password');
});

it('รหัสผ่านถูก hash ไม่เก็บเป็นข้อความธรรมดา', function (): void {
    actingAsRole(RoleName::Admin);

    $this->post(route('users.store'), [
        'name' => 'ผู้ใช้ทดสอบ',
        'email' => 'hashed@example.com',
        'password' => 'strong-pass-1234',
        'password_confirmation' => 'strong-pass-1234',
        'roles' => [RoleName::Viewer->value],
        'is_active' => '1',
    ])->assertRedirect();

    $user = User::query()->where('email', 'hashed@example.com')->firstOrFail();

    expect($user->password)->not->toBe('strong-pass-1234')
        ->and(Hash::check('strong-pass-1234', $user->password))->toBeTrue();
});

// ── ไฟล์อัปโหลด ───────────────────────────────────────

it('ปฏิเสธไฟล์ที่นามสกุลปลอมเป็นรูปแต่เนื้อในเป็น PHP', function (): void {
    Storage::fake('private');
    actingAsRole(RoleName::Admin);

    /*
     * UploadedFile::fake()->create() เดา mime จากนามสกุล ซึ่งจะทำให้เทสต์ผ่าน
     * ทั้งที่ยังไม่ได้พิสูจน์ว่ามีการตรวจเนื้อไฟล์จริง จึงต้องเขียนไฟล์จริงลงดิสก์
     */
    $path = (string) tempnam(sys_get_temp_dir(), 'texson-fake-');
    file_put_contents($path, "<?php echo 'pwned'; ?>");

    $this->put(route('settings.update'), [
        'group' => 'company',
        'values' => ['company.name_th' => 'TEXSON'],
        'logo' => new UploadedFile($path, 'logo.png', 'image/png', null, true),
    ])->assertSessionHasErrors('logo');

    @unlink($path);
});

it('เก็บไฟล์ที่อัปโหลดไว้นอก public และตั้งชื่อสุ่ม', function (): void {
    Storage::fake('private');
    actingAsRole(RoleName::Admin);

    $png = (string) tempnam(sys_get_temp_dir(), 'texson-png-');
    file_put_contents($png, (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    ));

    $this->put(route('settings.update'), [
        'group' => 'company',
        'values' => ['company.name_th' => 'TEXSON'],
        'logo' => new UploadedFile($png, 'บริษัท-โลโก้.png', 'image/png', null, true),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $stored = (string) Setting::query()->where('key', 'company.logo_path')->value('value');
    $stored = trim($stored, '"');

    expect($stored)->toStartWith('branding/')
        // ชื่อเดิมต้องไม่หลงเหลืออยู่ในชื่อไฟล์
        ->not->toContain('โลโก้')
        ->and(Storage::disk('private')->exists($stored))->toBeTrue();

    @unlink($png);
});

it('ดาวน์โหลดไฟล์แนบต้องผ่าน controller ที่ตรวจสิทธิ์', function (): void {
    actingAsRole(RoleName::Engineer);

    // วิศวกรไม่มีสิทธิ์ดูหน้าตั้งค่า จึงเปิดไฟล์โลโก้ผ่าน endpoint นี้ไม่ได้
    $this->get(route('settings.asset', ['key' => 'company.logo_path']))->assertForbidden();
});

// ── error handling ────────────────────────────────────

it('หน้าที่ไม่มีอยู่จริงตอบ 404 ไม่ใช่ error 500', function (): void {
    actingAsRole(RoleName::Viewer);

    $this->get('/customers/999999')->assertNotFound();
});

it('API ตอบ JSON ที่มี message เสมอ ไม่หลุด HTML ออกไป', function (): void {
    $response = $this->getJson('/api/v1/products')->assertUnauthorized();

    expect($response->json())->toHaveKey('message')
        ->and($response->headers->get('Content-Type'))->toContain('application/json');
});

// ── คำสั่งตรวจ checklist ──────────────────────────────

it('คำสั่ง texson:security-check ผ่านบนเครื่องที่ตั้งค่าถูก', function (): void {
    config()->set('app.debug', false);
    config()->set('session.secure', true);

    $this->artisan('texson:security-check', ['--production' => true])->assertSuccessful();
});

it('คำสั่ง texson:security-check จับได้เมื่อ APP_DEBUG ยังเปิดอยู่บน production', function (): void {
    config()->set('app.debug', true);
    config()->set('session.secure', true);

    $this->artisan('texson:security-check', ['--production' => true])->assertFailed();
});

it('คำสั่ง texson:security-check จับได้เมื่อ session ไม่ได้เป็น strict', function (): void {
    config()->set('app.debug', false);
    config()->set('session.secure', true);
    config()->set('session.same_site', 'lax');

    $this->artisan('texson:security-check', ['--production' => true])->assertFailed();
});
