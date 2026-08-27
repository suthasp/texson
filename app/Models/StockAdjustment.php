<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockAdjustmentReason;
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
 * ใบปรับปรุงสต็อก ADJ-YYYYMM-####
 */
class StockAdjustment extends Model
{
    /** @use HasFactory<\Database\Factories\StockAdjustmentFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'adjust_no',
        'warehouse_id',
        'reason',
        'adjusted_at',
        'status',
        'note',
        'user_id',
        'posted_at',
        'posted_by',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'reason' => StockAdjustmentReason::class,
            'status' => StockDocumentStatus::class,
            'adjusted_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['adjust_no', 'warehouse_id', 'reason', 'adjusted_at', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return HasMany<StockAdjustmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class, 'adjustment_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

    /** @param  Builder<StockAdjustment>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where('adjust_no', 'like', "%{$term}%");
    }
}
