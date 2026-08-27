<?php

declare(strict_types=1);

use App\Enums\QuotationItemType;
use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Enums\SalesOrderStatus;
use App\Enums\StockDocumentStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\QuotationService;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

/**
 * ใบสั่งขายและใบส่งของผ่าน REST API (spec 6)
 */
beforeEach(function (): void {
    $this->warehouse = Warehouse::factory()->create(['code' => 'HQ', 'is_default' => true]);
    $this->customer = Customer::factory()->create();
});

/**
 * ใบเสนอราคาที่ตอบรับแล้ว พร้อมของในคลัง
 */
function apiAcceptedQuote(App\Models\User $sales, Customer $customer, Warehouse $warehouse, string $qty = '4', string $stock = '50'): Quotation
{
    Auth::login($sales);

    $product = Product::factory()->create(['sku' => 'API-'.fake()->unique()->numerify('####'), 'cost_price' => '100']);
    app(StockService::class)->receive($product, $warehouse, $stock);

    $service = app(QuotationService::class);

    $quotation = $service->createDraft([
        'customer_id' => $customer->id,
        'sales_user_id' => $sales->id,
        'issue_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'price_tier' => 'standard',
        'vat_rate' => '7.00',
        'discount_amount' => '0',
        'items' => [[
            'item_type' => QuotationItemType::Product->value,
            'product_id' => $product->id,
            'qty' => $qty,
            'unit_price' => '500',
        ]],
    ]);

    $service->send($quotation);
    $service->accept($quotation);

    Auth::logout();

    return $quotation->refresh();
}

// ── แปลงใบเสนอราคา ──────────────────────────────────────

it('แปลงใบเสนอราคาเป็นใบสั่งขายผ่าน API ได้ 201', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);

    $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", [
        'warehouse_id' => $this->warehouse->id,
        'customer_po_no' => 'PO-API-001',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status.value', SalesOrderStatus::Pending->value)
        ->assertJsonPath('data.customer_po_no', 'PO-API-001')
        ->assertJsonPath('data.quotation.id', $quotation->id)
        ->assertJsonPath('data.totals.grand_total', (string) $quotation->grand_total);

    expect($response->json('data.so_no'))->toMatch('/^SO-\d{6}-\d{4}$/');
});

it('แปลงใบซ้ำตอบ 409 พร้อมบอกว่าใบสั่งขายเดิมคือใบไหน', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);

    $first = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->assertStatus(201);

    $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'quotation_no', 'sales_order_id', 'sales_order_no'])
        ->assertJsonPath('sales_order_no', $first->json('data.so_no'));

    expect(SalesOrder::count())->toBe(1);
});

it('แปลงใบที่ลูกค้ายังไม่ตอบรับตอบ 409 ไม่ใช่ 403', function (): void {
    $sales = userWithRole(RoleName::Sales);

    $quotation = Quotation::factory()->forSales($sales)->status(QuotationStatus::Sent)->create([
        'customer_id' => $this->customer->id,
    ]);

    Sanctum::actingAs($sales);

    // ผู้เรียกมีสิทธิ์และเป็นเจ้าของใบ — ที่ผิดคือสถานะ จึงเป็น 409 (ADR-014)
    $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'document', 'from', 'to']);
});

it('คลังสินค้าแปลงใบเสนอราคาผ่าน API ไม่ได้ (403)', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs(userWithRole(RoleName::Warehouse));

    $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->assertStatus(403);
});

// ── ยืนยันและ backorder ─────────────────────────────────

it('ยืนยันใบผ่าน API แล้วจองของ พร้อมรายงานว่าจองครบหรือไม่', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);

    $orderId = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->json('data.id');

    $this->postJson("/api/v1/sales-orders/{$orderId}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status.value', SalesOrderStatus::Reserved->value)
        ->assertJsonPath('meta.reserved_in_full', true)
        ->assertJsonPath('meta.shortage_qty', '0.000')
        ->assertJsonPath('data.items.0.qty_reserved', '4.000')
        ->assertJsonPath('data.fulfilment.has_shortage', false);
});

it('ของไม่พอ ยืนยันได้แต่ meta บอกว่าจองไม่ครบ (backorder ตาม spec 4.4)', function (): void {
    $sales = userWithRole(RoleName::Sales);
    // ของในคลังมี 3 แต่สั่ง 10
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse, qty: '10', stock: '3');

    Sanctum::actingAs($sales);

    $orderId = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->json('data.id');

    $this->postJson("/api/v1/sales-orders/{$orderId}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status.value', SalesOrderStatus::Reserved->value)
        ->assertJsonPath('meta.reserved_in_full', false)
        ->assertJsonPath('meta.shortage_qty', '7.000')
        ->assertJsonPath('data.items.0.qty_reserved', '3.000')
        ->assertJsonPath('data.items.0.qty_shortage', '7.000')
        ->assertJsonPath('data.fulfilment.has_shortage', true);
});

it('ยืนยันใบซ้ำตอบ 409', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);

    $orderId = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->json('data.id');

    $this->postJson("/api/v1/sales-orders/{$orderId}/confirm")->assertOk();
    $this->postJson("/api/v1/sales-orders/{$orderId}/confirm")->assertStatus(409);
});

it('ยกเลิกใบผ่าน API แล้วคืนของที่จองไว้', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);

    $orderId = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->json('data.id');
    $this->postJson("/api/v1/sales-orders/{$orderId}/confirm")->assertOk();

    $product = SalesOrder::findOrFail($orderId)->items->first()->product;

    expect((string) app(StockService::class)->levelFor($product, $this->warehouse)->refresh()->qty_reserved)->toBe('4.000');

    $this->postJson("/api/v1/sales-orders/{$orderId}/cancel", ['reason' => 'ลูกค้าเลื่อนโครงการ'])
        ->assertOk()
        ->assertJsonPath('data.status.value', SalesOrderStatus::Cancelled->value)
        ->assertJsonPath('data.cancel_reason', 'ลูกค้าเลื่อนโครงการ');

    expect((string) app(StockService::class)->levelFor($product, $this->warehouse)->refresh()->qty_reserved)->toBe('0.000');
});

// ── ใบส่งของ ────────────────────────────────────────────

it('เดินครบเส้นผ่าน API: convert → confirm → deliveries → post', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $warehouseUser = userWithRole(RoleName::Warehouse);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);

    $orderId = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->json('data.id');
    $this->postJson("/api/v1/sales-orders/{$orderId}/confirm")->assertOk();

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($warehouseUser);

    // ดูของที่ค้างส่งก่อน
    $outstanding = $this->getJson("/api/v1/sales-orders/{$orderId}/outstanding")->assertOk();

    expect($outstanding->json('meta.can_deliver'))->toBeTrue()
        ->and($outstanding->json('data.0.qty'))->toBe('4.000');

    $lineId = $outstanding->json('data.0.sales_order_item_id');

    $deliveryId = $this->postJson("/api/v1/sales-orders/{$orderId}/deliveries", [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        'receiver_name' => 'คุณสมชาย',
        'items' => [['sales_order_item_id' => $lineId, 'qty' => '4']],
    ])->assertStatus(201)
        ->assertJsonPath('data.status.value', StockDocumentStatus::Draft->value)
        ->json('data.id');

    $this->postJson("/api/v1/deliveries/{$deliveryId}/post")
        ->assertOk()
        ->assertJsonPath('data.status.value', StockDocumentStatus::Posted->value)
        ->assertJsonPath('meta.sales_order_status', SalesOrderStatus::Delivered->value)
        // ledger พิสูจน์ว่าตัดสต็อกจริง
        ->assertJsonPath('data.movements.0.qty', '-4.000');

    $product = SalesOrder::findOrFail($orderId)->items->first()->product;

    expect((string) app(StockService::class)->levelFor($product, $this->warehouse)->refresh()->qty_on_hand)->toBe('46.000')
        ->and(StockMovement::where('type', 'issue')->count())->toBe(1);
});

it('ออกใบส่งของจากใบที่ยังไม่ยืนยันตอบ 409', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $warehouseUser = userWithRole(RoleName::Warehouse);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);
    $orderId = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->json('data.id');

    $lineId = SalesOrder::findOrFail($orderId)->items->first()->id;

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($warehouseUser);

    $this->postJson("/api/v1/sales-orders/{$orderId}/deliveries", [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        'items' => [['sales_order_item_id' => $lineId, 'qty' => '1']],
    ])->assertStatus(409)->assertJsonStructure(['message', 'document', 'from', 'to']);
});

it('ของในคลังไม่พอตอน post ตอบ 422 พร้อมรายการที่ขาด (spec 6)', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $warehouseUser = userWithRole(RoleName::Warehouse);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);
    $empty = Warehouse::factory()->create(['code' => 'VAN']);

    Sanctum::actingAs($sales);
    $orderId = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->json('data.id');
    $this->postJson("/api/v1/sales-orders/{$orderId}/confirm")->assertOk();

    $lineId = SalesOrder::findOrFail($orderId)->items->first()->id;

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($warehouseUser);

    $deliveryId = $this->postJson("/api/v1/sales-orders/{$orderId}/deliveries", [
        'warehouse_id' => $empty->id,
        'delivery_date' => now()->toDateString(),
        'items' => [['sales_order_item_id' => $lineId, 'qty' => '4']],
    ])->assertStatus(201)->json('data.id');

    $this->postJson("/api/v1/deliveries/{$deliveryId}/post")
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'shortages' => [['product_id', 'sku', 'warehouse_code', 'requested', 'available']]])
        ->assertJsonPath('shortages.0.available', '0.000');
});

it('post ใบส่งของซ้ำตอบ 409', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $warehouseUser = userWithRole(RoleName::Warehouse);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);
    $orderId = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->json('data.id');
    $this->postJson("/api/v1/sales-orders/{$orderId}/confirm")->assertOk();

    $lineId = SalesOrder::findOrFail($orderId)->items->first()->id;

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($warehouseUser);

    $deliveryId = $this->postJson("/api/v1/sales-orders/{$orderId}/deliveries", [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        'items' => [['sales_order_item_id' => $lineId, 'qty' => '4']],
    ])->json('data.id');

    $this->postJson("/api/v1/deliveries/{$deliveryId}/post")->assertOk();
    $this->postJson("/api/v1/deliveries/{$deliveryId}/post")->assertStatus(409);

    expect(StockMovement::where('type', 'issue')->count())->toBe(1);
});

it('ฝ่ายขาย post ใบส่งของผ่าน API ไม่ได้ — เป็นงานคลัง', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $warehouseUser = userWithRole(RoleName::Warehouse);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);
    $orderId = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->json('data.id');
    $this->postJson("/api/v1/sales-orders/{$orderId}/confirm")->assertOk();

    $lineId = SalesOrder::findOrFail($orderId)->items->first()->id;

    // ฝ่ายขายสร้างใบส่งของเองไม่ได้
    $this->postJson("/api/v1/sales-orders/{$orderId}/deliveries", [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        'items' => [['sales_order_item_id' => $lineId, 'qty' => '1']],
    ])->assertStatus(403);

    // คลังสร้างให้แล้วฝ่ายขายก็ post ไม่ได้
    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($warehouseUser);

    $deliveryId = $this->postJson("/api/v1/sales-orders/{$orderId}/deliveries", [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        'items' => [['sales_order_item_id' => $lineId, 'qty' => '1']],
    ])->json('data.id');

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($sales);

    $this->postJson("/api/v1/deliveries/{$deliveryId}/post")->assertStatus(403);
});

// ── การมองเห็นและรูปแบบ response ────────────────────────

it('sales เห็นใบสั่งขายเฉพาะของตัวเอง แต่คลังเห็นทุกใบ', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    SalesOrder::factory()->forSales($mine)->inWarehouse($this->warehouse)->create(['so_no' => 'SO-202608-0001']);
    SalesOrder::factory()->forSales($theirs)->inWarehouse($this->warehouse)->create(['so_no' => 'SO-202608-0002']);

    Sanctum::actingAs($mine);
    expect($this->getJson('/api/v1/sales-orders')->assertOk()->json('meta.total'))->toBe(1);

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs(userWithRole(RoleName::Warehouse));
    expect($this->getJson('/api/v1/sales-orders')->assertOk()->json('meta.total'))->toBe(2);
});

it('meta.can บอกว่าทำอะไรกับใบสั่งขายได้บ้างตามสถานะ', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);

    $orderId = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->json('data.id');

    $pending = $this->getJson("/api/v1/sales-orders/{$orderId}")->assertOk();

    expect($pending->json('meta.can.update'))->toBeTrue()
        ->and($pending->json('meta.can.confirm'))->toBeTrue()
        // ฝ่ายขายออกใบส่งของเองไม่ได้
        ->and($pending->json('meta.can.deliver'))->toBeFalse();

    $this->postJson("/api/v1/sales-orders/{$orderId}/confirm")->assertOk();

    $reserved = $this->getJson("/api/v1/sales-orders/{$orderId}")->assertOk();

    expect($reserved->json('meta.can.update'))->toBeFalse()
        ->and($reserved->json('meta.can.confirm'))->toBeFalse()
        ->and($reserved->json('meta.can.cancel'))->toBeTrue();
});

it('ค่าจำนวนทุกตัวเป็น string ทศนิยม 3 ตำแหน่ง', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = apiAcceptedQuote($sales, $this->customer, $this->warehouse);

    Sanctum::actingAs($sales);

    $order = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-so", ['warehouse_id' => $this->warehouse->id])
        ->assertStatus(201)
        ->json('data.items.0');

    foreach (['qty_ordered', 'qty_reserved', 'qty_delivered', 'qty_outstanding', 'qty_shortage'] as $field) {
        expect($order[$field])->toBeString()->toMatch('/^\d+\.\d{3}$/');
    }
});
