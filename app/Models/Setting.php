<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * ค่าตั้งระบบหนึ่งรายการ — อ่านผ่าน SettingService เสมอ อย่าเรียกตรง
 *
 * @property string $key
 * @property mixed $value
 * @property string $group
 */
class Setting extends Model
{
    use LogsActivity;

    protected $fillable = ['key', 'value', 'group'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * การแก้ค่าตั้ง (โดยเฉพาะเกณฑ์อนุมัติ) ต้องตรวจย้อนหลังได้ว่าใครเปลี่ยนอะไร (spec 8)
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['value'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
