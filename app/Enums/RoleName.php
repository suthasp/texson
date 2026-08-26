<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Role ทั้งหมดของระบบ (spec 3.1 + ADR-003 เพิ่ม sales_manager)
 *
 * ค่าเหล่านี้ถูก seed ลงตาราง roles ของ spatie/laravel-permission
 */
enum RoleName: string
{
    case Admin = 'admin';
    case SalesManager = 'sales_manager';
    case Sales = 'sales';
    case Warehouse = 'warehouse';
    case Engineer = 'engineer';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('ผู้ดูแลระบบ'),
            self::SalesManager => __('ผู้จัดการฝ่ายขาย'),
            self::Sales => __('ฝ่ายขาย'),
            self::Warehouse => __('คลังสินค้า'),
            self::Engineer => __('วิศวกร'),
            self::Viewer => __('ผู้ดูอย่างเดียว'),
        };
    }

    /**
     * Role ที่เห็นเอกสารของ sales ทุกคน (spec 8)
     *
     * @return array<int, string>
     */
    public static function seeAllDocuments(): array
    {
        return [self::Admin->value, self::SalesManager->value];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $role): array => $carry + [$role->value => $role->label()],
            [],
        );
    }
}
