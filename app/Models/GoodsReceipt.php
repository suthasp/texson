<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockDocumentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ใบรับสินค้า GR-YYYYMM-####
 */
class GoodsReceipt extends Model
{
    /** @use HasFactory<\Database\Factories\GoodsReceiptFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'receipt_no',
        'supplier_id',
        'warehouse_id',
        'reference_no',
        'received_date',
        'status',
        'note',
        'created_by',
        'posted_at',
        'posted_by',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => StockDocumentStatus::class,
            'received_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['receipt_no', 'supplier_id', 'warehouse_id', 'reference_no', 'received_date', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return HasMany<GoodsReceiptItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return MorphMany<StockMovement, $this> */
    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'ref', 'ref_type', 'ref_id');
    }

    /** @param  Builder<GoodsReceipt>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('receipt_no', 'like', "%{$term}%")
                ->orWhere('reference_no', 'like', "%{$term}%");
        });
    }
}
