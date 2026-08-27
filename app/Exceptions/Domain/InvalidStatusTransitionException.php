<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * เปลี่ยนสถานะเอกสารข้ามขั้น เช่น post ใบที่ post ไปแล้ว
 */
class InvalidStatusTransitionException extends DomainException
{
    public function __construct(
        private readonly string $documentLabel,
        private readonly string $from,
        private readonly string $to,
    ) {
        parent::__construct(__(
            'เปลี่ยนสถานะ :document จาก :from เป็น :to ไม่ได้',
            ['document' => $documentLabel, 'from' => $from, 'to' => $to],
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
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
