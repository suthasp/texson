<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ระดับราคาที่ผูกกับลูกค้า — ใช้เลือกราคาตั้งต้นตอนออกใบเสนอราคา (spec 4.5)
 */
enum PriceTier: string
{
    case Standard = 'standard';
    case Dealer = 'dealer';
    case Project = 'project';

    public function label(): string
    {
        return match ($this) {
            self::Standard => __('ราคามาตรฐาน'),
            self::Dealer => __('ราคาตัวแทนจำหน่าย'),
            self::Project => __('ราคาโครงการ'),
        };
    }

    /**
     * ชื่อคอลัมน์ใน products ที่เก็บราคาของระดับนี้
     */
    public function priceColumn(): string
    {
        return match ($this) {
            self::Standard => 'list_price',
            self::Dealer => 'dealer_price',
            self::Project => 'project_price',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $tier): array => $carry + [$tier->value => $tier->label()],
            [],
        );
    }
}
