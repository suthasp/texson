<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SerialStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Serial รายชิ้น — ใช้ติดตาม UPS และแบตเตอรี่ตั้งแต่รับเข้าจนถึงงาน PM ใน Phase 2 ของโรดแมป
 */
class SerialNumber extends Model
{
    /** @use HasFactory<\Database\Factories\SerialNumberFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'product_id',
        'serial_no',
        'warehouse_id',
        'status',
        'customer_id',
        'customer_site_id',
        'sales_order_id',
        'warranty_start',
        'warranty_end',
        'lot_no',
        'note',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => SerialStatus::class,
            'warranty_start' => 'date',
            'warranty_end' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'warehouse_id', 'customer_id', 'customer_site_id', 'warranty_start', 'warranty_end'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CustomerSite, $this> */
    public function customerSite(): BelongsTo
    {
        return $this->belongsTo(CustomerSite::class);
    }

    /**
     * เปลี่ยนสถานะโดยตรวจเส้นทางที่อนุญาตก่อนเสมอ
     *
     * @param  array<string, mixed>  $extra
     *
     * @throws InvalidStatusTransitionException
     */
    public function transitionTo(SerialStatus $target, array $extra = []): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new InvalidStatusTransitionException(
                __('Serial :serial', ['serial' => $this->serial_no]),
                $this->status->label(),
                $target->label(),
            );
        }

        $this->update([...$extra, 'status' => $target]);
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_end !== null && $this->warranty_end->isFuture();
    }

    /** @param  Builder<SerialNumber>  $query */
    public function scopeAvailableIn(Builder $query, int $warehouseId): void
    {
        $query->where('warehouse_id', $warehouseId)
            ->where('status', SerialStatus::InStock);
    }

    /** @param  Builder<SerialNumber>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('serial_no', 'like', "%{$term}%")
                ->orWhereHas('product', fn (Builder $p) => $p->where('sku', 'like', "%{$term}%"));
        });
    }
}
