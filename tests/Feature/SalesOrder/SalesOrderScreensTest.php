<?php

declare(strict_types=1);

use App\Actions\ConvertQuotationToSalesOrder;
use App\Enums\QuotationItemType;
use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Enums\SalesOrderStatus;
use App\Enums\StockDocumentStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\DeliveryService;
use App\Services\QuotationService;
use App\Services\SalesOrderService;
use App\Services\StockService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * ใบสั่งขายและใบส่งของผ่าน HTTP — สิทธิ์ การมองเห็น และกฎสถานะ
 */
beforeEach(function (): void {
    $this->warehouse = Warehouse::factory()->create(['code' => 'HQ', 'is_default' => true]);
    $this->customer = Customer::factory()->create();
});

/**
 * ใบเสนอราคาที่ลูกค้าตอบรับแล้ว พร้อมของในคลัง
 *
 * ล็อกอินเป็น $sales ระหว่างสร้างเสมอ เพราะ QuotationService บันทึก created_by
 * จาก Auth::id() — ผู้เรียกบางเทสต์ยังไม่ได้ล็อกอินตอนเรียกฟังก์ชันนี้
 */
function acceptedQuoteFor(App\Models\User $sales, Customer $customer, Warehouse $warehouse, string $qty = '4'): Quotation
{
    $previous = Auth::user();
    Auth::login($sales);

    $product = Product::factory()->create(['cost_price' => '100']);
    app(StockService::class)->receive($product, $warehouse, '50');

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

    // คืนสภาพการล็อกอินเดิมให้เทสต์ที่เรียกมา
    $previous === null ? Auth::logout() : Auth::login($previous);

    return $quotation->refresh();
}

// ── แปลงใบเสนอราคา ──────────────────────────────────────

it('ฝ่ายขายแปลงใบเสนอราคาที่ตอบรับแล้วเป็นใบสั่งขายผ่านหน้าเว็บได้', function (): void {
    $sales = actingAsRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);

    $this->post(route('quotations.convert', $quotation), [
        'warehouse_id' => $this->warehouse->id,
        'customer_po_no' => 'PO-2026-0099',
    ])->assertRedirect();

    $order = SalesOrder::firstOrFail();

    expect($order->quotation_id)->toBe($quotation->id)
        ->and($order->status)->toBe(SalesOrderStatus::Pending)
        ->and($order->customer_po_no)->toBe('PO-2026-0099')
        ->and($order->sales_user_id)->toBe($sales->id)
        ->and((string) $order->grand_total)->toBe((string) $quotation->grand_total);
});

it('แปลงใบเดิมซ้ำไม่ได้ — ปุ่มหายและยิงตรงก็ไม่ผ่าน', function (): void {
    $sales = actingAsRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);

    $this->post(route('quotations.convert', $quotation), ['warehouse_id' => $this->warehouse->id])
        ->assertRedirect();

    // ปุ่มต้องหายไปจากหน้าจอ
    $this->get(route('quotations.show', $quotation))
        ->assertOk()
        ->assertDontSee(route('quotations.convert', $quotation), escape: false);

    // ยิงซ้ำก็ไม่ผ่าน (policy ปิดไว้แล้วเพราะแปลงไปแล้ว)
    $this->post(route('quotations.convert', $quotation), ['warehouse_id' => $this->warehouse->id])
        ->assertForbidden();

    expect(SalesOrder::count())->toBe(1);
});

it('แปลงใบที่ลูกค้ายังไม่ตอบรับไม่ได้', function (QuotationStatus $status): void {
    $sales = actingAsRole(RoleName::Sales);

    $quotation = Quotation::factory()->forSales($sales)->status($status)->create([
        'customer_id' => $this->customer->id,
    ]);

    $this->post(route('quotations.convert', $quotation), ['warehouse_id' => $this->warehouse->id])
        ->assertForbidden();

    expect(SalesOrder::count())->toBe(0);
})->with([QuotationStatus::Draft, QuotationStatus::Sent, QuotationStatus::Rejected]);

it('แนบไฟล์ใบสั่งซื้อของลูกค้าตอนแปลงได้ และเก็บใน storage private', function (): void {
    Storage::fake('private');

    $sales = actingAsRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);

    $this->post(route('quotations.convert', $quotation), [
        'warehouse_id' => $this->warehouse->id,
        'customer_po_file' => UploadedFile::fake()->create('ใบสั่งซื้อลูกค้า.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $order = SalesOrder::firstOrFail();

    expect($order->customer_po_file)->toStartWith('customer-po/')
        // ชื่อไฟล์เดิมของผู้ใช้ต้องไม่ถูกใช้เป็นชื่อบนดิสก์
        ->and($order->customer_po_file)->not->toContain('ใบสั่งซื้อ')
        ->and(Storage::disk('private')->exists($order->customer_po_file))->toBeTrue();

    // ไฟล์ต้องเสิร์ฟผ่าน controller ที่ตรวจสิทธิ์
    $this->get(route('sales-orders.po-file', $order))->assertOk();
});

it('คลังสินค้าแปลงใบเสนอราคาเองไม่ได้', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);

    actingAsRole(RoleName::Warehouse);

    $this->post(route('quotations.convert', $quotation), ['warehouse_id' => $this->warehouse->id])
        ->assertForbidden();
});

// ── ยืนยันและยกเลิก ─────────────────────────────────────

it('ยืนยันใบผ่านหน้าเว็บแล้วจองของ และหน้าจอบอกว่าจองครบ', function (): void {
    $sales = actingAsRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);
    $order = app(ConvertQuotationToSalesOrder::class)->handle($quotation, ['warehouse_id' => $this->warehouse->id]);

    $this->from(route('sales-orders.show', $order))
        ->post(route('sales-orders.confirm', $order))
        ->assertRedirect(route('sales-orders.show', $order))
        ->assertSessionHas('success');

    expect($order->refresh()->status)->toBe(SalesOrderStatus::Reserved);
});

it('ยืนยันใบที่ของไม่พอได้ข้อความเตือน ไม่ใช่ error', function (): void {
    $sales = actingAsRole(RoleName::Sales);

    $product = Product::factory()->create(['cost_price' => '100']);
    app(StockService::class)->receive($product, $this->warehouse, '1');

    $service = app(QuotationService::class);
    $quotation = $service->createDraft([
        'customer_id' => $this->customer->id,
        'issue_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'price_tier' => 'standard',
        'vat_rate' => '7.00',
        'discount_amount' => '0',
        'items' => [[
            'item_type' => QuotationItemType::Product->value,
            'product_id' => $product->id,
            'qty' => '10',
            'unit_price' => '500',
        ]],
    ]);
    $service->send($quotation);
    $service->accept($quotation);

    $order = app(ConvertQuotationToSalesOrder::class)->handle($quotation->refresh(), ['warehouse_id' => $this->warehouse->id]);

    $this->post(route('sales-orders.confirm', $order))
        ->assertRedirect()
        // เตือนแต่ทำสำเร็จ — backorder ตามสเปกข้อ 4.4
        ->assertSessionHas('warning');

    expect($order->refresh()->status)->toBe(SalesOrderStatus::Reserved)
        ->and($order->load('items')->hasShortage())->toBeTrue();
});

it('ยืนยันใบซ้ำไม่ได้', function (): void {
    $sales = actingAsRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);
    $order = app(ConvertQuotationToSalesOrder::class)->handle($quotation, ['warehouse_id' => $this->warehouse->id]);

    $this->post(route('sales-orders.confirm', $order))->assertRedirect();
    $this->post(route('sales-orders.confirm', $order->refresh()))->assertForbidden();
});

it('แก้หัวใบได้เฉพาะตอนยังไม่ยืนยัน', function (): void {
    $sales = actingAsRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);
    $order = app(ConvertQuotationToSalesOrder::class)->handle($quotation, ['warehouse_id' => $this->warehouse->id]);

    $this->get(route('sales-orders.edit', $order))->assertOk();

    app(SalesOrderService::class)->confirm($order);

    $this->get(route('sales-orders.edit', $order->refresh()))->assertForbidden();

    $this->get(route('sales-orders.show', $order))
        ->assertOk()
        ->assertDontSee(route('sales-orders.edit', $order), escape: false);
});

// ── การมองเห็น (spec 8) ─────────────────────────────────

it('sales เห็นเฉพาะใบสั่งขายของตัวเอง', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    SalesOrder::factory()->forSales($mine)->inWarehouse($this->warehouse)->create(['so_no' => 'SO-202608-0001']);
    SalesOrder::factory()->forSales($theirs)->inWarehouse($this->warehouse)->create(['so_no' => 'SO-202608-0002']);

    $this->actingAs($mine)
        ->get(route('sales-orders.index'))
        ->assertOk()
        ->assertSee('SO-202608-0001')
        ->assertDontSee('SO-202608-0002');
});

it('sales เปิดใบของ sales คนอื่นไม่ได้', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    $order = SalesOrder::factory()->forSales($theirs)->inWarehouse($this->warehouse)->create();

    $this->actingAs($mine)->get(route('sales-orders.show', $order))->assertForbidden();
});

it('คลังสินค้าเห็นใบสั่งขายทุกใบเพราะต้องจัดของส่ง', function (): void {
    $sales = userWithRole(RoleName::Sales);

    SalesOrder::factory()->forSales($sales)->inWarehouse($this->warehouse)->create(['so_no' => 'SO-202608-0100']);

    actingAsRole(RoleName::Warehouse);

    $this->get(route('sales-orders.index'))->assertOk()->assertSee('SO-202608-0100');
});

it('คลังสินค้ายืนยันหรือยกเลิกใบสั่งขายแทนฝ่ายขายไม่ได้', function (): void {
    $sales = userWithRole(RoleName::Sales);

    $order = SalesOrder::factory()
        ->forSales($sales)
        ->inWarehouse($this->warehouse)
        ->status(SalesOrderStatus::Pending)
        ->create();

    actingAsRole(RoleName::Warehouse);

    $this->post(route('sales-orders.confirm', $order))->assertForbidden();
    $this->post(route('sales-orders.cancel', $order))->assertForbidden();
});

// ── ใบส่งของ ────────────────────────────────────────────

it('คลังสินค้าออกใบส่งของและ post ตัดสต็อกได้ผ่านหน้าเว็บ', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);

    $this->actingAs($sales);
    $order = app(ConvertQuotationToSalesOrder::class)->handle($quotation, ['warehouse_id' => $this->warehouse->id]);
    app(SalesOrderService::class)->confirm($order);

    $this->app['auth']->forgetGuards();
    actingAsRole(RoleName::Warehouse);

    $this->get(route('sales-orders.deliveries.create', $order))->assertOk();

    $this->post(route('sales-orders.deliveries.store', $order), [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        'receiver_name' => 'คุณสมชาย',
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '4']],
    ])->assertRedirect();

    $delivery = Delivery::firstOrFail();

    expect($delivery->status)->toBe(StockDocumentStatus::Draft);

    $this->post(route('deliveries.post', $delivery))->assertRedirect();

    expect($delivery->refresh()->status)->toBe(StockDocumentStatus::Posted)
        ->and($order->refresh()->status)->toBe(SalesOrderStatus::Delivered);
});

it('ฝ่ายขายออกใบส่งของเองไม่ได้ — เป็นงานคลัง', function (): void {
    $sales = actingAsRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);
    $order = app(ConvertQuotationToSalesOrder::class)->handle($quotation, ['warehouse_id' => $this->warehouse->id]);
    app(SalesOrderService::class)->confirm($order);

    $this->get(route('sales-orders.deliveries.create', $order->refresh()))->assertForbidden();
    $this->post(route('sales-orders.deliveries.store', $order), [])->assertForbidden();
});

it('ฝ่ายขายดูใบส่งของของใบตัวเองได้', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);

    $this->actingAs($sales);
    $order = app(ConvertQuotationToSalesOrder::class)->handle($quotation, ['warehouse_id' => $this->warehouse->id]);
    app(SalesOrderService::class)->confirm($order);

    $delivery = app(DeliveryService::class)->createDraft($order->refresh(), [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '1']],
    ]);

    $this->get(route('deliveries.show', $delivery))->assertOk();

    // แต่ post ไม่ได้
    $this->post(route('deliveries.post', $delivery))->assertForbidden();
});

it('บรรทัดของใบสั่งขายใบอื่นถูกปฏิเสธด้วย 422', function (): void {
    $sales = userWithRole(RoleName::Sales);

    $quotationA = acceptedQuoteFor($sales, $this->customer, $this->warehouse);
    $quotationB = acceptedQuoteFor($sales, $this->customer, $this->warehouse);

    $this->actingAs($sales);
    $orderA = app(ConvertQuotationToSalesOrder::class)->handle($quotationA, ['warehouse_id' => $this->warehouse->id]);
    $orderB = app(ConvertQuotationToSalesOrder::class)->handle($quotationB, ['warehouse_id' => $this->warehouse->id]);
    app(SalesOrderService::class)->confirm($orderA);

    $this->app['auth']->forgetGuards();
    actingAsRole(RoleName::Warehouse);

    $this->post(route('sales-orders.deliveries.store', $orderA), [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        // บรรทัดของใบ B
        'items' => [['sales_order_item_id' => $orderB->items->first()->id, 'qty' => '1']],
    ])->assertSessionHasErrors('items.0.sales_order_item_id');

    expect(Delivery::count())->toBe(0);
});

it('post ใบที่ของไม่พอได้ข้อความบอกผู้ใช้ ไม่ใช่ error 500', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);
    $empty = Warehouse::factory()->create(['code' => 'VAN']);

    $this->actingAs($sales);
    $order = app(ConvertQuotationToSalesOrder::class)->handle($quotation, ['warehouse_id' => $this->warehouse->id]);
    app(SalesOrderService::class)->confirm($order);

    $this->app['auth']->forgetGuards();
    actingAsRole(RoleName::Warehouse);

    $delivery = app(DeliveryService::class)->createDraft($order->refresh(), [
        'warehouse_id' => $empty->id,
        'delivery_date' => now()->toDateString(),
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '4']],
    ]);

    $this->from(route('deliveries.show', $delivery))
        ->post(route('deliveries.post', $delivery))
        ->assertRedirect(route('deliveries.show', $delivery))
        ->assertSessionHas('error');

    expect($delivery->refresh()->status)->toBe(StockDocumentStatus::Draft);
});

it('ปุ่มแก้ไขและ post หายไปเมื่อใบส่งของถูก post แล้ว', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);

    $this->actingAs($sales);
    $order = app(ConvertQuotationToSalesOrder::class)->handle($quotation, ['warehouse_id' => $this->warehouse->id]);
    app(SalesOrderService::class)->confirm($order);

    $this->app['auth']->forgetGuards();
    actingAsRole(RoleName::Warehouse);

    $delivery = app(DeliveryService::class)->createDraft($order->refresh(), [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '4']],
    ]);

    $this->get(route('deliveries.show', $delivery))
        ->assertOk()
        ->assertSee(route('deliveries.post', $delivery), escape: false)
        ->assertSee(route('deliveries.edit', $delivery), escape: false);

    app(DeliveryService::class)->post($delivery);

    $this->get(route('deliveries.show', $delivery->refresh()))
        ->assertOk()
        ->assertDontSee(route('deliveries.post', $delivery), escape: false)
        ->assertDontSee(route('deliveries.edit', $delivery), escape: false);
});

it('viewer ดูได้แต่ทำอะไรไม่ได้เลย', function (): void {
    actingAsRole(RoleName::Viewer);

    $order = SalesOrder::factory()->inWarehouse($this->warehouse)->create();

    $this->get(route('sales-orders.index'))->assertOk();
    $this->get(route('deliveries.index'))->assertOk();
    $this->get(route('sales-orders.edit', $order))->assertForbidden();
    $this->post(route('sales-orders.confirm', $order))->assertForbidden();
    $this->get(route('sales-orders.deliveries.create', $order))->assertForbidden();
});

it('สคริปต์ในชื่อผู้รับของถูก escape ตอนแสดงผล', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $quotation = acceptedQuoteFor($sales, $this->customer, $this->warehouse);

    $this->actingAs($sales);
    $order = app(ConvertQuotationToSalesOrder::class)->handle($quotation, ['warehouse_id' => $this->warehouse->id]);
    app(SalesOrderService::class)->confirm($order);

    $this->app['auth']->forgetGuards();
    actingAsRole(RoleName::Warehouse);

    $delivery = app(DeliveryService::class)->createDraft($order->refresh(), [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        'receiver_name' => '<script>alert("xss")</script>',
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '1']],
    ]);

    $this->get(route('deliveries.show', $delivery))
        ->assertOk()
        ->assertDontSee('<script>alert("xss")</script>', escape: false)
        ->assertSee('&lt;script&gt;', escape: false);
});
