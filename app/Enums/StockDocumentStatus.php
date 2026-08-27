<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * สถานะเอกสารคลัง (ใบรับสินค้า / ใบโอนคลัง / ใบปรับปรุงสต็อก)
 *
 * draft   = แก้ไขได้ ยังไม่กระทบสต็อก
 * posted  = กระทบสต็อกแล้ว แก้ไม่ได้อีก (ledger เป็น append-only)
 * cancelled = ยกเลิกตั้งแต่ยังเป็น draft
 */
enum StockDocumentStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('ร่าง'),
            self::Posted => __('บันทึกแล้ว'),
            self::Cancelled => __('ยกเลิก'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Posted => 'green',
            self::Cancelled => 'red',
        };
    }

    /**
     * เอกสารที่ post แล้วห้ามย้อนกลับเป็น draft — การกลับรายการต้องออกเอกสารใหม่
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Posted, self::Cancelled], true),
            self::Posted, self::Cancelled => false,
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static fn (array $carry, self $status): array => $carry + [$status->value => $status->label()],
            [],
        );
    }
}
