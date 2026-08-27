<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * พยายามสร้างใบสั่งขายจากใบเสนอราคาที่แปลงไปแล้ว
 *
 * สเปกข้อ 4.3 ระบุว่าใบเสนอราคาหนึ่งใบสร้างใบสั่งขายได้ครั้งเดียว
 * ตอบ 409 เพราะเป็นเรื่องลำดับการทำงาน ไม่ใช่ข้อมูลที่กรอกมาผิด
 */
class QuotationAlreadyConvertedException extends DomainException
{
    public function __construct(
        private readonly string $quotationNo,
        private readonly int $salesOrderId,
        private readonly string $salesOrderNo,
    ) {
        parent::__construct(__(
            'ใบเสนอราคา :quote ถูกแปลงเป็นใบสั่งขาย :so ไปแล้ว',
            ['quote' => $quotationNo, 'so' => $salesOrderNo],
        ));
    }

    public function httpStatus(): int
    {
        return 409;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'quotation_no' => $this->quotationNo,
            'sales_order_id' => $this->salesOrderId,
            'sales_order_no' => $this->salesOrderNo,
        ];
    }
}
