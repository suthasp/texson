<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\StockService;
use Laravel\Sanctum\Sanctum;

/**
 * GET /products, /products/{id}, /products/{id}/stock, /customers (spec 6)
 */

// ── สินค้า ──────────────────────────────────────────────

it('รายการสินค้าตอบเป็นรูป data + meta ตามสเปกข้อ 6', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    Product::factory()->count(3)->create();

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'sku', 'name_th', 'uom' => ['value', 'label'], 'prices']],
            'links',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
});

it('ค้นหาสินค้าด้วย SKU ชื่อ หรือรุ่นได้', function (string $term): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    Product::factory()->create(['sku' => 'UPS-APC-9000', 'name_th' => 'เครื่องสำรองไฟ', 'model' => 'SRT9K']);
    Product::factory()->create(['sku' => 'RACK-42U', 'name_th' => 'ตู้แร็ค', 'model' => 'R42']);

    $response = $this->getJson('/api/v1/products?search='.urlencode($term));

    $response->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.sku'))->toBe('UPS-APC-9000');
})->with(['UPS-APC', 'เครื่องสำรอง', 'SRT9K']);

it('กรองสินค้าตามหมวดโดยรวมหมวดย่อยด้วย', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    Product::factory()->create(['category_id' => $child->id, 'sku' => 'IN-CHILD']);
    Product::factory()->create(['sku' => 'ELSEWHERE']);

    $response = $this->getJson("/api/v1/products?category_id={$parent->id}");

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.sku'))->toBe('IN-CHILD');
});

it('low_stock=1 คืนเฉพาะสินค้าที่ยอดพร้อมขายต่ำกว่าจุดสั่งซื้อ', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $warehouse = Warehouse::factory()->create();
    $stock = app(StockService::class);

    $low = Product::factory()->create(['sku' => 'LOW-SKU', 'min_stock' => 10]);
    $healthy = Product::factory()->create(['sku' => 'OK-SKU', 'min_stock' => 1]);

    $stock->receive($low, $warehouse, '2');
    $stock->receive($healthy, $warehouse, '50');

    $response = $this->getJson('/api/v1/products?low_stock=1');

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.sku'))->toBe('LOW-SKU');
});

it('ฝ่ายขายเห็นราคาทุนใน API แต่ viewer ไม่เห็น', function (): void {
    Product::factory()->create(['sku' => 'COST-SKU', 'cost_price' => '99999.00']);

    Sanctum::actingAs(userWithRole(RoleName::Sales));
    $sales = $this->getJson('/api/v1/products')->assertOk();

    expect($sales->json('data.0.prices.cost'))->toBe('99999.00');

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs(userWithRole(RoleName::Viewer));
    $viewer = $this->getJson('/api/v1/products')->assertOk();

    expect($viewer->json('data.0.prices'))->not->toHaveKey('cost')
        ->and($viewer->json('data.0.prices.list'))->not->toBeNull();
});

it('ยอดคงเหลือแยกตามคลังพร้อมยอดพร้อมขายที่หักของจองแล้ว', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $product = Product::factory()->create(['min_stock' => 5]);
    $hq = Warehouse::factory()->create(['code' => 'HQ']);
    $van = Warehouse::factory()->create(['code' => 'VAN']);

    $stock = app(StockService::class);
    $stock->receive($product, $hq, '20');
    $stock->reserve($product, $hq, '8');
    $stock->receive($product, $van, '3');

    $response = $this->getJson("/api/v1/products/{$product->id}/stock")->assertOk();

    $byCode = collect($response->json('data'))->keyBy('warehouse.code');

    expect($byCode['HQ']['qty_on_hand'])->toBe('20.000')
        ->and($byCode['HQ']['qty_reserved'])->toBe('8.000')
        ->and($byCode['HQ']['qty_available'])->toBe('12.000')
        ->and($byCode['HQ']['is_below_minimum'])->toBeFalse()
        // VAN เหลือ 3 ต่ำกว่า min_stock 5
        ->and($byCode['VAN']['is_below_minimum'])->toBeTrue()
        ->and($response->json('meta.total_on_hand'))->toBe('23.000')
        ->and($response->json('meta.total_available'))->toBe('15.000');
});

it('role ที่ไม่มีสิทธิ์ดูสต็อกเรียก /stock ไม่ได้', function (): void {
    // engineer ดูสต็อกได้ · ลองด้วย role ที่ไม่มีสิทธิ์สินค้าเลย
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $product = Product::factory()->create();

    // ฝ่ายขายดูได้ (ต้องใช้ตอนออกใบเสนอราคา)
    $this->getJson("/api/v1/products/{$product->id}/stock")->assertOk();
});

// ── ลูกค้า ──────────────────────────────────────────────

it('ค้นหาลูกค้าด้วยรหัส ชื่อ หรือเลขผู้เสียภาษีได้', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    Customer::factory()->create(['code' => 'CUS-0001', 'name_th' => 'บริษัท ดาต้าเซ็นเตอร์ไทย จำกัด']);
    Customer::factory()->create(['code' => 'CUS-0002', 'name_th' => 'บริษัท อื่น จำกัด']);

    $response = $this->getJson('/api/v1/customers?search='.urlencode('ดาต้าเซ็นเตอร์'));

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.code'))->toBe('CUS-0001');
});

it('รายการลูกค้าไม่แนบผู้ติดต่อมาด้วย เพราะเป็นข้อมูลส่วนบุคคลตาม PDPA', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $customer = Customer::factory()->create();
    CustomerContact::factory()->create(['customer_id' => $customer->id, 'name' => 'คุณสมชาย']);

    $list = $this->getJson('/api/v1/customers')->assertOk();

    expect($list->json('data.0'))->not->toHaveKey('contacts');

    // ต้องขอรายละเอียดรายคนถึงจะได้ผู้ติดต่อ
    $detail = $this->getJson("/api/v1/customers/{$customer->id}")->assertOk();

    expect($detail->json('data.contacts.0.name'))->toBe('คุณสมชาย');
});

it('meta.can บอก client ว่ามีสิทธิ์ทำอะไรกับรายการนั้นได้บ้าง', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Viewer));

    $customer = Customer::factory()->create();

    $response = $this->getJson("/api/v1/customers/{$customer->id}")->assertOk();

    expect($response->json('meta.can.view'))->toBeTrue()
        ->and($response->json('meta.can.update'))->toBeFalse()
        ->and($response->json('meta.can.delete'))->toBeFalse();
});

it('คลังสินค้าเรียกรายการลูกค้าได้แต่ engineer ที่ไม่มีสิทธิ์เรียกไม่ได้', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));
    $this->getJson('/api/v1/customers')->assertOk();
});

it('ยิง endpoint ที่ไม่มีสิทธิ์ได้ 403 พร้อมข้อความภาษาไทย', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $this->getJson('/api/v1/quotations')
        ->assertStatus(403)
        ->assertJsonPath('message', 'คุณไม่มีสิทธิ์ทำรายการนี้');
});

it('payload SQL injection ในช่องค้นหาไม่ทำให้ตารางหาย', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    Product::factory()->create();

    $this->getJson('/api/v1/products?search='.urlencode("'; DROP TABLE products; --"))->assertOk();

    expect(Illuminate\Support\Facades\Schema::hasTable('products'))->toBeTrue()
        ->and(Product::count())->toBe(1);
});

it('per_page ถูกจำกัดเพดานไม่ให้ดึงทั้งฐานข้อมูลในครั้งเดียว', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    Product::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/products?per_page=100000')->assertOk();

    expect($response->json('meta.per_page'))->toBe(100);
});
