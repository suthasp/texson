<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * วงจรชีวิตใบเสนอราคา (spec 4.3)
 *
 *   draft → pending_approval → sent → accepted | rejected | expired
 *   draft → cancelled          sent → cancelled
 *
 * หมายเหตุเรื่องการอนุมัติ (ADR-010):
 * การ "อนุมัติ" ไม่ได้เปลี่ยน status — มันประทับ approved_at/approved_by ไว้บนใบที่อยู่
 * ระหว่าง pending_approval แล้วปลดล็อกให้ส่งได้ เพราะ enum ตามสเปกไม่มีสถานะ approved
 * และการเพิ่มสถานะใหม่จะทำให้ query ที่กรอง sent/accepted ทั่วระบบเพี้ยน
 */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('ร่าง'),
            self::PendingApproval => __('รออนุมัติ'),
            self::Sent => __('ส่งแล้ว'),
            self::Accepted => __('ลูกค้าตอบรับ'),
            self::Rejected => __('ลูกค้าปฏิเสธ'),
            self::Expired => __('หมดอายุ'),
            self::Cancelled => __('ยกเลิก'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'amber',
            self::Sent => 'aqua',
            self::Accepted => 'green',
            self::Rejected, self::Cancelled => 'red',
            self::Expired => 'navy',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::PendingApproval, self::Sent, self::Cancelled], true),
            // ผู้อนุมัติตีกลับให้แก้ได้ → กลับไป draft
            self::PendingApproval => in_array($target, [self::Draft, self::Sent, self::Cancelled], true),
            self::Sent => in_array($target, [self::Accepted, self::Rejected, self::Expired, self::Cancelled], true),
            self::Accepted, self::Rejected, self::Expired, self::Cancelled => false,
        };
    }

    /**
     * แก้ไขรายการในใบได้หรือไม่ — ใบที่ส่งออกไปแล้วต้องแก้ผ่านการสร้าง revision เท่านั้น
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval], true);
    }

    /**
     * ใบที่จบวงจรแล้ว ไม่มีการกระทำต่อได้อีกนอกจากสร้าง revision
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Expired, self::Cancelled], true);
    }

    /**
     * สร้าง revision ใหม่จากใบสถานะนี้ได้หรือไม่ (spec 4.3)
     *
     * ใบที่ลูกค้าตอบรับแล้วห้ามแก้ — ต้องออกใบใหม่ทั้งใบ ไม่ใช่ revision ของดีลที่ปิดไปแล้ว
     */
    public function canBeRevised(): bool
    {
        return in_array($this, [self::Sent, self::Rejected, self::Expired], true);
    }

    /**
     * นับเป็นใบที่ยังมีโอกาสปิดการขาย — ใช้คำนวณ pipeline และ win rate
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Sent], true);
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
