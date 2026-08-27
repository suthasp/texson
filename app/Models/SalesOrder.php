<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\SalesOrderStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ใบสั่งขาย (spec 3.3, 4.4)
 *
 * ตรรกะการจอง/คืนของและการคำนวณสถานะอยู่ใน SalesOrderService ทั้งหมด
 */
class SalesOrder extends Model
{
    /** @use HasFactory<\Database\Factories\SalesOrderFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'so_no',
        'quotation_id',
        'customer_id',
        'customer_site_id',
        'warehouse_id',
        'sales_user_id',
        'customer_po_no',
        'customer_po_file',
        'order_date',
        'required_date',
        'currency',
        'subtotal',
        'discount_amount',
        'after_discount',
        'vat_rate',
        'vat_amount',
        'grand_total',
        'cost_total',
        'status',
        'confirmed_at',
        'confirmed_by',
        'closed_at',
        'cancel_reason',
        'payment_terms',
        'delivery_terms',
        'note',
        'internal_note',
        'created_by',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'required_date' => 'date',
            'status' => SalesOrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'after_discount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'so_no', 'status', 'customer_id', 'warehouse_id', 'sales_user_id',
                'customer_po_no', 'order_date', 'required_date',
                'subtotal', 'discount_amount', 'after_discount', 'vat_amount', 'grand_total',
                'confirmed_at', 'confirmed_by', 'closed_at', 'cancel_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ── Relations ───────────────────────────────────────────

    /** @return HasMany<SalesOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class)->orderBy('line_no');
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->orderByDesc('delivery_date');
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CustomerSite, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(CustomerSite::class, 'customer_site_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return HasMany<SerialNumber, $this> */
    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    // ── ความคืบหน้า ─────────────────────────────────────────

    /**
     * ยังส่งของไม่ครบทุกบรรทัดใช่หรือไม่
     */
    public function hasOutstanding(): bool
    {
        return $this->items->contains(fn (SalesOrderItem $item): bool => $item->hasOutstanding());
    }

    /**
     * ของที่ยังส่งไม่ครบ รวมทุกบรรทัด — ใช้แสดงบนหน้าจอ
     */
    public function outstandingQty(): string
    {
        return $this->items->reduce(
            static fn (string $carry, SalesOrderItem $item): string => bcadd($carry, $item->outstandingQty(), 3),
            '0.000',
        );
    }

    /**
     * ของที่จองไม่ได้เพราะสต็อกไม่พอ — backorder ตามสเปกข้อ 4.4
     */
    public function shortageQty(): string
    {
        return $this->items->reduce(
            static fn (string $carry, SalesOrderItem $item): string => bcadd($carry, $item->shortageQty(), 3),
            '0.000',
        );
    }

    public function hasShortage(): bool
    {
        return bccomp($this->shortageQty(), '0', 3) > 0;
    }

    /**
     * ความคืบหน้าการส่งของเป็นเปอร์เซ็นต์ — นับเฉพาะบรรทัดที่มีของให้ส่ง
     */
    public function deliveryProgressPercent(): string
    {
        $ordered = '0.000';
        $delivered = '0.000';

        foreach ($this->items as $item) {
            if (! $item->isDeliverable()) {
                continue;
            }

            $ordered = bcadd($ordered, (string) $item->qty_ordered, 3);
            $delivered = bcadd($delivered, (string) $item->qty_delivered, 3);
        }

        return Money::percentage($delivered, $ordered);
    }

    public function marginAmount(): string
    {
        return Money::sub((string) $this->after_discount, (string) $this->cost_total);
    }

    public function marginPercent(): string
    {
        return Money::percentage($this->marginAmount(), (string) $this->after_discount);
    }

    // ── Scopes ──────────────────────────────────────────────

    /** @param  Builder<SalesOrder>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('so_no', 'like', "%{$term}%")
                ->orWhere('customer_po_no', 'like', "%{$term}%")
                ->orWhereHas('customer', function (Builder $c) use ($term): void {
                    $c->where('name_th', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%");
                });
        });
    }

    /**
     * sales เห็นเฉพาะใบของตัวเอง เว้นแต่เป็น admin หรือผู้จัดการฝ่ายขาย (spec 8)
     *
     * คนคลังเห็นทุกใบเพราะเป็นคนจัดของส่ง — เงื่อนไขต้องตรงกับ SalesOrderPolicy::owns()
     * ไม่งั้นรายการจะว่างทั้งที่เปิดใบรายตัวได้
     *
     * @param  Builder<SalesOrder>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllDocuments() || $user->can(PermissionName::DeliveryCreate->value)) {
            return;
        }

        $query->where('sales_user_id', $user->id);
    }

    /**
     * ใบที่ยังต้องส่งของอยู่ — ใช้บนแดชบอร์ดและหน้าจอคลัง
     *
     * @param  Builder<SalesOrder>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            SalesOrderStatus::Pending->value,
            SalesOrderStatus::Reserved->value,
            SalesOrderStatus::PartiallyDelivered->value,
        ]);
    }
}
