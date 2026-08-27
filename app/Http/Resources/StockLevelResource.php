<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockLevel;
use Illuminate\Http\Request;

/**
 * ยอดคงเหลือของสินค้าหนึ่งในคลังหนึ่ง (spec 6 — GET /products/{id}/stock)
 *
 * @mixin StockLevel
 */
class StockLevelResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'warehouse' => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ],
            'qty_on_hand' => self::decimal($this->qty_on_hand, 3),
            'qty_reserved' => self::decimal($this->qty_reserved, 3),
            // คำนวณด้วย bcmath ใน accessor ไม่ใช่ลบด้วย float
            'qty_available' => $this->qty_available,
            'is_below_minimum' => $this->isBelowMinimum(),
        ];
    }
}
