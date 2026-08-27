<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocType;
use App\Models\NumberSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ออกเลขที่เอกสารแบบไม่ชนกัน (spec 4.1)
 *
 * รูปแบบ: {PREFIX}-YYYYMM-#### · running รีเซ็ตทุกเดือน
 *
 * วิธีกันเลขชน: ล็อกแถวของ (doc_type, period) ด้วย lockForUpdate ภายใน transaction
 * คนที่สองที่ขอเลขพร้อมกันจะรอจนคนแรก commit จึงอ่านค่า last_no ที่อัปเดตแล้วเสมอ
 *
 * ห้ามออกเลขด้วยวิธีอื่น เช่น MAX(quote_no)+1 เพราะสองคนอ่านค่าเดียวกันได้
 */
class NumberSequenceService
{
    public function next(DocType $type, ?Carbon $at = null): string
    {
        $period = ($at ?? Carbon::now())->format('Ym');

        $running = DB::transaction(function () use ($type, $period): int {
            // firstOrCreate ก่อน เพื่อให้แถวมีอยู่จริงก่อนจะล็อก
            NumberSequence::firstOrCreate(
                ['doc_type' => $type->value, 'period' => $period],
                ['last_no' => 0],
            );

            $sequence = NumberSequence::query()
                ->where('doc_type', $type->value)
                ->where('period', $period)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_no');

            return $sequence->refresh()->last_no;
        });

        return sprintf(
            '%s-%s-%s',
            $type->value,
            $period,
            str_pad((string) $running, $type->runningDigits(), '0', STR_PAD_LEFT),
        );
    }

    /**
     * เลขล่าสุดที่ออกไปแล้วของเดือนนั้น — ใช้แสดงผลเท่านั้น ห้ามใช้คำนวณเลขถัดไป
     */
    public function lastIssued(DocType $type, ?Carbon $at = null): int
    {
        return (int) NumberSequence::query()
            ->where('doc_type', $type->value)
            ->where('period', ($at ?? Carbon::now())->format('Ym'))
            ->value('last_no');
    }
}
