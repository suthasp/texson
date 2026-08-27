<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * พยายามส่งใบเสนอราคาที่เข้าเกณฑ์ต้องอนุมัติ โดยที่ยังไม่มีใครอนุมัติ (spec 4.3)
 *
 * ตอบ 409 เหมือนการเปลี่ยนสถานะข้ามขั้น เพราะเป็นปัญหาเรื่องลำดับการทำงาน
 * ไม่ใช่ข้อมูลที่กรอกมาผิด
 */
class ApprovalRequiredException extends DomainException
{
    /**
     * @param  array<int, string>  $reasons  เหตุผลที่เข้าเกณฑ์ เช่น ส่วนลดเกิน 15%
     */
    public function __construct(
        private readonly string $documentLabel,
        private readonly array $reasons,
    ) {
        parent::__construct(__(
            'ใบ :document ต้องผ่านการอนุมัติก่อนส่ง — :reasons',
            ['document' => $documentLabel, 'reasons' => implode(' · ', $reasons)],
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
            'document' => $this->documentLabel,
            'approval_reasons' => $this->reasons,
        ];
    }
}
