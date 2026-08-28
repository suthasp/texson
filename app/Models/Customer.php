<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PriceTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code',
        'name_th',
        'name_en',
        'tax_id',
        'branch_code',
        'address_line',
        'subdistrict',
        'district',
        'province',
        'postcode',
        'phone',
        'email',
        'credit_term_days',
        'payment_terms',
        'price_tier',
        'notes',
        'is_active',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'price_tier' => PriceTier::class,
            'credit_term_days' => 'integer',
            'is_active' => 'boolean',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * ข้อมูลลูกค้ามีข้อมูลส่วนบุคคลตาม PDPA — ต้อง log ทุกการแก้ไขพร้อมค่าก่อน/หลัง (spec 8)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'code', 'name_th', 'name_en', 'tax_id', 'branch_code',
                'address_line', 'subdistrict', 'district', 'province', 'postcode',
                'phone', 'email', 'credit_term_days', 'payment_terms', 'price_tier', 'is_active',
                'anonymized_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return HasMany<CustomerContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    /** @return HasOne<CustomerContact, $this> */
    public function primaryContact(): HasOne
    {
        return $this->hasOne(CustomerContact::class)->where('is_primary', true);
    }

    /** @return HasMany<CustomerSite, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(CustomerSite::class);
    }

    /** @return HasMany<Quotation, $this> */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /** @return HasMany<SalesOrder, $this> */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /**
     * ข้อมูลส่วนบุคคลถูกลบตามคำขอ PDPA แล้ว — เหลือไว้แต่ตัวเลขที่เอกสารภาษีอ้างถึง (ADR-024)
     */
    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    /**
     * ที่อยู่บรรทัดเดียวสำหรับหัวใบเสนอราคา
     */
    public function fullAddress(): string
    {
        return collect([
            $this->address_line,
            $this->subdistrict,
            $this->district,
            $this->province,
            $this->postcode,
        ])->filter()->implode(' ');
    }

    public function branchLabel(): string
    {
        return $this->branch_code === '00000'
            ? __('สำนักงานใหญ่')
            : __('สาขา :code', ['code' => $this->branch_code]);
    }

    /** @param  Builder<Customer>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<Customer>  $query */
    public function scopeAnonymized(Builder $query): void
    {
        $query->whereNotNull('anonymized_at');
    }

    /** @param  Builder<Customer>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('code', 'like', "%{$term}%")
                ->orWhere('name_th', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%")
                ->orWhere('tax_id', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
