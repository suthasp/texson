<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Ledger ของสต็อก — append-only (spec 3.2)
 *
 * โมเดลนี้บล็อก update และ delete ไว้ที่ระดับโค้ด การแก้ยอดที่ลงผิดต้องทำด้วย
 * ใบปรับปรุงสต็อกใบใหม่ ไม่ใช่การแก้ประวัติ
 */
class StockMovement extends Model
{
    /** @use HasFactory<\Database\Factories\StockMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'qty',
        'unit_cost',
        'balance_after',
        'ref_type',
        'ref_id',
        'lot_no',
        'note',
        'user_id',
        'moved_at',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'balance_after' => 'decimal:3',
            'moved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('stock_movements เป็น append-only ledger — แก้ไขรายการเดิมไม่ได้');
        });

        static::deleting(function (): never {
            throw new LogicException('stock_movements เป็น append-only ledger — ลบรายการไม่ได้');
        });
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * เอกสารต้นทาง — GoodsReceipt, StockTransfer, StockAdjustment
     * และจะรับ Delivery (Phase 4) กับ WorkOrder (Phase 2 ของโรดแมป) ได้โดยไม่ต้องแก้อะไร
     *
     * @return MorphTo<Model, $this>
     */
    public function ref(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'ref_type', 'ref_id');
    }

    /** @param  Builder<StockMovement>  $query */
    public function scopeForProduct(Builder $query, ?int $productId): void
    {
        if ($productId !== null) {
            $query->where('product_id', $productId);
        }
    }

    /** @param  Builder<StockMovement>  $query */
    public function scopeForWarehouse(Builder $query, ?int $warehouseId): void
    {
        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }
    }

    /** @param  Builder<StockMovement>  $query */
    public function scopeMovedBetween(Builder $query, ?string $from, ?string $to): void
    {
        if (filled($from)) {
            $query->where('moved_at', '>=', $from.' 00:00:00');
        }

        if (filled($to)) {
            $query->where('moved_at', '<=', $to.' 23:59:59');
        }
    }
}
