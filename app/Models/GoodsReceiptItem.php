<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    /** @use HasFactory<\Database\Factories\GoodsReceiptItemFactory> */
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'product_id',
        'qty',
        'unit_cost',
        'lot_no',
        'serial_numbers',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'serial_numbers' => 'array',
        ];
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lineTotal(): string
    {
        return bcmul((string) $this->qty, (string) $this->unit_cost, 2);
    }
}
