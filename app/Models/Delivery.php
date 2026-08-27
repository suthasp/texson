<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PermissionName;
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
 * ใบส่งของ (spec 3.3, 4.4)
 *
 * ใช้สถานะชุดเดียวกับเอกสารคลังอื่น — post แล้วตัดสต็อกจริงและแก้ไม่ได้อีก
 */
class Delivery extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'delivery_no',
        'sales_order_id',
        'warehouse_id',
        'delivery_date',
        'status',
        'receiver_name',
        'receiver_signature_path',
        'vehicle_note',
        'note',
        'created_by',
        'posted_at',
        'posted_by',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'status' => StockDocumentStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['delivery_no', 'status', 'sales_order_id', 'warehouse_id', 'delivery_date', 'receiver_name', 'posted_at', 'posted_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return HasMany<DeliveryItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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

    /**
     * รายการใน ledger ที่เกิดจากใบนี้ — พิสูจน์ได้ว่าตัดสต็อกไปเท่าไรจริง
     *
     * @return MorphMany<StockMovement, $this>
     */
    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'ref');
    }

    /** @param  Builder<Delivery>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('delivery_no', 'like', "%{$term}%")
                ->orWhere('receiver_name', 'like', "%{$term}%")
                ->orWhereHas('salesOrder', fn (Builder $so) => $so->where('so_no', 'like', "%{$term}%"));
        });
    }

    /**
     * ใบส่งของมองเห็นตามใบสั่งขายต้นทาง — sales เห็นเฉพาะของตัวเอง
     *
     * @param  Builder<Delivery>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllDocuments() || $user->can(PermissionName::DeliveryPost->value)) {
            return;
        }

        $query->whereHas('salesOrder', fn (Builder $so) => $so->where('sales_user_id', $user->id));
    }
}
