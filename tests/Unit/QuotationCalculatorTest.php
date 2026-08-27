<?php

declare(strict_types=1);

use App\Enums\QuotationItemType;
use App\Services\QuotationCalculator;
use App\Support\Money;

/**
 * ลำดับการคำนวณตามสเปกข้อ 4.2 — ห้ามสลับขั้น
 *
 *   line_total     = qty × unit_price − line_discount
 *   subtotal       = Σ line_total
 *   after_discount = subtotal − header_discount
 *   vat_amount     = after_discount × vat_rate / 100  (ปัดครึ่งขึ้น 2 ตำแหน่ง)
 *   grand_total    = after_discount + vat_amount
 */
beforeEach(function (): void {
    $this->calc = new QuotationCalculator;
});

/** @param array<int, array<string, mixed>> $items */
function totals(array $items, string $headerDiscount = '0', string $vatRate = '7.00'): array
{
    return app(QuotationCalculator::class)->calculate($items, $headerDiscount, $vatRate)['totals'];
}

function productLine(string $qty, string $price, array $extra = []): array
{
    return array_merge([
        'item_type' => QuotationItemType::Product->value,
        'description' => 'สินค้าทดสอบ',
        'qty' => $qty,
        'unit_price' => $price,
        'cost_snapshot' => '0',
    ], $extra);
}

it('คำนวณบรรทัดเดียวแบบไม่มีส่วนลดได้ถูกต้อง', function (): void {
    $result = totals([productLine('3', '1000')]);

    expect($result['subtotal'])->toBe('3000.00')
        ->and($result['after_discount'])->toBe('3000.00')
        ->and($result['vat_amount'])->toBe('210.00')
        ->and($result['grand_total'])->toBe('3210.00');
});

it('หักส่วนลดบรรทัดก่อนรวมเป็น subtotal', function (): void {
    // 10 × 500 = 5000 หักส่วนลด 10% = 500 → เหลือ 4500
    $result = totals([productLine('10', '500', ['discount_percent' => '10'])]);

    expect($result['subtotal'])->toBe('4500.00')
        ->and($result['line_discount_total'])->toBe('500.00')
        ->and($result['gross_total'])->toBe('5000.00');
});

it('ส่วนลดบรรทัดเป็นจำนวนเงินใช้ได้เมื่อไม่ได้ระบุเปอร์เซ็นต์', function (): void {
    $result = totals([productLine('2', '1000', ['discount_amount' => '350'])]);

    expect($result['subtotal'])->toBe('1650.00');
});

it('ส่วนลดเปอร์เซ็นต์มีสิทธิ์เหนือกว่าจำนวนเงินเมื่อกรอกมาทั้งคู่', function (): void {
    $result = totals([productLine('1', '1000', ['discount_percent' => '10', 'discount_amount' => '999'])]);

    // ใช้ 10% = 100 ไม่ใช่ 999
    expect($result['subtotal'])->toBe('900.00');
});

it('ส่วนลดบรรทัดเกินมูลค่าบรรทัดถูกจำกัดไม่ให้ยอดติดลบ', function (): void {
    $result = totals([productLine('1', '500', ['discount_amount' => '900'])]);

    expect($result['subtotal'])->toBe('0.00')
        ->and($result['grand_total'])->toBe('0.00');
});

it('หักส่วนลดท้ายบิลหลัง subtotal แล้วค่อยคิด VAT', function (): void {
    // 2 บรรทัด 10,000 + 5,000 = 15,000 หักท้ายบิล 1,000 = 14,000
    $result = totals([
        productLine('1', '10000'),
        productLine('1', '5000'),
    ], headerDiscount: '1000');

    expect($result['subtotal'])->toBe('15000.00')
        ->and($result['discount_amount'])->toBe('1000.00')
        ->and($result['after_discount'])->toBe('14000.00')
        ->and($result['vat_amount'])->toBe('980.00')
        ->and($result['grand_total'])->toBe('14980.00');
});

it('ส่วนลดท้ายบิลเกิน subtotal ถูกจำกัดไว้ที่ subtotal', function (): void {
    $result = totals([productLine('1', '1000')], headerDiscount: '5000');

    expect($result['discount_amount'])->toBe('1000.00')
        ->and($result['after_discount'])->toBe('0.00')
        ->and($result['grand_total'])->toBe('0.00');
});

it('คิด VAT แบบปัดครึ่งขึ้นสองตำแหน่ง', function (): void {
    // 100.07 × 7% = 7.0049 → 7.00
    expect(totals([productLine('1', '100.07')])['vat_amount'])->toBe('7.00');

    // 107.93 × 7% = 7.5551 → 7.56
    expect(totals([productLine('1', '107.93')])['vat_amount'])->toBe('7.56');

    // เคสกึ่งกลางพอดี: 50 × 7% = 3.50
    expect(totals([productLine('1', '50')])['vat_amount'])->toBe('3.50');
});

it('อัตรา VAT 0 ทำให้ยอดสุทธิเท่ากับยอดหลังส่วนลด', function (): void {
    $result = totals([productLine('1', '1234.56')], vatRate: '0');

    expect($result['vat_amount'])->toBe('0.00')
        ->and($result['grand_total'])->toBe('1234.56');
});

it('บรรทัดข้อความไม่มีมูลค่าและไม่กระทบยอดรวม', function (): void {
    $result = totals([
        productLine('1', '1000'),
        [
            'item_type' => QuotationItemType::Note->value,
            'description' => 'หมายเหตุ: ราคานี้ไม่รวมงานเดินสายไฟ',
            // ค่าที่หลุดมาจากฟอร์มต้องถูกบังคับเป็นศูนย์
            'qty' => '99',
            'unit_price' => '99999',
        ],
    ]);

    expect($result['subtotal'])->toBe('1000.00')
        ->and($result['grand_total'])->toBe('1070.00');
});

it('คิดฐานหัก ณ ที่จ่าย 3% เฉพาะค่าบริการและค่าแรง', function (): void {
    $result = totals([
        productLine('1', '100000'),
        ['item_type' => QuotationItemType::Service->value, 'description' => 'ค่าบริการตรวจสอบ', 'qty' => '1', 'unit_price' => '20000'],
        ['item_type' => QuotationItemType::Labour->value, 'description' => 'ค่าแรงติดตั้ง', 'qty' => '1', 'unit_price' => '10000'],
        ['item_type' => QuotationItemType::Freight->value, 'description' => 'ค่าขนส่ง', 'qty' => '1', 'unit_price' => '5000'],
    ]);

    // ฐาน = 20,000 + 10,000 (ไม่รวมสินค้าและค่าขนส่ง)
    expect($result['withholding_base'])->toBe('30000.00')
        ->and($result['withholding_amount'])->toBe('900.00')
        // แต่ยอดสุทธิไม่ถูกหัก
        ->and($result['grand_total'])->toBe('144450.00');
});

it('คำนวณ margin จากต้นทุนที่ snapshot ไว้', function (): void {
    $result = totals([productLine('10', '1000', ['cost_snapshot' => '700'])]);

    expect($result['cost_total'])->toBe('7000.00')
        ->and($result['margin_amount'])->toBe('3000.00')
        ->and($result['margin_percent'])->toBe('30.00');
});

it('คำนวณเปอร์เซ็นต์ส่วนลดรวมจากทั้งบรรทัดและท้ายบิล', function (): void {
    // ราคาเต็ม 10,000 · ส่วนลดบรรทัด 10% = 1,000 · ส่วนลดท้ายบิล 500 → รวม 1,500 = 15%
    $result = totals([productLine('10', '1000', ['discount_percent' => '10'])], headerDiscount: '500');

    expect($result['total_discount_percent'])->toBe('15.00');
});

it('ไม่หารด้วยศูนย์เมื่อใบไม่มีมูลค่าเลย', function (): void {
    $result = totals([['item_type' => QuotationItemType::Note->value, 'description' => 'เปล่า']]);

    expect($result['total_discount_percent'])->toBe('0.00')
        ->and($result['margin_percent'])->toBe('0.00')
        ->and($result['grand_total'])->toBe('0.00');
});

it('เศษสตางค์ไม่สะสมผิดเพราะคำนวณด้วย bcmath ไม่ใช่ float', function (): void {
    // 0.1 + 0.2 ด้วย float จะได้ 0.30000000000000004
    $lines = array_fill(0, 3, productLine('1', '0.10'));
    $result = totals($lines, vatRate: '0');

    expect($result['subtotal'])->toBe('0.30');

    // ยอดที่ทำให้ float พลาด: 1,234,567.89 × 3
    $big = totals(array_fill(0, 3, productLine('1', '1234567.89')), vatRate: '0');
    expect($big['subtotal'])->toBe('3703703.67');
});

it('เรียงเลขบรรทัดใหม่ตั้งแต่ 1 เสมอ', function (): void {
    $lines = app(QuotationCalculator::class)->lines([
        productLine('1', '100'),
        productLine('1', '200'),
        productLine('1', '300'),
    ]);

    expect(array_column($lines, 'line_no'))->toBe([1, 2, 3]);
});

it('Money::round ปัดครึ่งขึ้นทั้งค่าบวกและค่าลบ', function (): void {
    expect(Money::round('1.005'))->toBe('1.01')
        ->and(Money::round('1.004'))->toBe('1.00')
        ->and(Money::round('-1.005'))->toBe('-1.01')
        ->and(Money::round('-1.004'))->toBe('-1.00');
});
