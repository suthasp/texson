<?php

declare(strict_types=1);

use App\Actions\ConvertQuotationToSalesOrder;
use App\Enums\QuotationItemType;
use App\Enums\RoleName;
use App\Enums\SalesOrderStatus;
use App\Enums\SerialStatus;
use App\Enums\StockDocumentStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\DeliveryService;
use App\Services\GoodsReceiptService;
use App\Services\QuotationService;
use App\Services\SalesOrderService;
use App\Services\StockService;

/**
 * DoD ของ Phase 4: ใบเสนอราคา → ใบสั่งขาย → ใบส่งของ → สต็อกลดถูกต้อง
 *
 * เทสต์ชุดนี้เดินทั้งเส้นเหมือนผู้ใช้จริง ไม่ยัดข้อมูลลงตารางเอง
 * และตรวจ invariant ของ Phase 2 ทุกครั้ง: ยอดคงเหลือ = ผลรวม ledger เสมอ
 */

/**
 * ยอดคงเหลือทุกคู่ (สินค้า, คลัง) ต้องเท่ากับผลรวม ledger — invariant ที่ห้ามพังตลอดสายงาน
 */
function assertLedgerStillMatches(): void
{
    foreach (StockLevel::all() as $level) {
        $ledger = StockMovement::query()
            ->where('product_id', $level->product_id)
            ->where('warehouse_id', $level->warehouse_id)
            ->sum('qty');

        expect(number_format((float) $ledger, 3, '.', ''))->toBe((string) $level->qty_on_hand);
    }
}

beforeEach(function (): void {
    $this->sales = actingAsRole(RoleName::Sales);

    $this->warehouse = Warehouse::factory()->create(['code' => 'HQ', 'is_default' => true]);
    $this->customer = Customer::factory()->create(['name_th' => 'บริษัท ดาต้าเซ็นเตอร์ไทย จำกัด']);

    $this->quotations = app(QuotationService::class);
    $this->salesOrders = app(SalesOrderService::class);
    $this->deliveries = app(DeliveryService::class);
    $this->convert = app(ConvertQuotationToSalesOrder::class);
    $this->stock = app(StockService::class);
});

/**
 * สร้างใบเสนอราคาที่ลูกค้าตอบรับแล้ว พร้อมแปลงเป็นใบสั่งขาย
 *
 * @param  array<int, array<string, mixed>>  $items
 */
function acceptedQuotation(array $items): App\Models\Quotation
{
    $service = app(QuotationService::class);

    $quotation = $service->createDraft([
        'customer_id' => test()->customer->id,
        'issue_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'price_tier' => 'standard',
        'vat_rate' => '7.00',
        'discount_amount' => '0',
        'items' => $items,
    ]);

    $service->send($quotation);
    $service->accept($quotation);

    return $quotation->refresh();
}

// ── เส้นทางหลัก ─────────────────────────────────────────

it('เดินครบเส้น ใบเสนอราคา → ใบสั่งขาย → ใบส่งของ แล้วสต็อกลดถูกต้อง', function (): void {
    $product = Product::factory()->create(['sku' => 'UPS-E2E-1', 'cost_price' => '15000', 'list_price' => '25000']);

    // ตั้งต้นมีของ 10 ชิ้น
    $this->stock->receive($product, $this->warehouse, '10');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '4',
        'unit_price' => '25000',
    ]]);

    // ── 1. แปลงเป็นใบสั่งขาย ──
    $order = $this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]);

    expect($order->so_no)->toMatch('/^SO-\d{6}-\d{4}$/')
        ->and($order->status)->toBe(SalesOrderStatus::Pending)
        ->and($order->quotation_id)->toBe($quotation->id)
        // ยอดเงินยกมาจากใบเสนอราคาทั้งชุด
        ->and((string) $order->grand_total)->toBe((string) $quotation->grand_total)
        ->and($order->items)->toHaveCount(1);

    // ยังไม่ยืนยัน จึงยังไม่แตะสต็อกเลย
    $level = $this->stock->levelFor($product, $this->warehouse)->refresh();
    expect((string) $level->qty_on_hand)->toBe('10.000')
        ->and((string) $level->qty_reserved)->toBe('0.000');

    // ── 2. ยืนยันใบ → จองของ (ยังไม่ลดยอดในมือ) ──
    $order = $this->salesOrders->confirm($order);

    $level->refresh();

    expect($order->status)->toBe(SalesOrderStatus::Reserved)
        ->and((string) $level->qty_on_hand)->toBe('10.000')
        ->and((string) $level->qty_reserved)->toBe('4.000')
        ->and($level->qty_available)->toBe('6.000')
        ->and((string) $order->items->first()->qty_reserved)->toBe('4.000')
        ->and($order->hasShortage())->toBeFalse();

    assertLedgerStillMatches();

    // ── 3. ออกใบส่งของแล้ว post ──
    $delivery = $this->deliveries->createDraft($order, [
        'warehouse_id' => $this->warehouse->id,
        'delivery_date' => now()->toDateString(),
        'receiver_name' => 'คุณสมชาย ผู้รับของ',
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '4']],
    ]);

    expect($delivery->delivery_no)->toMatch('/^DN-\d{6}-\d{4}$/')
        ->and($delivery->status)->toBe(StockDocumentStatus::Draft);

    // ร่างยังไม่กระทบสต็อก
    expect((string) $level->refresh()->qty_on_hand)->toBe('10.000');

    $this->deliveries->post($delivery);

    // ── 4. ตรวจผลลัพธ์ ──
    $level->refresh();
    $order->refresh()->load('items');

    expect((string) $level->qty_on_hand)->toBe('6.000')
        ->and((string) $level->qty_reserved)->toBe('0.000')
        ->and($level->qty_available)->toBe('6.000')
        ->and($order->status)->toBe(SalesOrderStatus::Delivered)
        ->and((string) $order->items->first()->qty_delivered)->toBe('4.000')
        ->and($order->closed_at)->not->toBeNull();

    // ledger มีรายการ issue ที่ชี้กลับไปยังใบส่งของ
    $movement = StockMovement::query()->where('type', 'issue')->firstOrFail();

    expect((string) $movement->qty)->toBe('-4.000')
        ->and((string) $movement->balance_after)->toBe('6.000')
        ->and($movement->ref_id)->toBe($delivery->id)
        ->and($movement->ref_type)->toBe($delivery->getMorphClass());

    assertLedgerStillMatches();
});

it('ส่งของหลายครั้งได้ สถานะไล่จาก partially_delivered ไป delivered', function (): void {
    $product = Product::factory()->create(['cost_price' => '100']);
    $this->stock->receive($product, $this->warehouse, '10');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '6',
        'unit_price' => '500',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));
    $line = $order->items->first();

    // ── ส่งครั้งแรก 2 ชิ้น ──
    $first = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['sales_order_item_id' => $line->id, 'qty' => '2']],
    ]);
    $this->deliveries->post($first);

    $order->refresh()->load('items');
    $level = $this->stock->levelFor($product, $this->warehouse)->refresh();

    expect($order->status)->toBe(SalesOrderStatus::PartiallyDelivered)
        ->and((string) $order->items->first()->qty_delivered)->toBe('2.000')
        ->and((string) $level->qty_on_hand)->toBe('8.000')
        // ปลดจองเฉพาะส่วนที่ส่งไปแล้ว ที่เหลือยังกันไว้
        ->and((string) $level->qty_reserved)->toBe('4.000');

    // ── ส่งที่เหลืออีก 4 ชิ้น ──
    $second = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['sales_order_item_id' => $line->id, 'qty' => '4']],
    ]);
    $this->deliveries->post($second);

    $order->refresh()->load('items');
    $level->refresh();

    expect($order->status)->toBe(SalesOrderStatus::Delivered)
        ->and((string) $order->items->first()->qty_delivered)->toBe('6.000')
        ->and((string) $level->qty_on_hand)->toBe('4.000')
        ->and((string) $level->qty_reserved)->toBe('0.000');

    assertLedgerStillMatches();
});

// ── Backorder (spec 4.4) ────────────────────────────────

it('ของไม่พอก็ยืนยันใบได้ จองเท่าที่มีแล้วบันทึกส่วนที่ขาดเป็น backorder', function (): void {
    $product = Product::factory()->create(['sku' => 'SHORT-SKU', 'cost_price' => '100']);

    // มีแค่ 3 แต่ลูกค้าสั่ง 10
    $this->stock->receive($product, $this->warehouse, '3');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '10',
        'unit_price' => '500',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));

    $level = $this->stock->levelFor($product, $this->warehouse)->refresh();
    $line = $order->items->first();

    expect($order->status)->toBe(SalesOrderStatus::Reserved)
        // จองได้แค่ 3 ตามของที่มีจริง ไม่จองเกินจนยอดพร้อมขายติดลบ
        ->and((string) $line->qty_reserved)->toBe('3.000')
        ->and((string) $line->qty_ordered)->toBe('10.000')
        ->and($line->shortageQty())->toBe('7.000')
        ->and($order->hasShortage())->toBeTrue()
        ->and($order->shortageQty())->toBe('7.000')
        ->and((string) $level->qty_on_hand)->toBe('3.000')
        ->and((string) $level->qty_reserved)->toBe('3.000')
        ->and($level->qty_available)->toBe('0.000');

    assertLedgerStillMatches();
});

it('ของเข้ามาเพิ่มแล้วส่งส่วนที่ค้างได้จนครบ', function (): void {
    $product = Product::factory()->create(['cost_price' => '100']);
    $this->stock->receive($product, $this->warehouse, '3');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '5',
        'unit_price' => '500',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));
    $line = $order->items->first();

    // ส่งเท่าที่มีก่อน
    $first = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['sales_order_item_id' => $line->id, 'qty' => '3']],
    ]);
    $this->deliveries->post($first);

    expect($order->refresh()->status)->toBe(SalesOrderStatus::PartiallyDelivered);

    // ของเข้ามาเพิ่ม แล้วส่งส่วนที่ค้าง
    $this->stock->receive($product, $this->warehouse, '5');

    $second = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['sales_order_item_id' => $line->id, 'qty' => '2']],
    ]);
    $this->deliveries->post($second);

    $order->refresh()->load('items');

    expect($order->status)->toBe(SalesOrderStatus::Delivered)
        ->and((string) $order->items->first()->qty_delivered)->toBe('5.000')
        ->and((string) $this->stock->levelFor($product, $this->warehouse)->refresh()->qty_on_hand)->toBe('3.000');

    assertLedgerStillMatches();
});

// ── ยกเลิกใบสั่งขาย (spec 4.4) ──────────────────────────

it('ยกเลิกใบสั่งขายแล้วคืนของที่จองไว้ทั้งหมด', function (): void {
    $product = Product::factory()->create(['cost_price' => '100']);
    $this->stock->receive($product, $this->warehouse, '10');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '4',
        'unit_price' => '500',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));

    expect((string) $this->stock->levelFor($product, $this->warehouse)->refresh()->qty_reserved)->toBe('4.000');

    $this->salesOrders->cancel($order, 'ลูกค้าเลื่อนโครงการ');

    $level = $this->stock->levelFor($product, $this->warehouse)->refresh();

    expect($order->refresh()->status)->toBe(SalesOrderStatus::Cancelled)
        ->and($order->cancel_reason)->toBe('ลูกค้าเลื่อนโครงการ')
        ->and((string) $level->qty_reserved)->toBe('0.000')
        // ยกเลิกการจองไม่ใช่การเคลื่อนไหวสต็อก ยอดในมือจึงไม่เปลี่ยนและไม่มีรายการใน ledger
        ->and((string) $level->qty_on_hand)->toBe('10.000')
        ->and(StockMovement::where('type', 'issue')->count())->toBe(0);

    assertLedgerStillMatches();
});

it('ยกเลิกใบที่ส่งของไปบางส่วนแล้ว คืนเฉพาะที่ยังจองค้างอยู่', function (): void {
    $product = Product::factory()->create(['cost_price' => '100']);
    $this->stock->receive($product, $this->warehouse, '10');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '6',
        'unit_price' => '500',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));

    $delivery = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '2']],
    ]);
    $this->deliveries->post($delivery);

    $this->salesOrders->cancel($order->refresh(), 'ลูกค้ายกเลิกส่วนที่เหลือ');

    $level = $this->stock->levelFor($product, $this->warehouse)->refresh();

    expect($order->refresh()->status)->toBe(SalesOrderStatus::Cancelled)
        // ส่งไป 2 · จองค้าง 4 ถูกคืน · ยอดในมือเหลือ 8
        ->and((string) $level->qty_on_hand)->toBe('8.000')
        ->and((string) $level->qty_reserved)->toBe('0.000');

    assertLedgerStillMatches();
});

// ── Serial (spec 4.4) ───────────────────────────────────

it('สินค้าที่ติดตาม serial ต้องเลือก serial ให้ครบตอนส่ง แล้วกลายเป็นขายแล้วพร้อมประกัน', function (): void {
    $product = Product::factory()->serialized()->create([
        'sku' => 'UPS-SN-1',
        'cost_price' => '15000',
        'warranty_months' => 24,
    ]);

    // รับเข้าพร้อม serial 3 ตัว
    $receipt = app(GoodsReceiptService::class)->createDraft([
        'warehouse_id' => $this->warehouse->id,
        'received_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $product->id,
            'qty' => '3',
            'unit_cost' => '15000',
            'serial_numbers' => "SN-A1\nSN-A2\nSN-A3",
        ]],
    ]);
    app(GoodsReceiptService::class)->post($receipt);

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '2',
        'unit_price' => '25000',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));

    $deliveryDate = now()->toDateString();

    $delivery = $this->deliveries->createDraft($order, [
        'delivery_date' => $deliveryDate,
        'warehouse_id' => $this->warehouse->id,
        'items' => [[
            'sales_order_item_id' => $order->items->first()->id,
            'qty' => '2',
            'serial_numbers' => "SN-A1\nSN-A2",
        ]],
    ]);

    $this->deliveries->post($delivery);

    $sold = SerialNumber::query()->whereIn('serial_no', ['SN-A1', 'SN-A2'])->get();
    $remaining = SerialNumber::query()->where('serial_no', 'SN-A3')->firstOrFail();

    expect($sold)->toHaveCount(2);

    foreach ($sold as $serial) {
        expect($serial->status)->toBe(SerialStatus::Sold)
            ->and($serial->sales_order_id)->toBe($order->id)
            ->and($serial->customer_id)->toBe($this->customer->id)
            // ของออกจากคลังไปอยู่กับลูกค้าแล้ว
            ->and($serial->warehouse_id)->toBeNull()
            ->and($serial->warranty_start->toDateString())->toBe($deliveryDate)
            ->and($serial->warranty_end->toDateString())->toBe(now()->addMonths(24)->toDateString());
    }

    // ตัวที่ยังไม่ขายไม่ถูกแตะ
    expect($remaining->status)->toBe(SerialStatus::InStock)
        ->and($remaining->warehouse_id)->toBe($this->warehouse->id);

    // จำนวน serial ที่ยังอยู่ในคลังต้องตรงกับยอดคงเหลือ
    expect((string) $this->stock->levelFor($product, $this->warehouse)->refresh()->qty_on_hand)->toBe('1.000')
        ->and(app(App\Services\SerialNumberService::class)->countOnHand($product, $this->warehouse))->toBe(1);

    assertLedgerStillMatches();
});

it('serial ไม่ครบจำนวน post ไม่ผ่านและไม่แตะสต็อกเลย', function (): void {
    $product = Product::factory()->serialized()->create(['cost_price' => '15000', 'warranty_months' => 12]);

    $receipt = app(GoodsReceiptService::class)->createDraft([
        'warehouse_id' => $this->warehouse->id,
        'received_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty' => '3', 'unit_cost' => '1', 'serial_numbers' => "SN-B1\nSN-B2\nSN-B3"]],
    ]);
    app(GoodsReceiptService::class)->post($receipt);

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '2',
        'unit_price' => '25000',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));

    $delivery = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        // ส่ง 2 แต่ระบุ serial มาแค่ 1
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '2', 'serial_numbers' => 'SN-B1']],
    ]);

    expect(fn () => $this->deliveries->post($delivery))
        ->toThrow(App\Exceptions\Domain\SerialNumberMismatchException::class);

    expect($delivery->refresh()->status)->toBe(StockDocumentStatus::Draft)
        ->and((string) $this->stock->levelFor($product, $this->warehouse)->refresh()->qty_on_hand)->toBe('3.000')
        ->and(SerialNumber::where('status', SerialStatus::Sold)->count())->toBe(0)
        ->and(StockMovement::where('type', 'issue')->count())->toBe(0);

    assertLedgerStillMatches();
});

it('เลือก serial ที่ไม่ได้อยู่ในคลังที่จ่ายของ post ไม่ผ่าน', function (): void {
    $product = Product::factory()->serialized()->create(['cost_price' => '1', 'warranty_months' => 12]);
    $other = Warehouse::factory()->create(['code' => 'VAN']);

    $receipt = app(GoodsReceiptService::class)->createDraft([
        'warehouse_id' => $other->id,
        'received_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty' => '1', 'unit_cost' => '1', 'serial_numbers' => 'SN-VAN-1']],
    ]);
    app(GoodsReceiptService::class)->post($receipt);

    // ของในคลัง HQ ที่จะจ่ายจริง
    $this->stock->receive($product, $this->warehouse, '1');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '1',
        'unit_price' => '100',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));

    $delivery = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '1', 'serial_numbers' => 'SN-VAN-1']],
    ]);

    expect(fn () => $this->deliveries->post($delivery))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    expect($delivery->refresh()->status)->toBe(StockDocumentStatus::Draft);
});

// ── บรรทัดที่ไม่มีของ ───────────────────────────────────

it('บรรทัดค่าแรงส่งมอบได้โดยไม่แตะสต็อก และนับเข้าความคืบหน้าของใบ', function (): void {
    $product = Product::factory()->create(['cost_price' => '100']);
    $this->stock->receive($product, $this->warehouse, '5');

    $quotation = acceptedQuotation([
        [
            'item_type' => QuotationItemType::Product->value,
            'product_id' => $product->id,
            'qty' => '2',
            'unit_price' => '500',
        ],
        [
            'item_type' => QuotationItemType::Labour->value,
            'description' => 'ค่าแรงติดตั้งและทดสอบระบบ',
            'qty' => '1',
            'unit_price' => '12000',
        ],
    ]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));

    $goods = $order->items->firstWhere('item_type', QuotationItemType::Product);
    $labour = $order->items->firstWhere('item_type', QuotationItemType::Labour);

    // บรรทัดค่าแรงไม่มีของให้จอง
    expect((string) $labour->qty_reserved)->toBe('0.000')
        ->and($labour->isStockable())->toBeFalse();

    $delivery = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            ['sales_order_item_id' => $goods->id, 'qty' => '2'],
            ['sales_order_item_id' => $labour->id, 'qty' => '1'],
        ],
    ]);

    $this->deliveries->post($delivery);

    $order->refresh()->load('items');

    expect($order->status)->toBe(SalesOrderStatus::Delivered)
        ->and((string) $order->items->firstWhere('item_type', QuotationItemType::Labour)->qty_delivered)->toBe('1.000')
        // ตัดสต็อกเฉพาะบรรทัดที่มีของ
        ->and(StockMovement::where('type', 'issue')->count())->toBe(1)
        ->and((string) $this->stock->levelFor($product, $this->warehouse)->refresh()->qty_on_hand)->toBe('3.000');

    assertLedgerStillMatches();
});

// ── เคสที่ต้องล้มเหลว ───────────────────────────────────

it('ส่งเกินยอดที่สั่งไม่ได้', function (): void {
    $product = Product::factory()->create(['cost_price' => '100']);
    $this->stock->receive($product, $this->warehouse, '100');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '2',
        'unit_price' => '500',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));

    $delivery = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '5']],
    ]);

    expect(fn () => $this->deliveries->post($delivery))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    expect($order->refresh()->status)->toBe(SalesOrderStatus::Reserved)
        ->and((string) $this->stock->levelFor($product, $this->warehouse)->refresh()->qty_on_hand)->toBe('100.000');
});

it('ของในคลังไม่พอตอน post ได้ InsufficientStockException และไม่ตัดสต็อกบางส่วน', function (): void {
    $product = Product::factory()->create(['cost_price' => '100']);
    $other = Warehouse::factory()->create(['code' => 'VAN']);

    $this->stock->receive($product, $this->warehouse, '5');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '5',
        'unit_price' => '500',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));

    // จ่ายจากคลัง VAN ที่ไม่มีของเลย
    $delivery = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $other->id,
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '5']],
    ]);

    expect(fn () => $this->deliveries->post($delivery))
        ->toThrow(App\Exceptions\Domain\InsufficientStockException::class);

    expect($delivery->refresh()->status)->toBe(StockDocumentStatus::Draft)
        ->and((string) $this->stock->levelFor($product, $this->warehouse)->refresh()->qty_on_hand)->toBe('5.000')
        ->and((string) $this->stock->levelFor($product, $other)->refresh()->qty_on_hand)->toBe('0.000');

    assertLedgerStillMatches();
});

it('ออกใบส่งของจากใบที่ยังไม่ยืนยันไม่ได้', function (): void {
    $product = Product::factory()->create(['cost_price' => '100']);
    $this->stock->receive($product, $this->warehouse, '10');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '2',
        'unit_price' => '500',
    ]]);

    $order = $this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]);

    expect(fn () => $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '2']],
    ]))->toThrow(App\Exceptions\Domain\InvalidStatusTransitionException::class);
});

it('post ใบส่งของซ้ำไม่ได้ และสต็อกไม่ถูกตัดสองรอบ', function (): void {
    $product = Product::factory()->create(['cost_price' => '100']);
    $this->stock->receive($product, $this->warehouse, '10');

    $quotation = acceptedQuotation([[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '3',
        'unit_price' => '500',
    ]]);

    $order = $this->salesOrders->confirm($this->convert->handle($quotation, ['warehouse_id' => $this->warehouse->id]));

    $delivery = $this->deliveries->createDraft($order, [
        'delivery_date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'items' => [['sales_order_item_id' => $order->items->first()->id, 'qty' => '3']],
    ]);

    $this->deliveries->post($delivery);

    expect(fn () => $this->deliveries->post($delivery->refresh()))
        ->toThrow(App\Exceptions\Domain\InvalidStatusTransitionException::class);

    expect((string) $this->stock->levelFor($product, $this->warehouse)->refresh()->qty_on_hand)->toBe('7.000')
        ->and(StockMovement::where('type', 'issue')->count())->toBe(1);

    assertLedgerStillMatches();
});
