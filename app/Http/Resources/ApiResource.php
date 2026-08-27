<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ฐานของ API Resource ทุกตัว (spec 6)
 *
 * สเปกกำหนดให้ทุก response มีรูป {"data": ..., "meta": ...}
 *  - แบบ collection: Laravel เติม meta ของ pagination ให้เอง
 *  - แบบชิ้นเดียว: คลาสนี้เติม meta.can = สิทธิ์ที่ผู้เรียกมีกับรายการนั้น
 *
 * meta.can มีไว้ให้ client รู้ว่าควรโชว์ปุ่มอะไรได้บ้าง โดยไม่ต้องเดากฎธุรกิจซ้ำฝั่งตัวเอง
 * แล้วยิงไปโดน 403 ทีหลัง
 */
abstract class ApiResource extends JsonResource
{
    /**
     * ชื่อ ability ที่จะรายงานใน meta.can — คืน [] ถ้าไม่ต้องการ
     *
     * @return array<int, string>
     */
    protected function reportableAbilities(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $abilities = $this->reportableAbilities();

        if ($abilities === [] || $request->user() === null) {
            return [];
        }

        $can = [];

        foreach ($abilities as $ability) {
            $can[$ability] = $request->user()->can($ability, $this->resource);
        }

        return ['meta' => ['can' => $can]];
    }

    /**
     * แปลง decimal ของ Eloquent ให้เป็นสตริงเสมอ
     *
     * ห้ามคืนเป็น float — JSON ฝั่ง client จะปัดเศษเงินเพี้ยนเงียบ ๆ
     * (0.1 + 0.2 ใน JS ได้ 0.30000000000000004 เหมือนกับ PHP)
     */
    protected static function decimal(mixed $value, int $scale = 2): string
    {
        return number_format((float) $value, $scale, '.', '');
    }
}
