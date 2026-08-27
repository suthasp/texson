<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\StockDocumentStatus;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockService;

// ── หน้าจอสต็อก ─────────────────────────────────────────

it('หน้าสต็อกคงเหลือแสดงยอดของแต่ละคลัง', function (): void {
    actingAsRole(RoleName::Warehouse);

    $product = Product::factory()->create(['sku' => 'UPS-TEST-1']);
    $warehouse = Warehouse::factory()->create(['code' => 'HQ']);
    app(StockService::class)->receive($product, $warehouse, '12.500');

    $this->get(route('stock.index'))
        ->assertOk()
        ->assertSee('UPS-TEST-1')
        ->assertSee('12.500')
        ->assertSee('HQ');
});

it('กรองเฉพาะสินค้าที่เหลือน้อยกว่าจุดสั่งซื้อได้', function (): void {
    actingAsRole(RoleName::Warehouse);

    $warehouse = Warehouse::factory()->create();
    $stock = app(StockService::class);

    $low = Product::factory()->create(['sku' => 'LOW-SKU', 'min_stock' => 10]);
    $healthy = Product::factory()->create(['sku' => 'OK-SKU', 'min_stock' => 1]);

    $stock->receive($low, $warehouse, '2');
    $stock->receive($healthy, $warehouse, '50');

    $this->get(route('stock.index', ['low_stock' => 1]))
        ->assertOk()
        ->assertSee('LOW-SKU')
        ->assertDontSee('OK-SKU');
});

it('ยอดที่ถูกจองทำให้สินค้าเข้าเกณฑ์เหลือน้อยได้ แม้ยอดในมือจะพอ', function (): void {
    actingAsRole(RoleName::Warehouse);

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['sku' => 'RESERVED-SKU', 'min_stock' => 10]);

    $stock = app(StockService::class);
    $stock->receive($product, $warehouse, '12');
    $stock->reserve($product, $warehouse, '8');

    // ในมือ 12 (มากกว่า 10) แต่พร้อมขายเหลือ 4 จึงต้องถูกนับว่าเหลือน้อย
    expect(StockLevel::query()->belowMinimum()->count())->toBe(1);

    $this->get(route('stock.index', ['low_stock' => 1]))
        ->assertOk()
        ->assertSee('RESERVED-SKU');
});

it('หน้า ledger แสดงประวัติพร้อมยอดหลังรายการ', function (): void {
    actingAsRole(RoleName::Warehouse);

    $product = Product::factory()->create(['sku' => 'LEDGER-SKU']);
    $warehouse = Warehouse::factory()->create();
    $stock = app(StockService::class);

    $stock->receive($product, $warehouse, '10');
    $stock->issue($product, $warehouse, '4');

    $this->get(route('stock.ledger'))
        ->assertOk()
        ->assertSee('LEDGER-SKU')
        ->assertSee('6.000');
});

it('sales ดูยอดคงเหลือได้แต่เข้า ledger ไม่ได้', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get(route('stock.index'))->assertOk();
    $this->get(route('stock.ledger'))->assertForbidden();
});

it('ผู้จัดการฝ่ายขายเข้า ledger ได้', function (): void {
    actingAsRole(RoleName::SalesManager);

    $this->get(route('stock.ledger'))->assertOk();
});

// ── เอกสารคลังผ่าน HTTP ─────────────────────────────────

it('สร้างและ post ใบรับสินค้าผ่านหน้าเว็บได้ครบวงจร', function (): void {
    actingAsRole(RoleName::Warehouse);

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $this->post(route('goods-receipts.store'), [
        'warehouse_id' => $warehouse->id,
        'received_date' => now()->toDateString(),
        'items' => [
            ['product_id' => $product->id, 'qty' => '15', 'unit_cost' => '250'],
        ],
    ])->assertRedirect();

    $receipt = GoodsReceipt::firstOrFail();
    expect($receipt->status)->toBe(StockDocumentStatus::Draft);

    $this->post(route('goods-receipts.post', $receipt))->assertRedirect();

    expect($receipt->refresh()->status)->toBe(StockDocumentStatus::Posted)
        ->and((string) app(StockService::class)->levelFor($product, $warehouse)->qty_on_hand)->toBe('15.000');
});

it('ใบรับสินค้าที่ไม่มีรายการเลยบันทึกไม่ได้', function (): void {
    actingAsRole(RoleName::Warehouse);

    $this->post(route('goods-receipts.store'), [
        'warehouse_id' => Warehouse::factory()->create()->id,
        'received_date' => now()->toDateString(),
        'items' => [],
    ])->assertSessionHasErrors('items');

    expect(GoodsReceipt::count())->toBe(0);
});

it('post ใบที่ของไม่พอได้ข้อความบอกผู้ใช้ ไม่ใช่ error 500', function (): void {
    actingAsRole(RoleName::Warehouse);

    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $this->post(route('stock-transfers.store'), [
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty' => '5']],
    ])->assertRedirect();

    $transfer = StockTransfer::firstOrFail();

    $this->from(route('stock-transfers.show', $transfer))
        ->post(route('stock-transfers.post', $transfer))
        ->assertRedirect(route('stock-transfers.show', $transfer))
        ->assertSessionHas('error');

    expect($transfer->refresh()->status)->toBe(StockDocumentStatus::Draft);
});

it('API ที่ของไม่พอตอบ 422 พร้อมรายการที่ขาด', function (): void {
    $user = userWithRole(RoleName::Warehouse);

    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $product = Product::factory()->create(['sku' => 'SHORT-SKU']);

    $transfer = app(App\Services\StockTransferService::class);
    $this->actingAs($user);

    $draft = $transfer->createDraft([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty' => '5']],
    ]);

    $response = $this->postJson(route('stock-transfers.post', $draft));

    $response->assertStatus(422)
        ->assertJsonPath('shortages.0.sku', 'SHORT-SKU')
        ->assertJsonPath('shortages.0.requested', '5.000')
        ->assertJsonPath('shortages.0.available', '0.000')
        ->assertJsonStructure(['message', 'shortages']);
});

it('สร้างและ post ใบปรับปรุงผ่านหน้าเว็บได้', function (): void {
    actingAsRole(RoleName::Warehouse);

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $this->post(route('stock-adjustments.store'), [
        'warehouse_id' => $warehouse->id,
        'reason' => 'opening',
        'adjusted_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty_counted' => '30']],
    ])->assertRedirect();

    $adjustment = StockAdjustment::firstOrFail();
    $this->post(route('stock-adjustments.post', $adjustment))->assertRedirect();

    expect((string) app(StockService::class)->levelFor($product, $warehouse)->qty_on_hand)->toBe('30.000');
});

it('ปุ่ม post หายไปเมื่อใบถูก post ไปแล้ว', function (): void {
    actingAsRole(RoleName::Warehouse);

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $receipt = app(App\Services\GoodsReceiptService::class)->createDraft([
        'warehouse_id' => $warehouse->id,
        'received_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty' => '1', 'unit_cost' => '1']],
    ]);

    // ตรวจที่ปุ่มจริง (URL ของ action) ไม่ใช่ข้อความ เพราะคำว่า "บันทึกเข้าสต็อก"
    // ปรากฏในป้ายกำกับ "บันทึกเข้าสต็อกเมื่อ" ของแผงข้อมูลเอกสารด้วย
    $this->get(route('goods-receipts.show', $receipt))
        ->assertOk()
        ->assertSee(route('goods-receipts.post', $receipt), escape: false)
        ->assertSee(route('goods-receipts.edit', $receipt), escape: false);

    app(App\Services\GoodsReceiptService::class)->post($receipt);

    $this->get(route('goods-receipts.show', $receipt->refresh()))
        ->assertOk()
        ->assertDontSee(route('goods-receipts.post', $receipt), escape: false)
        ->assertDontSee(route('goods-receipts.edit', $receipt), escape: false);
});

it('viewer ดูเอกสารคลังได้แต่สร้างไม่ได้', function (): void {
    actingAsRole(RoleName::Viewer);

    $this->get(route('goods-receipts.index'))->assertOk();
    $this->get(route('goods-receipts.create'))->assertForbidden();
    $this->post(route('goods-receipts.store'), [])->assertForbidden();
});

it('sales แตะเอกสารคลังไม่ได้เลย', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get(route('goods-receipts.index'))->assertForbidden();
    $this->get(route('stock-adjustments.index'))->assertForbidden();
});

it('หน้าทะเบียน serial แสดงรายการที่รับเข้ามาแล้ว', function (): void {
    actingAsRole(RoleName::Warehouse);

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->serialized()->create();

    $receipt = app(App\Services\GoodsReceiptService::class)->createDraft([
        'warehouse_id' => $warehouse->id,
        'received_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty' => '2', 'unit_cost' => '100', 'serial_numbers' => "SN-X1\nSN-X2"]],
    ]);

    app(App\Services\GoodsReceiptService::class)->post($receipt);

    $this->get(route('serial-numbers.index'))
        ->assertOk()
        ->assertSee('SN-X1')
        ->assertSee('SN-X2');
});
