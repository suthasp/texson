<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ServiceInterest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ฟอร์ม "ปรึกษาฟรี" บนหน้าเว็บสาธารณะ
 *
 * เป็น endpoint เดียวของระบบที่คนนอกยิงเข้ามาได้โดยไม่ต้องล็อกอิน
 * จึงจำกัดความยาวทุกช่องอย่างเข้มงวด และมีกับดักสแปมอยู่ด้วย
 */
class ContactLeadRequest extends FormRequest
{
    /** ช่องล่อสแปม — บอตกรอกทุกช่องที่เจอ ส่วนคนไม่เห็นช่องนี้เลย */
    public const HONEYPOT = 'website';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:150'],
            'contact' => ['required', 'string', 'max:150'],
            'service_interest' => ['nullable', Rule::enum(ServiceInterest::class)],
            'message' => ['nullable', 'string', 'max:2000'],
            self::HONEYPOT => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('ชื่อ–นามสกุล'),
            'company' => __('บริษัท / องค์กร'),
            'contact' => __('เบอร์โทร / อีเมล'),
            'service_interest' => __('บริการที่สนใจ'),
            'message' => __('รายละเอียดเพิ่มเติม'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            self::HONEYPOT.'.prohibited' => __('ไม่สามารถส่งข้อความได้ กรุณาลองใหม่อีกครั้ง'),
        ];
    }

    /**
     * ข้อมูลที่พร้อมบันทึก รวมร่องรอยที่ใช้ตามตอนโดนยิงสแปม
     *
     * @return array<string, mixed>
     */
    public function lead(): array
    {
        return [
            ...$this->safe()->except([self::HONEYPOT]),
            'locale' => app()->getLocale(),
            'ip' => $this->ip(),
            'user_agent' => substr((string) $this->userAgent(), 0, 500),
        ];
    }
}
