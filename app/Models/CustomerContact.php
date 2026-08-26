<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ผู้ติดต่อของลูกค้า — ข้อมูลส่วนบุคคลตาม PDPA (spec 8)
 */
class CustomerContact extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerContactFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id',
        'name',
        'position',
        'phone',
        'email',
        'line_id',
        'is_primary',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['customer_id', 'name', 'position', 'phone', 'email', 'line_id', 'is_primary'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
