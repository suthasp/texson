<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    /** @use HasFactory<\Database\Factories\StockAdjustmentItemFactory> */
    use HasFactory;

    protected $fillable = [
        'adjustment_id',
        'product_id',
        'qty_system',
        'qty_counted',
        'qty_diff',
        'lot_no',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'qty_system' => 'decimal:3',
            'qty_counted' => 'decimal:3',
            'qty_diff' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<StockAdjustment, $this> */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'adjustment_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isIncrease(): bool
    {
        return bccomp((string) $this->qty_diff, '0', 3) > 0;
    }
}
