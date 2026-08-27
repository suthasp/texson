<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Requests\StockAdjustmentRequest;

/**
 * POST /api/v1/stock/adjust (spec 6)
 *
 * ใช้กฎเดียวกับฟอร์มบนเว็บทั้งหมด แล้วเพิ่มธง post เข้ามา
 * เพราะ API ถูกเรียกจากแอปนับสต็อกที่ต้องการบันทึกจบในครั้งเดียว
 *
 * สืบทอดจาก StockAdjustmentRequest เพื่อไม่ให้กฎ validation แตกออกเป็นสองชุด
 * ที่แก้ไม่พร้อมกันแล้วเพี้ยนกันภายหลัง
 */
class StockAdjustRequest extends StockAdjustmentRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // ไม่ระบุมาถือว่าบันทึกเข้าสต็อกทันที — เป็นสิ่งที่ผู้เรียก API ต้องการเกือบทุกครั้ง
            'post' => ['nullable', 'boolean'],
        ];
    }

    public function shouldPost(): bool
    {
        return $this->boolean('post', true);
    }
}
