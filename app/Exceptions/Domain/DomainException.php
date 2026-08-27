<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use RuntimeException;

/**
 * ฐานของ exception ที่มาจากกฎธุรกิจ ไม่ใช่ความผิดพลาดทางเทคนิค
 *
 * ทุกตัวรู้ว่าตัวเองควรตอบ HTTP status อะไรเมื่อถูกโยนจาก API (spec 6)
 */
abstract class DomainException extends RuntimeException
{
    abstract public function httpStatus(): int;

    /**
     * ข้อมูลประกอบที่ส่งกลับไปกับ error response
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [];
    }
}
