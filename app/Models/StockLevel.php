<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ยอดคงเหลือรายสินค้าต่อคลัง
 *
 * ห้ามแก้ตรง ๆ จากที่อื่น — ต้องผ่าน StockService เท่านั้น เพื่อให้ ledger กับยอดสรุปตรงกันเสมอ
 */
class StockLevel extends Model
{
    /** @use HasFactory<\Database\Factories\StockLevelFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'qty_on_hand',
        'qty_reserved',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'decimal:3',
            'qty_reserved' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * ของที่ขายได้จริง = ของในมือ − ของที่ถูกจองไว้แล้ว (spec 3.2)
     *
     * @return Attribute<string, never>
     */
    protected function qtyAvailable(): Attribute
    {
        return Attribute::get(
            fn (): string => bcsub((string) $this->qty_on_hand, (string) $this->qty_reserved, 3),
        );
    }

    /**
     * ต่ำกว่าจุดสั่งซื้อของสินค้าหรือยัง — ใช้ min_stock ที่ join มาแล้วถ้ามี
     */
    public function isBelowMinimum(): bool
    {
        $minStock = (string) ($this->product?->min_stock ?? '0');

        return bccomp($this->qty_available, $minStock, 3) < 0;
    }

    /**
     * เหลือน้อยกว่าจุดสั่งซื้อ — เทียบ qty_available กับ products.min_stock
     *
     * @param  Builder<StockLevel>  $query
     */
    public function scopeBelowMinimum(Builder $query): void
    {
        $query->whereHas('product', fn (Builder $p) => $p->where('min_stock', '>', 0))
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->whereRaw('(stock_levels.qty_on_hand - stock_levels.qty_reserved) < products.min_stock')
            ->select('stock_levels.*');
    }
}
