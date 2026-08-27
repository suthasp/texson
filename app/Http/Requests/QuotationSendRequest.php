<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ส่งใบเสนอราคาให้ลูกค้า — เลือกได้ว่าจะส่งอีเมลพร้อม PDF หรือแค่บันทึกว่าส่งแล้ว
 * (บางดีลส่งผ่าน LINE หรือยื่นเอกสารตัวจริง)
 */
class QuotationSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $quotation = $this->route('quotation');

        return $user !== null
            && $quotation instanceof Quotation
            && $user->can('send', $quotation);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'send_email' => ['nullable', 'boolean'],
            'email' => ['nullable', 'required_if_accepted:send_email', 'email', 'max:255'],
            'locale' => ['nullable', 'in:th,en'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'email' => __('อีเมลผู้รับ'),
            'note' => __('ข้อความในอีเมล'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required_if_accepted' => __('ต้องระบุอีเมลผู้รับเมื่อเลือกส่งอีเมล'),
        ];
    }
}
