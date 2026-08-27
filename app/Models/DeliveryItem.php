<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * บรรทัดในใบส่งของ — ผูกกับบรรทัดของใบสั่งขายเสมอ
 * เพื่อให้บวก qty_delivered กลับไปที่บรรทัดต้นทางได้ถูกใบ
 */
class DeliveryItem extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryItemFactory> */
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'sales_order_item_id',
        'product_id',
        'qty',
        'serial_numbers',
        'lot_no',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'serial_numbers' => 'array',
        ];
    }

    /** @return BelongsTo<Delivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /** @return BelongsTo<SalesOrderItem, $this> */
    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * บรรทัดนี้ตัดสต็อกหรือไม่ — ค่าแรงและค่าบริการส่งมอบได้แต่ไม่มีของ
     */
    public function isStockable(): bool
    {
        return $this->product_id !== null;
    }

    /**
     * @return array<int, string>
     */
    public function serials(): array
    {
        return $this->serial_numbers ?? [];
    }
}
