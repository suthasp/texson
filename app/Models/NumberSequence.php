<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ตัวนับเลขเอกสารรายเดือน — เข้าถึงผ่าน NumberSequenceService เท่านั้น
 *
 * ห้ามอ่านหรือเขียนตารางนี้จากที่อื่นโดยตรง เพราะการออกเลขต้องล็อกแถวด้วย
 * lockForUpdate ภายใน transaction ไม่งั้นเลขชนกันตอนหลายคนกดพร้อมกัน (spec 4.1)
 */
class NumberSequence extends Model
{
    protected $fillable = [
        'doc_type',
        'period',
        'last_no',
    ];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'last_no' => 'integer',
        ];
    }
}
