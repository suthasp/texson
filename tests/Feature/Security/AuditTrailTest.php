<?php

declare(strict_types=1);

use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Enums\StockAdjustmentReason;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\QuotationService;
use App\Services\StockAdjustmentService;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit trail ตาม spec 8
 *
 * "ทุกการเปลี่ยน status เอกสาร/ปรับสต็อก/แก้ราคา ต้องมีใน activity log พร้อม before-after"
 */
beforeEach(function (): void {
    $this->warehouse = Warehouse::factory()->create(['code' => 'HQ', 'is_default' => true]);
    $this->customer = Customer::factory()->create(['code' => 'CUS-8001']);
});

// ── เนื้อหาที่ต้องถูกบันทึก ────────────────────────────

it('บันทึกค่าก่อน/หลังเมื่อใบเสนอราคาเปลี่ยนสถานะ', function (): void {
    $manager = actingAsRole(RoleName::SalesManager);

    $quotation = Quotation::factory()->forSales($manager)->status(QuotationStatus::Draft)->create([
        'customer_id' => $this->customer->id,
    ]);

    app(QuotationService::class)->submit($quotation);

    $entry = Activity::query()
        ->where('subject_type', Quotation::class)
        ->where('subject_id', $quotation->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->properties->get('old')['status'])->toBe(QuotationStatus::Draft->value)
        ->and($entry->properties->get('attributes')['status'])->toBe(QuotationStatus::PendingApproval->value)
        ->and($entry->causer_id)->toBe($manager->id);
});

it('บันทึกค่าก่อน/หลังเมื่อแก้ราคาสินค้า', function (): void {
    actingAsRole(RoleName::Warehouse);

    $product = Product::factory()->create(['sku' => 'AUDIT-SKU', 'list_price' => '1000.00']);

    $product->update(['list_price' => '1250.00']);

    $entry = Activity::query()->where('subject_type', Product::class)->where('event', 'updated')->latest('id')->first();

    expect($entry->properties->get('old')['list_price'])->toBe('1000.00')
        ->and($entry->properties->get('attributes')['list_price'])->toBe('1250.00');
});

it('บันทึกการ post ใบปรับปรุงสต็อก', function (): void {
    $user = actingAsRole(RoleName::Warehouse);

    $product = Product::factory()->create(['sku' => 'ADJ-AUDIT']);

    $adjustment = app(StockAdjustmentService::class)->createDraft([
        'warehouse_id' => $this->warehouse->id,
        'reason' => StockAdjustmentReason::Opening->value,
        'adjusted_at' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty_counted' => '15']],
    ]);

    app(StockAdjustmentService::class)->post($adjustment);

    $entry = Activity::query()
        ->where('subject_type', StockAdjustment::class)
        ->where('subject_id', $adjustment->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->properties->get('attributes')['status'])->toBe('posted')
        ->and($entry->causer_id)->toBe($user->id);
});

// ── หน้าอ่าน audit trail ───────────────────────────────

it('เปิดหน้าประวัติการใช้งานได้และเห็นค่าก่อน/หลัง', function (): void {
    actingAsRole(RoleName::Admin);

    $product = Product::factory()->create(['sku' => 'TRAIL-SKU', 'list_price' => '500.00']);
    $product->update(['list_price' => '600.00']);

    $this->get(route('activity.index'))
        ->assertOk()
        ->assertSee('list_price')
        ->assertSee('500.00')
        ->assertSee('600.00');
});

it('กรองประวัติตามผู้ทำรายการและประเภทข้อมูลได้', function (): void {
    $admin = actingAsRole(RoleName::Admin);

    Product::factory()->create(['sku' => 'FILTER-A']);
    Customer::factory()->create(['code' => 'CUS-8002', 'name_th' => 'ลูกค้ากรองทดสอบ']);

    $this->get(route('activity.index', ['subject_type' => Product::class]))
        ->assertOk()
        ->assertSee('Product')
        ->assertDontSee('ลูกค้ากรองทดสอบ');

    $this->get(route('activity.index', ['causer_id' => $admin->id]))->assertOk();
});

it('ช่วงเวลานอก whitelist ถูกปฏิเสธ ไม่ใช่ error 500', function (): void {
    actingAsRole(RoleName::Admin);

    $this->get(route('activity.index', ['days' => 9999]))->assertSessionHasErrors('days');
});

it('role ที่ไม่มีสิทธิ์ดูประวัติ เข้าหน้านี้ไม่ได้', function (): void {
    actingAsRole(RoleName::Sales);
    $this->get(route('activity.index'))->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->actingAs(userWithRole(RoleName::Warehouse));
    $this->get(route('activity.index'))->assertForbidden();
});

it('เมนูประวัติการใช้งานขึ้นเฉพาะคนที่มีสิทธิ์', function (): void {
    actingAsRole(RoleName::Admin);
    $this->get(route('dashboard'))->assertOk()->assertSee(route('activity.index'), escape: false);

    $this->app['auth']->forgetGuards();
    $this->actingAs(userWithRole(RoleName::Sales));
    $this->get(route('dashboard'))->assertOk()->assertDontSee(route('activity.index'), escape: false);
});

it('ไม่มีทางแก้หรือลบ activity log ผ่านหน้าเว็บ', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->uri(), 'activity'))
        ->flatMap(fn ($route): array => $route->methods())
        ->unique()
        ->values();

    expect($routes->all())->toBe(['GET', 'HEAD']);
});
