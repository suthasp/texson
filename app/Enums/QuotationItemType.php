<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ชนิดของบรรทัดในใบเสนอราคา (spec 3.3)
 *
 * ไม่ใช่ทุกบรรทัดที่ผูกกับสินค้าในคลัง — ค่าแรงติดตั้งและค่าขนส่งพิมพ์อิสระได้
 */
enum QuotationItemType: string
{
    case Product = 'product';
    case Service = 'service';
    case Labour = 'labour';
    case Freight = 'freight';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Product => __('สินค้า'),
            self::Service => __('ค่าบริการ'),
            self::Labour => __('ค่าแรง'),
            self::Freight => __('ค่าขนส่ง'),
            self::Note => __('ข้อความ'),
        };
    }

    /**
     * บรรทัดที่ไม่มีมูลค่า — ใช้คั่นหัวข้อหรือใส่หมายเหตุกลางตาราง
     */
    public function isMonetary(): bool
    {
        return $this !== self::Note;
    }

    /**
     * เข้าฐานหัก ณ ที่จ่าย 3% หรือไม่ (spec 4.2)
     *
     * ค่าบริการและค่าแรงเข้าฐาน ส่วนค่าสินค้าและค่าขนส่งไม่เข้า
     */
    public function isWithholdingBase(): bool
    {
        return in_array($this, [self::Service, self::Labour], true);
    }

    /**
     * ต้องผูกกับสินค้าในระบบหรือไม่ — ใช้ตอนดึงราคาและสต็อกมาแสดง
     */
    public function requiresProduct(): bool
    {
        return $this === self::Product;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $type): array => $carry + [$type->value => $type->label()],
            [],
        );
    }
}
