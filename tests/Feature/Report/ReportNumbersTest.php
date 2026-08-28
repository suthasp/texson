<?php

declare(strict_types=1);

use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Warehouse;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Support\Carbon;

/**
 * DoD ของ Phase 5: "ตัวเลขตรงกับ query ตรวจมือ"
 *
 * ทุกเทสต์ในไฟล์นี้วางข้อมูลด้วยตัวเลขกลม ๆ ที่บวกในหัวได้ แล้วเทียบกับผลลัพธ์ตรง ๆ
 * ไม่ใช้ค่าที่คำนวณจากโค้ดชุดเดียวกับที่กำลังทดสอบ ไม่งั้นเทสต์จะพิสูจน์แค่ว่าโค้ดตรงกับตัวเอง
 */
beforeEach(function (): void {
    $this->sales = actingAsRole(RoleName::Sales);
    $this->warehouse = Warehouse::factory()->create(['is_default' => true]);
    $this->customer = Customer::factory()->create(['name_th' => 'ลูกค้า ก']);
    $this->reports = app(ReportService::class);

    $this->from = Carbon::now()->startOfMonth();
    $this->to = Carbon::now()->endOfMonth();
});

/**
 * ใบสั่งขายที่ตั้งยอดเองแบบตรง ๆ — ไม่ผ่าน calculator เพื่อให้ตัวเลขที่คาดหวังชัดเจน
 */
function orderWorth(string $grandTotal, SalesOrderStatus $status, array $overrides = []): SalesOrder
{
    return SalesOrder::factory()
        ->forSales(test()->sales)
        ->inWarehouse(test()->warehouse)
        ->status($status)
        ->create(array_merge([
            'customer_id' => test()->customer->id,
            'grand_total' => $grandTotal,
            'after_discount' => $grandTotal,
            'cost_total' => '0.00',
            'order_date' => Carbon::now()->toDateString(),
        ], $overrides));
}

function quoteWorth(string $grandTotal, QuotationStatus $status, array $overrides = []): Quotation
{
    return Quotation::factory()
        ->forSales(test()->sales)
        ->status($status)
        ->create(array_merge([
            'customer_id' => test()->customer->id,
            'grand_total' => $grandTotal,
            'after_discount' => $grandTotal,
            'cost_total' => '0.00',
            'issue_date' => Carbon::now()->toDateString(),
        ], $overrides));
}

// ── ยอดขาย ──────────────────────────────────────────────

it('ยอดขายรวมเฉพาะใบที่ไม่ถูกยกเลิก', function (): void {
    orderWorth('100000.00', SalesOrderStatus::Reserved);
    orderWorth('250000.50', SalesOrderStatus::PartiallyDelivered);
    orderWorth('49999.50', SalesOrderStatus::Delivered);
    // ใบที่ยกเลิกต้องไม่ถูกนับรวมในยอดขาย
    orderWorth('999999.00', SalesOrderStatus::Cancelled);

    $summary = $this->reports->salesSummary($this->from, $this->to, $this->sales);

    // 100,000.00 + 250,000.50 + 49,999.50 = 400,000.00
    expect($summary['ordered'])->toBe('400000.00')
        ->and($summary['order_count'])->toBe(3)
        ->and($summary['delivered'])->toBe('49999.50')
        ->and($summary['delivered_count'])->toBe(1)
        ->and($summary['cancelled'])->toBe('999999.00')
        ->and($summary['cancelled_count'])->toBe(1);
});

it('กำไรขั้นต้นคิดจากยอดก่อน VAT ลบต้นทุน', function (): void {
    orderWorth('107000.00', SalesOrderStatus::Delivered, [
        'after_discount' => '100000.00',
        'cost_total' => '70000.00',
    ]);
    orderWorth('53500.00', SalesOrderStatus::Reserved, [
        'after_discount' => '50000.00',
        'cost_total' => '20000.00',
    ]);

    // (100,000 − 70,000) + (50,000 − 20,000) = 60,000
    expect($this->reports->salesSummary($this->from, $this->to, $this->sales)['margin'])->toBe('60000.00');
});

it('ใบที่อยู่นอกช่วงวันที่ไม่ถูกนับ', function (): void {
    orderWorth('100000.00', SalesOrderStatus::Reserved);
    orderWorth('500000.00', SalesOrderStatus::Reserved, [
        'order_date' => Carbon::now()->subMonths(2)->toDateString(),
    ]);

    expect($this->reports->salesSummary($this->from, $this->to, $this->sales)['ordered'])->toBe('100000.00');

    // ขยายช่วงแล้วต้องเห็นทั้งสองใบ
    $wide = $this->reports->salesSummary(Carbon::now()->subMonths(6), $this->to, $this->sales);

    expect($wide['ordered'])->toBe('600000.00');
});

it('ไม่มีใบเลยได้ศูนย์ ไม่ใช่ error', function (): void {
    $summary = $this->reports->salesSummary($this->from, $this->to, $this->sales);

    expect($summary['ordered'])->toBe('0.00')
        ->and($summary['margin'])->toBe('0.00')
        ->and($summary['order_count'])->toBe(0);
});

// ── ใบเสนอราคาและ win rate ──────────────────────────────

it('win rate นับเฉพาะใบที่ลูกค้าตัดสินใจแล้ว', function (): void {
    quoteWorth('100000.00', QuotationStatus::Accepted);
    quoteWorth('100000.00', QuotationStatus::Accepted);
    quoteWorth('100000.00', QuotationStatus::Accepted);
    quoteWorth('100000.00', QuotationStatus::Rejected);
    // ใบที่ยังเปิดอยู่และใบหมดอายุไม่เข้าสมการ win rate
    quoteWorth('100000.00', QuotationStatus::Sent);
    quoteWorth('100000.00', QuotationStatus::Expired);

    $summary = $this->reports->quotationSummary($this->from, $this->to, $this->sales);

    // ตอบรับ 3 · ปฏิเสธ 1 → 3/4 = 75.00
    expect($summary['win_rate'])->toBe('75.00')
        ->and($summary['decided_count'])->toBe(4)
        ->and($summary['accepted_count'])->toBe(3)
        ->and($summary['expired_count'])->toBe(1)
        ->and($summary['total'])->toBe(6);
});

it('win rate เป็นศูนย์เมื่อยังไม่มีใบที่ตัดสินใจแล้ว ไม่ใช่หารด้วยศูนย์', function (): void {
    quoteWorth('100000.00', QuotationStatus::Sent);
    quoteWorth('100000.00', QuotationStatus::Draft);

    $summary = $this->reports->quotationSummary($this->from, $this->to, $this->sales);

    expect($summary['win_rate'])->toBe('0.00')
        ->and($summary['decided_count'])->toBe(0)
        ->and($summary['open_count'])->toBe(2);
});

it('ใบที่ถูกแทนที่ด้วย revision ไม่ถูกนับซ้ำใน pipeline', function (): void {
    quoteWorth('100000.00', QuotationStatus::Sent);
    // ใบเดิมที่ถูก revision แทนที่แล้ว — ยังเป็น sent แต่ต้องไม่นับเป็น pipeline (ADR-002)
    quoteWorth('999999.00', QuotationStatus::Sent, ['superseded_at' => Carbon::now()]);

    $summary = $this->reports->quotationSummary($this->from, $this->to, $this->sales);

    expect($summary['open_count'])->toBe(1)
        ->and($summary['open'])->toBe('100000.00')
        // แต่ยอดรวมทุกใบยังนับทั้งคู่ เพราะเป็นเอกสารที่ออกไปจริง
        ->and($summary['quoted'])->toBe('1099999.00');
});

// ── การมองเห็น (spec 8) ─────────────────────────────────

it('sales เห็นตัวเลขเฉพาะใบของตัวเอง', function (): void {
    $other = userWithRole(RoleName::Sales);

    orderWorth('100000.00', SalesOrderStatus::Reserved);

    SalesOrder::factory()
        ->forSales($other)
        ->inWarehouse($this->warehouse)
        ->status(SalesOrderStatus::Reserved)
        ->create(['customer_id' => $this->customer->id, 'grand_total' => '900000.00']);

    expect($this->reports->salesSummary($this->from, $this->to, $this->sales)['ordered'])->toBe('100000.00');

    // ผู้จัดการฝ่ายขายเห็นของทุกคน
    $manager = userWithRole(RoleName::SalesManager);

    expect($this->reports->salesSummary($this->from, $this->to, $manager)['ordered'])->toBe('1000000.00');
});

// ── ยอดรายเดือน ─────────────────────────────────────────

it('ยอดรายเดือนเติมเดือนที่ไม่มีออร์เดอร์ให้ครบและเรียงจากเก่าไปใหม่', function (): void {
    orderWorth('50000.00', SalesOrderStatus::Reserved);
    orderWorth('30000.00', SalesOrderStatus::Delivered, [
        'order_date' => Carbon::now()->subMonthNoOverflow()->startOfMonth()->addDay()->toDateString(),
    ]);

    $monthly = $this->reports->monthlySales($this->sales, 3);

    expect($monthly)->toHaveCount(3);

    $byMonth = $monthly->keyBy('month');

    expect($byMonth[Carbon::now()->format('Y-m')]['total'])->toBe('50000.00')
        ->and($byMonth[Carbon::now()->subMonthNoOverflow()->format('Y-m')]['total'])->toBe('30000.00')
        // เดือนที่ไม่มีออร์เดอร์ต้องมีแถวและเป็นศูนย์ ไม่ใช่หายไป
        ->and($byMonth[Carbon::now()->subMonthsNoOverflow(2)->format('Y-m')]['total'])->toBe('0.00')
        ->and($byMonth[Carbon::now()->subMonthsNoOverflow(2)->format('Y-m')]['count'])->toBe(0)
        // เรียงจากเก่าไปใหม่
        ->and($monthly->first()['month'])->toBe(Carbon::now()->subMonthsNoOverflow(2)->format('Y-m'))
        ->and($monthly->last()['month'])->toBe(Carbon::now()->format('Y-m'));
});

// ── อันดับ ──────────────────────────────────────────────

it('อันดับลูกค้าเรียงตามยอดและไม่นับใบที่ยกเลิก', function (): void {
    $big = Customer::factory()->create(['name_th' => 'ลูกค้ารายใหญ่']);
    $small = Customer::factory()->create(['name_th' => 'ลูกค้ารายเล็ก']);

    orderWorth('300000.00', SalesOrderStatus::Reserved, ['customer_id' => $big->id]);
    orderWorth('200000.00', SalesOrderStatus::Delivered, ['customer_id' => $big->id]);
    orderWorth('100000.00', SalesOrderStatus::Reserved, ['customer_id' => $small->id]);
    orderWorth('999999.00', SalesOrderStatus::Cancelled, ['customer_id' => $small->id]);

    $top = $this->reports->topCustomers($this->from, $this->to, $this->sales);

    expect($top)->toHaveCount(2)
        ->and($top[0]['customer']->id)->toBe($big->id)
        ->and($top[0]['total'])->toBe('500000.00')
        ->and($top[0]['count'])->toBe(2)
        ->and($top[1]['customer']->id)->toBe($small->id)
        // ใบที่ยกเลิกไม่ถูกนับ ยอดจึงเป็น 100,000 ไม่ใช่ 1,099,999
        ->and($top[1]['total'])->toBe('100000.00');
});

it('อันดับสินค้ารวมจำนวนและยอดข้ามหลายใบ', function (): void {
    $product = Product::factory()->create(['sku' => 'TOP-SKU']);

    $first = orderWorth('0.00', SalesOrderStatus::Reserved);
    $second = orderWorth('0.00', SalesOrderStatus::Delivered);

    foreach ([[$first, '3', '30000.00'], [$second, '2', '20000.00']] as [$order, $qty, $total]) {
        SalesOrderItem::factory()->create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'sku_snapshot' => 'TOP-SKU',
            'qty_ordered' => $qty,
            'line_total' => $total,
        ]);
    }

    $top = $this->reports->topProducts($this->from, $this->to, $this->sales);

    expect($top)->toHaveCount(1)
        ->and($top[0]['sku'])->toBe('TOP-SKU')
        ->and($top[0]['qty'])->toBe('5.000')
        ->and($top[0]['total'])->toBe('50000.00');
});

// ── มูลค่าสต็อก ─────────────────────────────────────────

it('มูลค่าสต็อกคิดจากยอดคงเหลือคูณราคาทุน', function (): void {
    $a = Product::factory()->create(['cost_price' => '1000.00', 'min_stock' => 0]);
    $b = Product::factory()->create(['cost_price' => '250.50', 'min_stock' => 0]);

    $stock = app(StockService::class);
    $stock->receive($a, $this->warehouse, '10');
    $stock->receive($b, $this->warehouse, '4');

    // 10 × 1,000.00 + 4 × 250.50 = 10,000 + 1,002 = 11,002.00
    $valuation = $this->reports->stockValuation();

    expect($valuation['value_at_cost'])->toBe('11002.00')
        ->and($valuation['sku_count'])->toBe(2)
        ->and($valuation['low_stock_count'])->toBe(0);
});

it('นับรายการที่เหลือน้อยจากยอดพร้อมขาย ไม่ใช่ยอดในมือ', function (): void {
    $product = Product::factory()->create(['cost_price' => '100.00', 'min_stock' => 10]);

    $stock = app(StockService::class);
    $stock->receive($product, $this->warehouse, '12');

    expect($this->reports->stockValuation()['low_stock_count'])->toBe(0);

    // ในมือ 12 (มากกว่า 10) แต่จองไป 8 เหลือพร้อมขาย 4 → เข้าเกณฑ์เหลือน้อย
    $stock->reserve($product, $this->warehouse, '8');

    expect($this->reports->stockValuation()['low_stock_count'])->toBe(1)
        // มูลค่ายังคิดจากยอดในมือ ของที่จองไว้ยังอยู่ในคลัง
        ->and($this->reports->stockValuation()['value_at_cost'])->toBe('1200.00');
});

// ── งานที่ต้องลงมือทำ ───────────────────────────────────

it('นับงานค้างแยกตามชนิดได้ถูกต้อง', function (): void {
    quoteWorth('100000.00', QuotationStatus::PendingApproval);
    quoteWorth('100000.00', QuotationStatus::PendingApproval, ['approved_at' => Carbon::now()]);
    quoteWorth('100000.00', QuotationStatus::Sent, ['valid_until' => Carbon::now()->addDays(3)->toDateString()]);
    quoteWorth('100000.00', QuotationStatus::Sent, ['valid_until' => Carbon::now()->addDays(60)->toDateString()]);

    orderWorth('100000.00', SalesOrderStatus::Pending);
    orderWorth('100000.00', SalesOrderStatus::Reserved);
    orderWorth('100000.00', SalesOrderStatus::PartiallyDelivered);
    orderWorth('100000.00', SalesOrderStatus::Delivered);

    $actions = $this->reports->actionItems($this->sales);

    expect($actions['quotations_pending_approval'])->toBe(1)
        ->and($actions['quotations_expiring'])->toBe(1)
        ->and($actions['orders_pending_confirm'])->toBe(1)
        ->and($actions['orders_to_ship'])->toBe(2);
});

// ── ตรงกับ query ตรวจมือ ────────────────────────────────

it('ยอดขายที่รายงานตรงกับผลรวมที่ query ตรงจากฐานข้อมูล', function (): void {
    foreach (['12345.67', '89012.33', '54321.00'] as $amount) {
        orderWorth($amount, SalesOrderStatus::Reserved);
    }
    orderWorth('11111.11', SalesOrderStatus::Cancelled);

    $reported = $this->reports->salesSummary($this->from, $this->to, $this->sales)['ordered'];

    // query ตรวจมือ: SUM(grand_total) ของใบที่ไม่ถูกยกเลิกในช่วงเดียวกัน
    $manual = SalesOrder::query()
        ->where('sales_user_id', $this->sales->id)
        ->where('status', '!=', SalesOrderStatus::Cancelled->value)
        ->whereBetween('order_date', [$this->from->toDateString(), $this->to->toDateString()])
        ->sum('grand_total');

    expect($reported)->toBe(number_format((float) $manual, 2, '.', ''))
        ->and($reported)->toBe('155679.00');
});
