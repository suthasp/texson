<?php

declare(strict_types=1);

use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Enums\SalesOrderStatus;
use App\Exports\ProductStockExport;
use App\Exports\QuotationReportExport;
use App\Exports\StockLedgerExport;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * หน้ารายงานและไฟล์ส่งออก Excel (spec 5)
 */
beforeEach(function (): void {
    $this->warehouse = Warehouse::factory()->create(['code' => 'HQ', 'is_default' => true]);
    $this->customer = Customer::factory()->create(['code' => 'CUS-0001', 'name_th' => 'ลูกค้าทดสอบ']);
});

// ── หน้าจอ ──────────────────────────────────────────────

it('เปิดหน้ารายงานได้และเห็นยอดที่ถูกต้อง', function (): void {
    $sales = actingAsRole(RoleName::Sales);

    SalesOrder::factory()->forSales($sales)->inWarehouse($this->warehouse)
        ->status(SalesOrderStatus::Delivered)
        ->create([
            'customer_id' => $this->customer->id,
            'grand_total' => '123456.78',
            'after_discount' => '115380.17',
            'order_date' => now()->toDateString(),
        ]);

    $this->get(route('reports.index'))
        ->assertOk()
        ->assertSee('123,456.78')
        ->assertSee('ลูกค้าทดสอบ');
});

it('เลือกช่วงวันที่เองได้ และช่วงที่กลับหัวกลับหางถูกปฏิเสธ', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get(route('reports.index', ['from' => '2026-08-01', 'to' => '2026-08-31']))->assertOk();

    $this->get(route('reports.index', ['from' => '2026-08-31', 'to' => '2026-08-01']))
        ->assertSessionHasErrors('to');
});

it('แดชบอร์ดแสดงยอดขายเดือนนี้และปีนี้', function (): void {
    $sales = actingAsRole(RoleName::Sales);

    SalesOrder::factory()->forSales($sales)->inWarehouse($this->warehouse)
        ->status(SalesOrderStatus::Reserved)
        ->create([
            'customer_id' => $this->customer->id,
            'grand_total' => '250000.00',
            'order_date' => now()->toDateString(),
        ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('ยอดขายเดือนนี้')
        ->assertSee('ยอดขายปีนี้')
        ->assertSee('250,000.00');
});

it('แดชบอร์ดแสดง win rate จากใบที่ลูกค้าตัดสินใจแล้ว', function (): void {
    $sales = actingAsRole(RoleName::Sales);

    Quotation::factory()->forSales($sales)->status(QuotationStatus::Accepted)->count(3)
        ->create(['customer_id' => $this->customer->id, 'grand_total' => '1000.00']);
    Quotation::factory()->forSales($sales)->status(QuotationStatus::Rejected)
        ->create(['customer_id' => $this->customer->id, 'grand_total' => '1000.00']);

    // 3/4 = 75.0%
    $this->get(route('dashboard'))->assertOk()->assertSee('75.0%');
});

it('viewer เปิดหน้ารายงานได้แต่ไม่เห็นปุ่มส่งออก', function (): void {
    actingAsRole(RoleName::Viewer);

    $this->get(route('reports.index'))
        ->assertOk()
        ->assertDontSee(route('reports.export.products'), escape: false);

    $this->get(route('reports.export.products'))->assertForbidden();
});

it('ผู้ใช้ที่ยังไม่ได้รับ role เข้าหน้ารายงานไม่ได้', function (): void {
    seedRoles();
    $this->actingAs(App\Models\User::factory()->create());

    $this->get(route('reports.index'))->assertForbidden();
});

it('เมนูรายงานขึ้นเมื่อมีสิทธิ์ และหายเมื่อไม่มี', function (): void {
    actingAsRole(RoleName::Sales);
    $this->get(route('dashboard'))->assertOk()->assertSee(route('reports.index'), escape: false);

    $this->app['auth']->forgetGuards();
    seedRoles();
    $this->actingAs(App\Models\User::factory()->create());

    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('reports.index'), escape: false);
});

// ── ไฟล์ส่งออก ──────────────────────────────────────────

it('ดาวน์โหลดไฟล์สินค้าและสต็อกได้เป็น xlsx', function (): void {
    Excel::fake();
    // ชื่อไฟล์มีเวลาที่ออกรายงานต่อท้าย จึงต้องเทียบด้วย regex ไม่ใช่ชื่อตรง ๆ
    Excel::matchByRegex();
    actingAsRole(RoleName::Warehouse);

    Product::factory()->create(['sku' => 'EXPORT-SKU']);

    $this->get(route('reports.export.products'))->assertOk();

    Excel::assertDownloaded('/^texson_products_stock_\d{8}_\d{6}\.xlsx$/');
});

it('ดาวน์โหลดรายงานใบเสนอราคาตามช่วงวันที่ได้', function (): void {
    Excel::fake();
    // ชื่อไฟล์มีเวลาที่ออกรายงานต่อท้าย จึงต้องเทียบด้วย regex ไม่ใช่ชื่อตรง ๆ
    Excel::matchByRegex();
    actingAsRole(RoleName::Sales);

    $this->get(route('reports.export.quotations', ['from' => '2026-08-01', 'to' => '2026-08-31']))->assertOk();

    Excel::assertDownloaded('/^texson_quotations_20260801_20260831_\d{8}_\d{6}\.xlsx$/');
});

it('ดาวน์โหลด ledger ได้เมื่อมีสิทธิ์ดูประวัติสต็อก', function (): void {
    Excel::fake();
    // ชื่อไฟล์มีเวลาที่ออกรายงานต่อท้าย จึงต้องเทียบด้วย regex ไม่ใช่ชื่อตรง ๆ
    Excel::matchByRegex();
    actingAsRole(RoleName::Warehouse);

    $this->get(route('reports.export.ledger', ['from' => '2026-08-01', 'to' => '2026-08-31']))->assertOk();

    Excel::assertDownloaded('/^texson_stock_ledger_20260801_20260831_\d{8}_\d{6}\.xlsx$/');
});

it('ฝ่ายขายดาวน์โหลด ledger ไม่ได้ เพราะไม่มีสิทธิ์ดูประวัติสต็อก', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get(route('reports.export.ledger'))->assertForbidden();
});

// ── เนื้อหาในไฟล์ ───────────────────────────────────────

it('ไฟล์สินค้ามีหนึ่งแถวต่อคู่สินค้าและคลัง พร้อมยอดที่ถูกต้อง', function (): void {
    $user = userWithRole(RoleName::Warehouse);

    $product = Product::factory()->create(['sku' => 'MULTI-WH', 'cost_price' => '1000.00', 'min_stock' => 5]);
    $van = Warehouse::factory()->create(['code' => 'VAN']);

    $stock = app(StockService::class);
    $stock->receive($product, $this->warehouse, '20');
    $stock->reserve($product, $this->warehouse, '8');
    $stock->receive($product, $van, '3');

    $rows = (new ProductStockExport($user))->collection()
        ->where('sku', 'MULTI-WH')
        ->keyBy('warehouse');

    expect($rows)->toHaveCount(2)
        ->and($rows['HQ']['on_hand'])->toBe('20.000')
        ->and($rows['HQ']['reserved'])->toBe('8.000')
        ->and($rows['HQ']['available'])->toBe('12.000')
        ->and($rows['HQ']['is_low'])->toBeFalse()
        ->and($rows['HQ']['stock_value'])->toBe('20000.00')
        // VAN เหลือ 3 ต่ำกว่าขั้นต่ำ 5
        ->and($rows['VAN']['is_low'])->toBeTrue();
});

it('ไฟล์สินค้าซ่อนคอลัมน์ราคาทุนจาก role ที่ไม่มีสิทธิ์', function (): void {
    $warehouse = userWithRole(RoleName::Warehouse);
    $viewer = userWithRole(RoleName::Viewer);

    Product::factory()->create(['sku' => 'COST-CHECK', 'cost_price' => '9999.00']);

    expect((new ProductStockExport($warehouse))->headings())->toContain('ราคาทุน')
        ->and((new ProductStockExport($viewer))->headings())->not->toContain('ราคาทุน');
});

it('ไฟล์สินค้ากรองเฉพาะที่เหลือน้อยได้', function (): void {
    $user = userWithRole(RoleName::Warehouse);

    $low = Product::factory()->create(['sku' => 'LOW-SKU', 'min_stock' => 10]);
    $healthy = Product::factory()->create(['sku' => 'OK-SKU', 'min_stock' => 1]);

    $stock = app(StockService::class);
    $stock->receive($low, $this->warehouse, '2');
    $stock->receive($healthy, $this->warehouse, '50');

    $skus = (new ProductStockExport($user, lowStockOnly: true))->collection()->pluck('sku');

    expect($skus)->toContain('LOW-SKU')->not->toContain('OK-SKU');
});

it('ไฟล์ใบเสนอราคาของ sales มีเฉพาะใบของตัวเอง', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    Quotation::factory()->forSales($mine)->create([
        'customer_id' => $this->customer->id,
        'quote_no' => 'QT-202608-0001',
        'issue_date' => now()->toDateString(),
    ]);
    Quotation::factory()->forSales($theirs)->create([
        'customer_id' => $this->customer->id,
        'quote_no' => 'QT-202608-0002',
        'issue_date' => now()->toDateString(),
    ]);

    $export = new QuotationReportExport($mine, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

    $numbers = $export->query()->pluck('quote_no');

    expect($numbers)->toHaveCount(1)->toContain('QT-202608-0001');

    // ผู้จัดการได้ทั้งสองใบ
    $manager = userWithRole(RoleName::SalesManager);
    $managerExport = new QuotationReportExport($manager, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

    expect($managerExport->query()->pluck('quote_no'))->toHaveCount(2);
});

it('ไฟล์ ledger เรียงจากเก่าไปใหม่เพื่อให้ยอดหลังรายการอ่านต่อกันได้', function (): void {
    $user = userWithRole(RoleName::Warehouse);
    $this->actingAs($user);

    $product = Product::factory()->create(['sku' => 'LEDGER-SKU']);

    $stock = app(StockService::class);
    $stock->receive($product, $this->warehouse, '10');
    $stock->issue($product, $this->warehouse, '4');
    $stock->receive($product, $this->warehouse, '5');

    $export = new StockLedgerExport(Carbon::now()->subDay(), Carbon::now()->addDay());

    $balances = $export->query()->get()->map(fn ($movement): string => (string) $movement->balance_after);

    // 10 → 6 → 11 ต่อกันเป็นลูกโซ่
    expect($balances->all())->toBe(['10.000', '6.000', '11.000']);
});

it('ไฟล์ ledger กรองตามสินค้าและคลังได้', function (): void {
    $user = userWithRole(RoleName::Warehouse);
    $this->actingAs($user);

    $product = Product::factory()->create();
    $other = Product::factory()->create();
    $van = Warehouse::factory()->create(['code' => 'VAN']);

    $stock = app(StockService::class);
    $stock->receive($product, $this->warehouse, '10');
    $stock->receive($other, $this->warehouse, '10');
    $stock->receive($product, $van, '10');

    $filtered = new StockLedgerExport(
        Carbon::now()->subDay(),
        Carbon::now()->addDay(),
        productId: $product->id,
        warehouseId: $this->warehouse->id,
    );

    expect($filtered->query()->count())->toBe(1);
});
