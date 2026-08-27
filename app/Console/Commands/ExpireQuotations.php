<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QuotationService;
use Illuminate\Console\Command;

/**
 * เปลี่ยนใบเสนอราคาที่ส่งไปแล้วและเลยวันยืนราคาเป็น expired (spec 4.3)
 *
 * ตั้งเวลาไว้ทุกเช้า 06:00 ใน routes/console.php
 * รันซ้ำได้ปลอดภัย — ใบที่เปลี่ยนไปแล้วจะไม่เข้าเงื่อนไขอีก
 */
class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'เปลี่ยนใบเสนอราคาที่เลยวันยืนราคาเป็นสถานะหมดอายุ';

    public function handle(QuotationService $quotations): int
    {
        $count = $quotations->expireOverdue();

        $this->info($count === 0
            ? 'ไม่มีใบเสนอราคาที่หมดอายุวันนี้'
            : "เปลี่ยนใบเสนอราคาเป็นหมดอายุแล้ว {$count} ใบ");

        return self::SUCCESS;
    }
}
