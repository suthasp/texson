<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ประเภทเอกสารที่ต้องมีเลขที่รันนิ่ง (spec 4.1)
 *
 * รูปแบบ: {PREFIX}-YYYYMM-#### · running รีเซ็ตทุกเดือน
 * ออกเลขผ่าน NumberSequenceService เท่านั้น
 *
 * Phase 2 จะเพิ่ม WO (work order) และ SR (service report) ได้โดยเติม case
 * ไม่ต้องแก้ตาราง number_sequences เพราะ doc_type เก็บเป็น string
 */
enum DocType: string
{
    case Quotation = 'QT';
    case SalesOrder = 'SO';
    case DeliveryNote = 'DN';
    case GoodsReceipt = 'GR';
    case StockTransfer = 'TR';
    case StockAdjustment = 'ADJ';

    public function label(): string
    {
        return match ($this) {
            self::Quotation => __('ใบเสนอราคา'),
            self::SalesOrder => __('ใบสั่งขาย'),
            self::DeliveryNote => __('ใบส่งของ'),
            self::GoodsReceipt => __('ใบรับสินค้า'),
            self::StockTransfer => __('ใบโอนคลัง'),
            self::StockAdjustment => __('ใบปรับปรุงสต็อก'),
        };
    }

    /**
     * ความยาวของเลขรันนิ่งท้ายเอกสาร
     */
    public function runningDigits(): int
    {
        return 4;
    }
}
