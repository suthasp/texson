<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PriceTier;
use App\Enums\QuotationStatus;
use App\Services\QuotationCalculator;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ใบเสนอราคา (spec 3.3, 4.2, 4.3)
 *
 * โมเดลนี้เก็บเฉพาะ relation, cast และ scope — ตรรกะการเปลี่ยนสถานะและการคำนวณเงิน
 * อยู่ใน QuotationService และ QuotationCalculator ทั้งหมด
 */
class Quotation extends Model
{
    /** @use HasFactory<\Database\Factories\QuotationFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'quote_no',
        'revision',
        'parent_quotation_id',
        'customer_id',
        'customer_contact_id',
        'customer_site_id',
        'sales_user_id',
        'issue_date',
        'valid_until',
        'currency',
        'price_tier',
        'subtotal',
        'discount_amount',
        'after_discount',
        'vat_rate',
        'vat_amount',
        'grand_total',
        'cost_total',
        'status',
        'payment_terms',
        'delivery_terms',
        'lead_time_note',
        'terms_and_conditions',
        'customer_note',
        'internal_note',
        'approved_by',
        'approved_at',
        'sent_at',
        'decided_at',
        'lost_reason',
        'superseded_at',
        'created_by',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'issue_date' => 'date',
            'valid_until' => 'date',
            'price_tier' => PriceTier::class,
            'status' => QuotationStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'after_discount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * ทุกการเปลี่ยนสถานะและทุกการแก้ราคาต้องมีใน activity log พร้อมค่าก่อน/หลัง (spec 8)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'quote_no', 'revision', 'status', 'customer_id', 'sales_user_id',
                'issue_date', 'valid_until', 'price_tier',
                'subtotal', 'discount_amount', 'after_discount', 'vat_rate', 'vat_amount', 'grand_total',
                'approved_by', 'approved_at', 'sent_at', 'decided_at', 'lost_reason', 'superseded_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ── Relations ───────────────────────────────────────────

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('line_no');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CustomerContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'customer_contact_id');
    }

    /** @return BelongsTo<CustomerSite, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(CustomerSite::class, 'customer_site_id');
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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** ใบก่อนหน้าในสายการแก้ไข @return BelongsTo<Quotation, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_quotation_id');
    }

    /** revision ที่ถูกสร้างต่อจากใบนี้ @return HasMany<Quotation, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_quotation_id');
    }

    /**
     * ใบสั่งขายที่แปลงมาจากใบนี้ — หนึ่งใบต่อหนึ่งใบเสนอราคา (spec 4.3)
     *
     * @return HasOne<SalesOrder, $this>
     */
    public function salesOrder(): HasOne
    {
        return $this->hasOne(SalesOrder::class);
    }

    // ── สถานะและการแสดงผล ───────────────────────────────────

    /**
     * เลขที่พร้อม revision สำหรับแสดงและตั้งชื่อไฟล์ PDF (spec 5)
     */
    public function displayNo(): string
    {
        return $this->revision > 0
            ? $this->quote_no.'_rev'.$this->revision
            : $this->quote_no;
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }

    /**
     * ใบที่ส่งแล้วและเลยวันยืนราคา — job จะเปลี่ยนเป็น expired ทุกเช้า
     * แต่หน้าจอควรบอกผู้ใช้ทันทีโดยไม่ต้องรอ job
     */
    public function isPastValidity(?Carbon $asOf = null): bool
    {
        return $this->valid_until->isBefore(($asOf ?? Carbon::now())->startOfDay());
    }

    public function daysUntilExpiry(?Carbon $asOf = null): int
    {
        return (int) ($asOf ?? Carbon::now())->startOfDay()->diffInDays($this->valid_until, false);
    }

    // ── ตัวเลขประกอบที่คำนวณสด ───────────────────────────────

    /**
     * margin เป็นบาท = ยอดหลังหักส่วนลด (ไม่รวม VAT) − ต้นทุนรวม
     */
    public function marginAmount(): string
    {
        return Money::sub((string) $this->after_discount, (string) $this->cost_total);
    }

    /**
     * margin เป็นเปอร์เซ็นต์ — แดงเมื่อต่ำกว่า 10 (spec 4.5)
     */
    public function marginPercent(): string
    {
        return Money::percentage($this->marginAmount(), (string) $this->after_discount);
    }

    /**
     * ส่วนลดรวม (บรรทัด + ท้ายบิล) คิดเป็นเปอร์เซ็นต์ของราคาเต็ม
     */
    public function totalDiscountPercent(): string
    {
        $gross = Money::add((string) $this->subtotal, $this->lineDiscountTotal());

        return Money::percentage(
            Money::add($this->lineDiscountTotal(), (string) $this->discount_amount),
            $gross,
        );
    }

    public function lineDiscountTotal(): string
    {
        return $this->items->reduce(
            static fn (string $carry, QuotationItem $item): string => Money::add($carry, (string) $item->discount_amount),
            '0.00',
        );
    }

    /**
     * ฐานและยอดหัก ณ ที่จ่าย 3% — แสดงท้ายใบเมื่อมีค่าบริการ ไม่หักจาก grand_total (spec 4.2)
     *
     * @return array{base: string, amount: string}
     */
    public function withholding(): array
    {
        $base = $this->items
            ->filter(static fn (QuotationItem $item): bool => $item->item_type->isWithholdingBase())
            ->reduce(static fn (string $carry, QuotationItem $item): string => Money::add($carry, (string) $item->line_total), '0.00');

        return [
            'base' => $base,
            'amount' => Money::percentOf($base, QuotationCalculator::WITHHOLDING_RATE),
        ];
    }

    public function hasWithholding(): bool
    {
        return ! Money::isZero($this->withholding()['base']);
    }

    // ── Scopes ──────────────────────────────────────────────

    /** @param  Builder<Quotation>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('quote_no', 'like', "%{$term}%")
                ->orWhereHas('customer', function (Builder $c) use ($term): void {
                    $c->where('name_th', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%");
                });
        });
    }

    /**
     * จำกัดให้เห็นเฉพาะใบของตัวเอง เว้นแต่เป็น admin หรือผู้จัดการฝ่ายขาย (spec 8)
     *
     * @param  Builder<Quotation>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllDocuments()) {
            return;
        }

        $query->where('sales_user_id', $user->id);
    }

    /** @param  Builder<Quotation>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            QuotationStatus::Draft->value,
            QuotationStatus::PendingApproval->value,
            QuotationStatus::Sent->value,
        ]);
    }

    /**
     * ใบที่ส่งแล้วและใกล้หมดอายุภายในกี่วัน (spec 7 — การ์ดบนแดชบอร์ด)
     *
     * @param  Builder<Quotation>  $query
     */
    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query->where('status', QuotationStatus::Sent->value)
            ->whereNull('superseded_at')
            ->whereBetween('valid_until', [
                Carbon::now()->toDateString(),
                Carbon::now()->addDays($days)->toDateString(),
            ]);
    }
}
