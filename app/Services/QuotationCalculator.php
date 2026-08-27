<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QuotationItemType;
use App\Support\Money;

/**
 * คำนวณยอดเงินของใบเสนอราคาตามลำดับที่สเปกข้อ 4.2 กำหนดไว้ตายตัว
 *
 *   line_total     = qty × unit_price − line_discount
 *   subtotal       = Σ line_total
 *   after_discount = subtotal − header_discount
 *   vat_amount     = after_discount × vat_rate / 100   (ปัดครึ่งขึ้น 2 ตำแหน่ง)
 *   grand_total    = after_discount + vat_amount
 *
 * คลาสนี้เป็น pure function ล้วน ไม่แตะฐานข้อมูลและไม่มี state
 * จึงทดสอบค่าทุกเคสได้โดยไม่ต้องสร้างใบจริง
 */
class QuotationCalculator
{
    /** อัตราหัก ณ ที่จ่ายสำหรับค่าบริการ (spec 4.2 — แสดงเป็นข้อมูลประกอบ ไม่หักจาก grand_total) */
    public const WITHHOLDING_RATE = '3.00';

    /**
     * คำนวณบรรทัดเดียว — คืน array ที่พร้อมบันทึกลง quotation_items
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function line(array $input, int $lineNo): array
    {
        $type = $input['item_type'] instanceof QuotationItemType
            ? $input['item_type']
            : QuotationItemType::from((string) ($input['item_type'] ?? QuotationItemType::Product->value));

        // บรรทัดข้อความไม่มีมูลค่า — บังคับเป็นศูนย์ทุกช่อง กันยอดเพี้ยนจากค่าที่หลุดมาจากฟอร์ม
        if (! $type->isMonetary()) {
            return [
                'line_no' => $lineNo,
                'product_id' => null,
                'item_type' => $type,
                'sku_snapshot' => null,
                'description' => (string) ($input['description'] ?? ''),
                'uom' => null,
                'qty' => '0.000',
                'unit_price' => '0.00',
                'cost_snapshot' => '0.00',
                'discount_percent' => '0.00',
                'discount_amount' => '0.00',
                'line_total' => '0.00',
                'lead_time_days' => null,
            ];
        }

        $qty = Money::normalizeQty($input['qty'] ?? 0);
        $unitPrice = Money::normalize($input['unit_price'] ?? 0);
        $gross = Money::multiply($qty, $unitPrice);

        $percent = Money::normalize($input['discount_percent'] ?? 0);

        // ส่วนลดเป็นเปอร์เซ็นต์มีสิทธิ์เหนือกว่าจำนวนเงินเสมอ — ผู้ใช้กรอกช่องใดช่องหนึ่ง
        $discount = Money::isZero($percent)
            ? Money::normalize($input['discount_amount'] ?? 0)
            : Money::percentOf($gross, $percent);

        // ส่วนลดห้ามเกินมูลค่าบรรทัด ไม่งั้นยอดรวมจะติดลบ
        if (Money::greaterThan($discount, $gross)) {
            $discount = $gross;
        }

        return [
            'line_no' => $lineNo,
            'product_id' => $type->requiresProduct() ? ($input['product_id'] ?? null) : null,
            'item_type' => $type,
            'sku_snapshot' => $input['sku_snapshot'] ?? null,
            'description' => (string) ($input['description'] ?? ''),
            'uom' => $input['uom'] ?? null,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'cost_snapshot' => Money::normalize($input['cost_snapshot'] ?? 0),
            'discount_percent' => $percent,
            'discount_amount' => $discount,
            'line_total' => Money::clampToZero(Money::sub($gross, $discount)),
            'lead_time_days' => isset($input['lead_time_days']) && $input['lead_time_days'] !== ''
                ? (int) $input['lead_time_days']
                : null,
        ];
    }

    /**
     * คำนวณทุกบรรทัด เรียงเลขบรรทัดใหม่ตั้งแต่ 1
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function lines(array $items): array
    {
        $lines = [];
        $lineNo = 1;

        foreach ($items as $item) {
            $lines[] = $this->line($item, $lineNo);
            $lineNo++;
        }

        return $lines;
    }

    /**
     * สรุปยอดท้ายบิลจากบรรทัดที่คำนวณแล้ว
     *
     * @param  array<int, array<string, mixed>>  $lines  ผลลัพธ์จาก lines()
     * @return array<string, string>
     */
    public function summarize(array $lines, string $headerDiscount = '0', string $vatRate = '7.00'): array
    {
        $gross = '0.00';
        $lineDiscount = '0.00';
        $subtotal = '0.00';
        $costTotal = '0.00';
        $withholdingBase = '0.00';

        foreach ($lines as $line) {
            $subtotal = Money::add($subtotal, (string) $line['line_total']);
            $lineDiscount = Money::add($lineDiscount, (string) $line['discount_amount']);
            $gross = Money::add($gross, Money::multiply((string) $line['qty'], (string) $line['unit_price']));
            $costTotal = Money::add($costTotal, Money::multiply((string) $line['qty'], (string) $line['cost_snapshot']));

            $type = $line['item_type'] instanceof QuotationItemType
                ? $line['item_type']
                : QuotationItemType::from((string) $line['item_type']);

            if ($type->isWithholdingBase()) {
                $withholdingBase = Money::add($withholdingBase, (string) $line['line_total']);
            }
        }

        $headerDiscount = Money::normalize($headerDiscount);

        // ส่วนลดท้ายบิลห้ามเกิน subtotal ด้วยเหตุผลเดียวกับส่วนลดบรรทัด
        if (Money::greaterThan($headerDiscount, $subtotal)) {
            $headerDiscount = $subtotal;
        }

        $vatRate = Money::normalize($vatRate);
        $afterDiscount = Money::clampToZero(Money::sub($subtotal, $headerDiscount));
        $vatAmount = Money::percentOf($afterDiscount, $vatRate);

        return [
            'gross_total' => $gross,
            'line_discount_total' => $lineDiscount,
            'subtotal' => $subtotal,
            'discount_amount' => $headerDiscount,
            'after_discount' => $afterDiscount,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'grand_total' => Money::add($afterDiscount, $vatAmount),
            'cost_total' => $costTotal,

            // ── ตัวเลขประกอบ ไม่ได้เก็บลง quotations แต่ใช้ตัดสินใจเรื่องอนุมัติและแสดงบนหน้าจอ ──
            'total_discount_percent' => Money::percentage(Money::add($lineDiscount, $headerDiscount), $gross),
            'margin_amount' => Money::sub($afterDiscount, $costTotal),
            'margin_percent' => Money::percentage(Money::sub($afterDiscount, $costTotal), $afterDiscount),
            'withholding_base' => $withholdingBase,
            'withholding_amount' => Money::percentOf($withholdingBase, self::WITHHOLDING_RATE),
        ];
    }

    /**
     * คำนวณครบทั้งบรรทัดและยอดสรุปในครั้งเดียว
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, string>}
     */
    public function calculate(array $items, string $headerDiscount = '0', string $vatRate = '7.00'): array
    {
        $lines = $this->lines($items);

        return [
            'lines' => $lines,
            'totals' => $this->summarize($lines, $headerDiscount, $vatRate),
        ];
    }
}
