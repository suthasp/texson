<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ขอ token สำหรับเรียก API (spec 6 — Sanctum token)
 *
 * เปิดให้เรียกโดยไม่ต้องล็อกอินอยู่แล้ว แต่ถูกจำกัดที่ 5 ครั้ง/นาที/IP ตาม spec 8
 */
class IssueTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            // ชื่ออุปกรณ์ทำให้ผู้ดูแลเพิกถอน token ทีละเครื่องได้ ไม่ต้องล้างทั้งหมด
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => __('อีเมล'),
            'password' => __('รหัสผ่าน'),
            'device_name' => __('ชื่ออุปกรณ์'),
        ];
    }
}
