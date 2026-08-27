<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * สถานะของ serial แต่ละชิ้น (spec 3.2)
 *
 * เส้นทางปกติ: in_stock → reserved → sold → installed
 * ของที่ติดตั้งแล้วจะกลายเป็น asset ที่ต้องทำ PM ใน Phase 2 ของโรดแมป
 */
enum SerialStatus: string
{
    case InStock = 'in_stock';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Installed = 'installed';
    case Rma = 'rma';
    case Scrapped = 'scrapped';

    public function label(): string
    {
        return match ($this) {
            self::InStock => __('อยู่ในคลัง'),
            self::Reserved => __('จองแล้ว'),
            self::Sold => __('ขายแล้ว'),
            self::Installed => __('ติดตั้งแล้ว'),
            self::Rma => __('ส่งเคลม'),
            self::Scrapped => __('ตัดจำหน่าย'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::InStock => 'green',
            self::Reserved => 'amber',
            self::Sold => 'navy',
            self::Installed => 'aqua',
            self::Rma => 'red',
            self::Scrapped => 'gray',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::InStock => [self::Reserved, self::Sold, self::Rma, self::Scrapped],
            self::Reserved => [self::InStock, self::Sold, self::Scrapped],
            self::Sold => [self::Installed, self::Rma],
            self::Installed => [self::Rma, self::Scrapped],
            // ของที่ส่งเคลมกลับมาแล้วเข้าคลังใหม่ได้ หรือตัดจำหน่ายทิ้ง
            self::Rma => [self::InStock, self::Installed, self::Scrapped],
            self::Scrapped => [],
        };
    }

    /**
     * สถานะที่ยังนับเป็นของในคลัง — ใช้ตรวจว่าจำนวน serial ตรงกับ qty_on_hand
     */
    public function countsAsOnHand(): bool
    {
        return in_array($this, [self::InStock, self::Reserved], true);
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
