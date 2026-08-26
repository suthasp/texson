<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * หน่วยนับสินค้า (spec 3.1 products.uom)
 */
enum Uom: string
{
    case Pcs = 'pcs';
    case Set = 'set';
    case Box = 'box';
    case Roll = 'roll';
    case Meter = 'm';

    public function label(): string
    {
        return match ($this) {
            self::Pcs => __('ชิ้น'),
            self::Set => __('ชุด'),
            self::Box => __('กล่อง'),
            self::Roll => __('ม้วน'),
            self::Meter => __('เมตร'),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $uom): array => $carry + [$uom->value => $uom->label()],
            [],
        );
    }
}
