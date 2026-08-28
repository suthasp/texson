<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * บริการที่ผู้ติดต่อสนใจ — ตรงกับสามบริการหลักบนหน้าเว็บ บวกงานจัดหาอุปกรณ์
 *
 * ค่านี้มาจากผู้ใช้ภายนอกกรอกเอง จึงต้องเป็น enum ปิด ไม่ใช่ข้อความอิสระ
 */
enum ServiceInterest: string
{
    case Audit = 'audit';
    case PmPlanning = 'pm_planning';
    case Training = 'training';
    case Products = 'products';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Audit => __('Audit ห้อง Server / Data Center'),
            self::PmPlanning => __('วางแผน Preventive Maintenance (PM)'),
            self::Training => __('อบรมทีมช่าง / วิศวกร In-house'),
            self::Products => __('จัดหาสินค้าและอุปกรณ์'),
            self::Other => __('เรื่องอื่น ๆ'),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
