<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * เรียงลำดับตารางฝั่งเซิร์ฟเวอร์โดยรับชื่อคอลัมน์จาก query string
 *
 * รับเฉพาะคอลัมน์ที่อยู่ใน whitelist เพื่อกัน SQL injection ผ่านชื่อคอลัมน์
 * (ชื่อคอลัมน์ผูกเป็น binding ไม่ได้ จึงต้องกรองด้วยรายการที่อนุญาต)
 */
trait SortsListings
{
    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, string>  $sortable
     */
    protected function applySort(
        Builder $query,
        Request $request,
        array $sortable,
        string $default,
        string $defaultDirection = 'asc',
    ): void {
        $column = $request->string('sort')->toString();
        $column = in_array($column, $sortable, true) ? $column : $default;

        // กล่องขาเข้าอยากได้ใหม่สุดขึ้นก่อน ส่วนตารางข้อมูลหลักอยากได้เรียงจากน้อยไปมาก
        $requested = $request->string('direction')->toString();
        $direction = in_array($requested, ['asc', 'desc'], true) ? $requested : $defaultDirection;

        $query->orderBy($column, $direction);
    }
}
