<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    /** @use HasFactory<\Database\Factories\StockTransferItemFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'qty',
        'lot_no',
        'serial_numbers',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'serial_numbers' => 'array',
        ];
    }

    /** @return BelongsTo<StockTransfer, $this> */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
