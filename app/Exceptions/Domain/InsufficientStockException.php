<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Models\Product;
use App\Models\Warehouse;

/**
 * ตัดสต็อกมากกว่าที่มีอยู่จริง — สต็อกกายภาพติดลบไม่ได้ (spec 4.4)
 *
 * ต่างจากกรณี backorder ตอนจอง (reserve) ซึ่งอนุญาตให้ติดลบได้และบันทึก shortage แทน
 */
class InsufficientStockException extends DomainException
{
    public function __construct(
        private readonly Product $product,
        private readonly Warehouse $warehouse,
        private readonly string $requested,
        private readonly string $available,
    ) {
        parent::__construct(__(
            'สต็อก :sku ที่คลัง :warehouse ไม่พอ — ขอตัด :requested แต่มีอยู่ :available',
            [
                'sku' => $product->sku,
                'warehouse' => $warehouse->code,
                'requested' => rtrim(rtrim($requested, '0'), '.'),
                'available' => rtrim(rtrim($available, '0'), '.'),
            ],
        ));
    }

    public function httpStatus(): int
    {
        return 422;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'shortages' => [[
                'product_id' => $this->product->id,
                'sku' => $this->product->sku,
                'warehouse_id' => $this->warehouse->id,
                'warehouse_code' => $this->warehouse->code,
                'requested' => $this->requested,
                'available' => $this->available,
            ]],
        ];
    }
}
