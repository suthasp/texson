<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\StockDocumentStatus;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Laravel\Sanctum\Sanctum;

/**
 * POST /api/v1/stock/adjust — role: warehouse|admin (spec 6)
 *
 * เงื่อนไขสำคัญ: ต้องเขียน ledger ครบเหมือนกดจากหน้าเว็บ
 * ยอดคงเหลือต้องเท่ากับผลรวม ledger เสมอ (DoD ของ Phase 2)
 */
function adjustPayload(Warehouse $warehouse, Product $product, string $counted, array $overrides = []): array
{
    return array_merge([
        'warehouse_id' => $warehouse->id,
        'reason' => 'stock_count',
        'adjusted_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty_counted' => $counted]],
    ], $overrides);
}

it('คลังสินค้าปรับสต็อกผ่าน API แล้วยอดเปลี่ยนพร้อมมีรายการใน ledger', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    app(StockService::class)->receive($product, $warehouse, '10');

    $response = $this->postJson('/api/v1/stock/adjust', adjustPayload($warehouse, $product, '7'));

    // 201 เพราะเป็นการสร้างใบปรับปรุงใบใหม่ ไม่ใช่แค่แก้ยอด
    $response->assertStatus(201)
        ->assertJsonPath('data.status.value', StockDocumentStatus::Posted->value)
        ->assertJsonPath('meta.posted', true)
        ->assertJsonPath('data.items.0.qty_system', '10.000')
        ->assertJsonPath('data.items.0.qty_counted', '7.000')
        ->assertJsonPath('data.items.0.qty_diff', '-3.000');

    $level = app(StockService::class)->levelFor($product, $warehouse);

    expect((string) $level->qty_on_hand)->toBe('7.000');

    // ยอดคงเหลือต้องเท่ากับผลรวม ledger เสมอ
    $ledger = StockMovement::query()
        ->where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->sum('qty');

    expect(number_format((float) $ledger, 3, '.', ''))->toBe('7.000');
});

it('ส่ง post=false ได้ใบร่างที่ยังไม่กระทบสต็อก', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    app(StockService::class)->receive($product, $warehouse, '10');

    $this->postJson('/api/v1/stock/adjust', adjustPayload($warehouse, $product, '7', ['post' => false]))
        ->assertStatus(201)
        ->assertJsonPath('data.status.value', StockDocumentStatus::Draft->value)
        ->assertJsonPath('meta.posted', false);

    expect((string) app(StockService::class)->levelFor($product, $warehouse)->qty_on_hand)->toBe('10.000')
        ->and(StockAdjustment::count())->toBe(1);
});

it('ปรับสต็อกจนติดลบไม่ได้ — ตอบ 422 พร้อมรายการที่ขาด (spec 6)', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $warehouse = Warehouse::factory()->create(['code' => 'HQ']);
    $product = Product::factory()->create(['sku' => 'SHORT-SKU']);

    // ใบปรับปรุงรับ "จำนวนที่นับได้" ซึ่งติดลบไม่ได้อยู่แล้ว จึงทำให้สต็อกติดลบผ่าน endpoint นี้ไม่ได้
    // เคสของไม่พอที่เกิดจริงคือการโอนคลัง — ใช้พิสูจน์รูปแบบ error ที่สเปกข้อ 6 กำหนดไว้
    app(StockService::class)->receive($product, $warehouse, '2');

    $other = Warehouse::factory()->create();

    $transfer = app(App\Services\StockTransferService::class)->createDraft([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $other->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty' => '10']],
    ]);

    $this->postJson(route('stock-transfers.post', $transfer))
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'shortages' => [['product_id', 'sku', 'warehouse_code', 'requested', 'available']]])
        ->assertJsonPath('shortages.0.sku', 'SHORT-SKU')
        ->assertJsonPath('shortages.0.requested', '10.000')
        ->assertJsonPath('shortages.0.available', '2.000');
});

it('ฝ่ายขายปรับสต็อกผ่าน API ไม่ได้ (403)', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $this->postJson('/api/v1/stock/adjust', adjustPayload($warehouse, $product, '5'))
        ->assertStatus(403);

    expect(StockAdjustment::count())->toBe(0);
});

it('viewer ที่ดูเอกสารได้แต่สร้างไม่ได้ ก็ปรับสต็อกไม่ได้', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Viewer));

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    // ต้องได้ 403 ไม่ใช่ 422 — ตรวจสิทธิ์ต้องมาก่อน validation ไม่งั้นรั่วว่ากฎ validation คืออะไร
    $this->postJson('/api/v1/stock/adjust', [])->assertStatus(403);
    $this->postJson('/api/v1/stock/adjust', adjustPayload($warehouse, $product, '5'))->assertStatus(403);
});

it('payload ที่ไม่ครบตอบ 422 พร้อม errors ตามรูปแบบในสเปกข้อ 6', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $this->postJson('/api/v1/stock/adjust', ['items' => []])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors'])
        ->assertJsonValidationErrors(['warehouse_id', 'reason', 'adjusted_at', 'items']);
});

it('สินค้าที่ถูกลบไปแล้วปรับสต็อกไม่ได้', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $product->delete();

    $this->postJson('/api/v1/stock/adjust', adjustPayload($warehouse, $product, '5'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items.0.product_id');
});

it('ledger ผ่าน API กรองตามสินค้าและคลังได้', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $product = Product::factory()->create(['sku' => 'LEDGER-SKU']);

    $stock = app(StockService::class);
    $stock->receive($product, $warehouse, '10');
    $stock->issue($product, $warehouse, '4');
    $stock->receive($product, $other, '99');

    $response = $this->getJson("/api/v1/stock/ledger?product_id={$product->id}&warehouse_id={$warehouse->id}")
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2)
        // เรียงล่าสุดก่อน — ยอดหลังรายการของตัวล่าสุดคือ 6
        ->and($response->json('data.0.balance_after'))->toBe('6.000')
        ->and($response->json('data.0.product.sku'))->toBe('LEDGER-SKU');
});

it('ฝ่ายขายเข้า ledger ผ่าน API ไม่ได้', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $this->getJson('/api/v1/stock/ledger')->assertStatus(403);
});
