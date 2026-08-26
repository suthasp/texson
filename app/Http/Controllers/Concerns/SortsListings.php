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
    protected function applySort(Builder $query, Request $request, array $sortable, string $default): void
    {
        $column = $request->string('sort')->toString();
        $column = in_array($column, $sortable, true) ? $column : $default;

        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $direction);
    }
}
