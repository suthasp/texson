<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * หน้างานของลูกค้า เช่น "DC ชั้น 3 อาคาร A"
 *
 * Phase 1 ใช้เลือกในใบเสนอราคา/ใบสั่งขาย · Phase 2 จะเป็นที่ตั้งของ asset ที่ต้องทำ PM
 */
class CustomerSite extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerSiteFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id',
        'site_code',
        'site_name',
        'address_line',
        'province',
        'access_note',
        'primary_contact_id',
        'is_active',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['customer_id', 'site_code', 'site_name', 'address_line', 'province', 'primary_contact_id', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CustomerContact, $this> */
    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'primary_contact_id');
    }

    /** @param  Builder<CustomerSite>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
