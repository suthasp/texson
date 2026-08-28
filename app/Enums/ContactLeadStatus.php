<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * สถานะคำขอติดต่อจากหน้าเว็บสาธารณะ
 *
 * new       = เพิ่งส่งเข้ามา ยังไม่มีใครรับ
 * contacted = ติดต่อกลับแล้ว อยู่ระหว่างคุย
 * closed    = จบเรื่องแล้ว (ได้งาน ไม่ได้งาน หรือไม่สนใจต่อ)
 * spam      = ไม่ใช่คำขอจริง เก็บไว้เพื่อดูรูปแบบการยิงสแปม ไม่ลบทิ้ง
 */
enum ContactLeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Closed = 'closed';
    case Spam = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::New => __('ใหม่'),
            self::Contacted => __('ติดต่อแล้ว'),
            self::Closed => __('ปิดเรื่อง'),
            self::Spam => __('สแปม'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::New => 'aqua',
            self::Contacted => 'amber',
            self::Closed => 'green',
            self::Spam => 'gray',
        };
    }

    /**
     * ทำเครื่องหมายสแปมได้ทุกเมื่อ ส่วนเรื่องที่ปิดแล้วเปิดใหม่ไม่ได้
     * ถ้าลูกค้าติดต่อมาอีกก็นับเป็นคำขอใหม่ ไม่ใช่รื้อของเก่า
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::New => in_array($target, [self::Contacted, self::Closed, self::Spam], true),
            self::Contacted => in_array($target, [self::Closed, self::Spam], true),
            self::Closed, self::Spam => false,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Contacted], true);
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
