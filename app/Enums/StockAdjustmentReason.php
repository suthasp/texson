<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * เหตุผลการปรับปรุงสต็อก (spec 3.2)
 */
enum StockAdjustmentReason: string
{
    case StockCount = 'stock_count';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Found = 'found';
    case Opening = 'opening';

    public function label(): string
    {
        return match ($this) {
            self::StockCount => __('ตรวจนับสต็อก'),
            self::Damaged => __('ชำรุด / เสียหาย'),
            self::Lost => __('สูญหาย'),
            self::Found => __('พบเพิ่ม'),
            self::Opening => __('ยอดยกมา'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::StockCount => __('ปรับยอดให้ตรงกับที่นับได้จริงหน้างาน'),
            self::Damaged => __('ตัดของที่ใช้งานไม่ได้ออกจากสต็อก'),
            self::Lost => __('ตัดของที่หาไม่พบออกจากสต็อก'),
            self::Found => __('เพิ่มของที่พบเกินจากยอดในระบบ'),
            self::Opening => __('ตั้งยอดตั้งต้นตอนเริ่มใช้ระบบ'),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $reason): array => $carry + [$reason->value => $reason->label()],
            [],
        );
    }
}
