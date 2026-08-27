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
 * ใบโอนคลัง TR-YYYYMM-####
 */
class StockTransfer extends Model
{
    /** @use HasFactory<\Database\Factories\StockTransferFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'transfer_no',
        'from_warehouse_id',
        'to_warehouse_id',
        'transfer_date',
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
            'transfer_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['transfer_no', 'from_warehouse_id', 'to_warehouse_id', 'transfer_date', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return HasMany<StockTransferItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
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

    /** @param  Builder<StockTransfer>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where('transfer_no', 'like', "%{$term}%");
    }
}
