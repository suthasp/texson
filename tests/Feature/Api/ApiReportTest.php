<?php

declare(strict_types=1);

use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/**
 * GET /api/v1/reports/low-stock และ /reports/sales-summary (spec 6)
 */

// ── สินค้าเหลือน้อย ─────────────────────────────────────

it('รายงานสินค้าเหลือน้อยคืนเฉพาะรายการที่ต่ำกว่าจุดสั่งซื้อ', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $warehouse = Warehouse::factory()->create(['code' => 'HQ']);
    $stock = app(StockService::class);

    $low = Product::factory()->create(['sku' => 'LOW-SKU', 'min_stock' => 10]);
    $healthy = Product::factory()->create(['sku' => 'OK-SKU', 'min_stock' => 1]);

    $stock->receive($low, $warehouse, '2');
    $stock->receive($healthy, $warehouse, '50');

    $response = $this->getJson('/api/v1/reports/low-stock')->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.warehouse.code'))->toBe('HQ')
        ->and($response->json('data.0.qty_available'))->toBe('2.000')
        ->and($response->json('data.0.is_below_minimum'))->toBeTrue()
        ->and($response->json('meta.basis'))->toBe('qty_available < products.min_stock');
});

it('ของที่ถูกจองไว้ทำให้สินค้าเข้าเกณฑ์เหลือน้อยได้ แม้ยอดในมือจะพอ', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['sku' => 'RESERVED-SKU', 'min_stock' => 10]);

    $stock = app(StockService::class);
    $stock->receive($product, $warehouse, '12');
    $stock->reserve($product, $warehouse, '8');

    $response = $this->getJson('/api/v1/reports/low-stock')->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.qty_available'))->toBe('4.000');
});

it('กรองรายงานเหลือน้อยตามคลังได้', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $hq = Warehouse::factory()->create();
    $van = Warehouse::factory()->create();
    $product = Product::factory()->create(['min_stock' => 10]);

    $stock = app(StockService::class);
    $stock->receive($product, $hq, '1');
    $stock->receive($product, $van, '2');

    expect($this->getJson('/api/v1/reports/low-stock')->json('meta.total'))->toBe(2)
        ->and($this->getJson("/api/v1/reports/low-stock?warehouse_id={$hq->id}")->json('meta.total'))->toBe(1);
});

it('ผู้ใช้ที่ยังไม่ถูกกำหนด role เรียกรายงานไม่ได้เลย', function (string $path): void {
    seedRoles();

    // ทุก role ในระบบมีสิทธิ์ดูยอดคงเหลือ จึงต้องทดสอบด้วยบัญชีที่ยังไม่ได้รับ role
    // ซึ่งเป็นสภาพจริงของผู้ใช้ที่เพิ่งถูกสร้างและยังไม่ถูกจัดสิทธิ์
    Sanctum::actingAs(App\Models\User::factory()->create());

    $this->getJson($path)->assertStatus(403);
})->with([
    '/api/v1/reports/low-stock',
    '/api/v1/reports/sales-summary',
]);

// ── สรุปยอดขาย ─────────────────────────────────────────

it('สรุปยอดนับใบตามสถานะและคำนวณ win rate จากใบที่ตัดสินใจแล้วเท่านั้น', function (): void {
    $sales = userWithRole(RoleName::Sales);

    // ตอบรับ 2 ใบ ปฏิเสธ 1 ใบ → win rate = 2/3 = 66.67%
    Quotation::factory()->forSales($sales)->status(QuotationStatus::Accepted)->count(2)->create([
        'grand_total' => '100000.00',
        'after_discount' => '93457.94',
        'cost_total' => '60000.00',
    ]);
    Quotation::factory()->forSales($sales)->status(QuotationStatus::Rejected)->create(['grand_total' => '50000.00']);
    // ใบที่ยังเปิดอยู่ต้องไม่ถูกนับใน win rate
    Quotation::factory()->forSales($sales)->status(QuotationStatus::Sent)->count(3)->create(['grand_total' => '10000.00']);

    Sanctum::actingAs($sales);

    $response = $this->getJson('/api/v1/reports/sales-summary')->assertOk();

    expect($response->json('data.quotations.total'))->toBe(6)
        ->and($response->json('data.quotations.accepted'))->toBe(2)
        ->and($response->json('data.quotations.rejected'))->toBe(1)
        ->and($response->json('data.quotations.open'))->toBe(3)
        ->and($response->json('data.amounts.accepted'))->toBe('200000.00')
        ->and($response->json('data.amounts.open'))->toBe('30000.00')
        ->and($response->json('data.amounts.accepted_margin'))->toBe('66915.88')
        ->and($response->json('data.win_rate_percent'))->toBe('66.67')
        // ระบุฐานที่ใช้คำนวณไว้ชัด เพราะ Phase 4 จะเพิ่มฐานจากใบสั่งขาย
        ->and($response->json('meta.basis'))->toBe('quotations')
        ->and($response->json('meta.win_rate_excludes_open'))->toBeTrue();
});

it('ช่วงวันที่เริ่มต้นคือเดือนปัจจุบัน และระบุเองได้', function (): void {
    $sales = userWithRole(RoleName::Sales);

    Quotation::factory()->forSales($sales)->create(['issue_date' => now()->toDateString()]);
    Quotation::factory()->forSales($sales)->create(['issue_date' => now()->subMonths(3)->toDateString()]);

    Sanctum::actingAs($sales);

    expect($this->getJson('/api/v1/reports/sales-summary')->json('data.quotations.total'))->toBe(1);

    $wide = $this->getJson('/api/v1/reports/sales-summary?from='.now()->subMonths(6)->toDateString());

    expect($wide->json('data.quotations.total'))->toBe(2);
});

it('ช่วงวันที่กลับหัวกลับหางถูกปฏิเสธด้วย 422', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $this->getJson('/api/v1/reports/sales-summary?from=2026-08-31&to=2026-08-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors('to');
});

it('sales เห็นสรุปยอดเฉพาะใบของตัวเอง', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    Quotation::factory()->forSales($mine)->create(['grand_total' => '1000.00']);
    Quotation::factory()->forSales($theirs)->count(5)->create(['grand_total' => '9999.00']);

    Sanctum::actingAs($mine);

    expect($this->getJson('/api/v1/reports/sales-summary')->json('data.quotations.total'))->toBe(1)
        ->and($this->getJson('/api/v1/reports/sales-summary')->json('data.amounts.quoted'))->toBe('1000.00');
});

it('ไม่มีใบเลยก็ตอบได้โดยไม่หารด้วยศูนย์', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $response = $this->getJson('/api/v1/reports/sales-summary')->assertOk();

    expect($response->json('data.win_rate_percent'))->toBe('0.00')
        ->and($response->json('data.amounts.quoted'))->toBe('0.00');
});

it('คลังสินค้าเรียกสรุปยอดขายไม่ได้', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $this->getJson('/api/v1/reports/sales-summary')->assertStatus(403);
});

// ── แดชบอร์ดผ่าน API (Phase 5) ──────────────────────────

it('แดชบอร์ดผ่าน API ให้ตัวเลขชุดเดียวกับหน้าเว็บ', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $warehouse = Warehouse::factory()->create(['is_default' => true]);

    App\Models\SalesOrder::factory()->forSales($sales)->inWarehouse($warehouse)
        ->status(App\Enums\SalesOrderStatus::Delivered)
        ->create(['grand_total' => '321000.00', 'after_discount' => '300000.00', 'cost_total' => '200000.00']);

    Sanctum::actingAs($sales);

    $response = $this->getJson('/api/v1/reports/dashboard')->assertOk();

    expect($response->json('data.sales_this_month.ordered'))->toBe('321000.00')
        ->and($response->json('data.sales_this_month.delivered'))->toBe('321000.00')
        // กำไร = 300,000 − 200,000
        ->and($response->json('data.sales_this_month.margin'))->toBe('100000.00')
        ->and($response->json('data.monthly_sales'))->toHaveCount(12)
        ->and($response->json('meta.amounts_include_vat'))->toBeTrue();

    // ตรงกับ ReportService ที่หน้าเว็บใช้
    $direct = app(App\Services\ReportService::class)->salesSummary(
        Carbon::now()->startOfMonth(),
        Carbon::now()->endOfDay(),
        $sales,
    );

    expect($response->json('data.sales_this_month.ordered'))->toBe($direct['ordered']);
});

it('คลังสินค้าเรียกแดชบอร์ดผ่าน API ไม่ได้ เพราะไม่มีสิทธิ์ดูใบเสนอราคา', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $this->getJson('/api/v1/reports/dashboard')->assertStatus(403);
});
