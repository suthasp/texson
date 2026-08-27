<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * สินค้าที่ is_serialized ต้องมี serial ครบเท่าจำนวนเสมอ (spec 4.4)
 *
 * ถ้าปล่อยให้ไม่ครบ จำนวน serial ที่ status = in_stock จะไม่ตรงกับ qty_on_hand
 * แล้วตอนส่งของจะเลือก serial ไม่ได้
 */
class SerialNumberMismatchException extends DomainException
{
    public function __construct(
        private readonly string $sku,
        private readonly string $expected,
        private readonly int $given,
    ) {
        parent::__construct(__(
            'สินค้า :sku ต้องระบุ serial ให้ครบ :expected ชิ้น แต่ระบุมา :given ชิ้น',
            ['sku' => $sku, 'expected' => rtrim(rtrim($expected, '0'), '.'), 'given' => (string) $given],
        ));
    }

    public function httpStatus(): int
    {
        return 422;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'sku' => $this->sku,
            'expected' => $this->expected,
            'given' => $this->given,
        ];
    }
}
