<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;

it('ช่องค้นหาไม่ถูก SQL injection', function (string $payload): void {
    actingAsRole(RoleName::Warehouse);
    Product::factory()->count(3)->create();

    $this->get(route('products.index', ['q' => $payload]))->assertOk();

    // ตารางต้องยังอยู่และข้อมูลต้องไม่หาย
    expect(Schema::hasTable('products'))->toBeTrue()
        ->and(Product::count())->toBe(3);
})->with([
    "'; DROP TABLE products; --",
    "1' OR '1'='1",
    "admin'--",
    '" UNION SELECT NULL, version() --',
]);

it('ชื่อคอลัมน์ที่ใช้เรียงลำดับรับเฉพาะค่าใน whitelist', function (): void {
    actingAsRole(RoleName::Warehouse);
    Product::factory()->count(2)->create();

    // คอลัมน์ที่ไม่อยู่ใน whitelist ต้องถูกทิ้งแล้วใช้ค่า default แทน ไม่ใช่ error 500
    $this->get(route('products.index', ['sort' => 'password', 'direction' => 'asc']))->assertOk();
    $this->get(route('products.index', ['sort' => '(SELECT 1)', 'direction' => 'desc']))->assertOk();
    $this->get(route('products.index', ['sort' => 'sku', 'direction' => 'evil']))->assertOk();

    expect(Schema::hasTable('products'))->toBeTrue();
});

it('escape HTML ในข้อมูลที่ผู้ใช้กรอก จึงไม่เกิด XSS', function (): void {
    actingAsRole(RoleName::Warehouse);

    $xss = '<script>alert("xss")</script>';
    $product = Product::factory()->create(['name_th' => $xss, 'description' => $xss]);

    $response = $this->get(route('products.show', $product));

    $response->assertOk()
        ->assertDontSee($xss, escape: false)
        ->assertSee('&lt;script&gt;', escape: false);
});

it('escape HTML ในชื่อลูกค้าด้วย', function (): void {
    actingAsRole(RoleName::Sales);

    $customer = Customer::factory()->create(['name_th' => '<img src=x onerror=alert(1)>']);

    $this->get(route('customers.show', $customer))
        ->assertOk()
        ->assertDontSee('<img src=x onerror=alert(1)>', escape: false);
});

it('ฟอร์มทุกอันมี CSRF token', function (): void {
    actingAsRole(RoleName::Warehouse);

    $this->get(route('products.create'))
        ->assertOk()
        ->assertSee('name="_token"', escape: false);
});

it('middleware ตรวจ CSRF ถูกผูกไว้กับทุก route ของกลุ่ม web', function (): void {
    // Laravel ปิดการตรวจ CSRF อัตโนมัติระหว่างรันเทสต์ จึงยิง 419 จริงไม่ได้
    // สิ่งที่ตรวจได้และมีความหมายคือ middleware ถูกผูกไว้จริงในกลุ่ม web
    $webMiddleware = app(Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'];

    expect($webMiddleware)->toContain(Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
});

it('ผู้ใช้ที่ไม่มีสิทธิ์ได้ 403 ไม่ใช่ validation error ที่บอกใบ้กฎของฟอร์ม', function (): void {
    actingAsRole(RoleName::Viewer);

    // ส่งข้อมูลที่ไม่ผ่าน validation แน่ ๆ — ต้องโดนปฏิเสธด้วยสิทธิ์ก่อนถึงขั้น validate
    $this->post(route('brands.store'), [])->assertForbidden();
    $this->post(route('products.store'), [])->assertForbidden();
    $this->post(route('customers.store'), [])->assertForbidden();
});

it('ล็อกอินผิดเกิน 5 ครั้งต่อนาทีถูกล็อก', function (): void {
    seedRoles();
    $user = App\Models\User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->get('email')[0])->toContain('พยายามเข้าสู่ระบบมากเกินไป');
});

it('เข้า API โดยไม่มี token ได้ 401', function (): void {
    // เทสต์ครบทุก endpoint ของ API อยู่ที่ tests/Feature/Api/ApiAuthTest.php
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});
