<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ประเภทรายการใน ledger (spec 3.2)
 *
 * ทิศทางของแต่ละประเภทตายตัว — ใช้กำหนดเครื่องหมายของ qty ที่บันทึกลง ledger
 * เพื่อให้ผลรวม qty ของสินค้า+คลัง เท่ากับ qty_on_hand เสมอ
 */
enum StockMovementType: string
{
    case Receive = 'receive';
    case Issue = 'issue';
    case AdjustIn = 'adjust_in';
    case AdjustOut = 'adjust_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case ReturnIn = 'return_in';

    public function label(): string
    {
        return match ($this) {
            self::Receive => __('รับเข้า'),
            self::Issue => __('ตัดออก'),
            self::AdjustIn => __('ปรับเพิ่ม'),
            self::AdjustOut => __('ปรับลด'),
            self::TransferIn => __('โอนเข้า'),
            self::TransferOut => __('โอนออก'),
            self::ReturnIn => __('รับคืน'),
        };
    }

    /**
     * ประเภทนี้ทำให้สต็อกเพิ่มขึ้นหรือไม่
     */
    public function isInbound(): bool
    {
        return match ($this) {
            self::Receive, self::AdjustIn, self::TransferIn, self::ReturnIn => true,
            self::Issue, self::AdjustOut, self::TransferOut => false,
        };
    }

    /**
     * เครื่องหมายที่ต้องใช้กับ qty ตอนเขียนลง ledger
     */
    public function sign(): int
    {
        return $this->isInbound() ? 1 : -1;
    }

    public function badgeColor(): string
    {
        return $this->isInbound() ? 'green' : 'amber';
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
