<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContactLeadStatus;
use App\Models\ContactLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ทีมขายอัปเดตการติดตามคำขอ
 *
 * ตรวจสิทธิ์ที่ชั้นนี้เพื่อให้ 403 มาก่อน validation (ไม่ให้เดากฎของฟอร์มได้จาก error)
 * และใช้ updateAny เพื่อให้ "สถานะเปลี่ยนไม่ได้" ตกไปเป็น 409 จาก service (ADR-014)
 */
class UpdateContactLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateAny', ContactLead::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(ContactLeadStatus::class)],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'status' => __('สถานะ'),
            'internal_note' => __('บันทึกภายใน'),
        ];
    }
}
