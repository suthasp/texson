<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactLeadStatus;
use App\Enums\ServiceInterest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * คำขอติดต่อจากฟอร์มบนหน้าเว็บสาธารณะ
 *
 * เป็นข้อมูลส่วนบุคคลตาม PDPA — ชื่อ เบอร์ อีเมล ของคนนอกองค์กร
 */
class ContactLead extends Model
{
    /** @use HasFactory<\Database\Factories\ContactLeadFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'company',
        'contact',
        'service_interest',
        'message',
        'status',
        'handled_by',
        'handled_at',
        'internal_note',
        'locale',
        'ip',
        'user_agent',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'status' => ContactLeadStatus::class,
            'service_interest' => ServiceInterest::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * บันทึกทุกการเปลี่ยนสถานะและการแก้ไข พร้อมค่าก่อน/หลัง (spec 8)
     *
     * ไม่ log ip / user_agent เพราะเป็นค่าที่ระบบเก็บตอนสร้าง ไม่มีใครแก้
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'company', 'contact', 'service_interest', 'message', 'status', 'handled_by', 'internal_note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @param  Builder<ContactLead>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [ContactLeadStatus::New, ContactLeadStatus::Contacted]);
    }

    /** @param  Builder<ContactLead>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%")
                ->orWhere('contact', 'like', "%{$term}%")
                ->orWhere('message', 'like', "%{$term}%");
        });
    }
}
