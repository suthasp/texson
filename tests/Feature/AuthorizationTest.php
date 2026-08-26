<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;

it('ผู้ใช้ที่ยังไม่ล็อกอินถูกส่งไปหน้า login', function (string $route): void {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['dashboard', 'products.index', 'customers.index', 'suppliers.index', 'users.index']);

it('sales แก้ไขสินค้าไม่ได้ (403)', function (): void {
    actingAsRole(RoleName::Sales);
    $product = Product::factory()->create();

    $this->get(route('products.edit', $product))->assertForbidden();
    $this->delete(route('products.destroy', $product))->assertForbidden();

    expect(Product::find($product->id))->not->toBeNull();
});

it('sales ดูรายการสินค้าได้แต่ไม่เห็นราคาทุน', function (): void {
    actingAsRole(RoleName::Sales);
    Product::factory()->create(['cost_price' => '99999.00', 'list_price' => '135000.00']);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('135,000.00')
        ->assertDontSee('99,999.00');
});

it('ผู้จัดการฝ่ายขายเห็นราคาทุนได้เพราะต้องตรวจ margin ก่อนอนุมัติ', function (): void {
    actingAsRole(RoleName::SalesManager);
    Product::factory()->create(['cost_price' => '99999.00']);

    $this->get(route('products.index'))->assertOk()->assertSee('99,999.00');
});

it('warehouse แตะข้อมูลลูกค้าเชิงแก้ไขไม่ได้', function (): void {
    actingAsRole(RoleName::Warehouse);
    $customer = Customer::factory()->create();

    $this->get(route('customers.index'))->assertOk();
    $this->get(route('customers.edit', $customer))->assertForbidden();
    $this->post(route('customers.store'), ['code' => 'X'])->assertForbidden();
});

it('viewer ดูได้อย่างเดียว สร้างอะไรไม่ได้เลย', function (): void {
    actingAsRole(RoleName::Viewer);

    $this->get(route('products.index'))->assertOk();
    $this->get(route('products.create'))->assertForbidden();
    $this->get(route('customers.create'))->assertForbidden();
    $this->get(route('users.index'))->assertForbidden();
});

it('engineer เข้าหน้าจัดการผู้ใช้ไม่ได้', function (): void {
    actingAsRole(RoleName::Engineer);

    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('users.create'))->assertForbidden();
});

it('admin เข้าได้ทุกหน้าของ Phase 1', function (string $route): void {
    actingAsRole(RoleName::Admin);

    $this->get(route($route))->assertOk();
})->with([
    'dashboard', 'products.index', 'products.create',
    'customers.index', 'customers.create',
    'suppliers.index', 'suppliers.create',
    'categories.index', 'brands.index', 'warehouses.index',
    'users.index', 'users.create',
]);

it('ลบถาวรตาม PDPA สงวนไว้ให้ admin เท่านั้น', function (): void {
    seedRoles();
    $customer = Customer::factory()->create();

    $sales = User::factory()->create();
    $sales->assignRole(RoleName::Sales->value);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    expect($sales->can('forceDelete', $customer))->toBeFalse()
        ->and($admin->can('forceDelete', $customer))->toBeTrue()
        // ฝ่ายขายยังลบแบบ soft delete และส่งออกข้อมูลได้ตามปกติ
        ->and($sales->can('delete', $customer))->toBeTrue()
        ->and($sales->can('export', $customer))->toBeTrue();
});

it('เมนูซ่อนรายการที่ผู้ใช้ไม่มีสิทธิ์', function (): void {
    actingAsRole(RoleName::Engineer);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('สินค้า / อะไหล่')
        ->assertDontSee('ผู้ใช้งาน');
});
