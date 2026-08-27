<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * สถานะใบสั่งขาย (spec 3.3, 4.4)
 *
 *   pending → reserved → partially_delivered → delivered
 *   pending | reserved | partially_delivered → cancelled
 *
 * pending  = สร้างแล้วแต่ยังไม่ยืนยัน ยังไม่จองของ แก้ไขได้
 * reserved = ยืนยันแล้ว จองของในคลังไว้ (qty_reserved เพิ่ม แต่ qty_on_hand ยังไม่ลด)
 * partially_delivered / delivered = คำนวณจากยอดที่ส่งจริงเทียบกับยอดสั่ง
 * cancelled = ยกเลิก คืนของที่จองไว้ทั้งหมด
 */
enum SalesOrderStatus: string
{
    case Pending = 'pending';
    case Reserved = 'reserved';
    case PartiallyDelivered = 'partially_delivered';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('รอยืนยัน'),
            self::Reserved => __('จองของแล้ว'),
            self::PartiallyDelivered => __('ส่งของบางส่วน'),
            self::Delivered => __('ส่งของครบแล้ว'),
            self::Cancelled => __('ยกเลิก'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Reserved => 'aqua',
            self::PartiallyDelivered => 'amber',
            self::Delivered => 'green',
            self::Cancelled => 'red',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Reserved, self::Cancelled], true),
            self::Reserved => in_array($target, [self::PartiallyDelivered, self::Delivered, self::Cancelled], true),
            self::PartiallyDelivered => in_array($target, [self::Delivered, self::Cancelled], true),
            self::Delivered, self::Cancelled => false,
        };
    }

    /**
     * แก้ไขรายการในใบได้หรือไม่ — ยืนยันแล้วห้ามแก้ เพราะของถูกจองไว้แล้ว
     */
    public function isEditable(): bool
    {
        return $this === self::Pending;
    }

    /**
     * ของถูกจองไว้ในคลังอยู่หรือไม่ — ใช้ตัดสินว่าตอนยกเลิกต้องคืนของหรือเปล่า
     */
    public function holdsReservation(): bool
    {
        return in_array($this, [self::Reserved, self::PartiallyDelivered], true);
    }

    /**
     * เปิดใบส่งของจากใบนี้ได้หรือไม่
     */
    public function canDeliver(): bool
    {
        return in_array($this, [self::Reserved, self::PartiallyDelivered], true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], true);
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
