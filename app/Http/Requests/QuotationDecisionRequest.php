<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ใช้กับการกระทำที่มีแค่ข้อความประกอบ — ปฏิเสธ / ยกเลิก / ตีกลับ
 *
 * ตรวจสิทธิ์ตรงนี้ (ไม่ใช่ใน controller) ตามเหตุผลเดียวกับ AuthorizesResource:
 * Laravel รัน authorize() ก่อน validation ถ้าไม่ทำที่นี่ผู้ใช้ที่ไม่มีสิทธิ์จะได้ 302 แทน 403
 */
class QuotationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $quotation = $this->route('quotation');

        if ($user === null || ! $quotation instanceof Quotation) {
            return false;
        }

        return $user->can($this->abilityForRoute(), $quotation);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => __('เหตุผล')];
    }

    /**
     * แปลงชื่อ route เป็นชื่อ ability ของ policy
     */
    private function abilityForRoute(): string
    {
        return match ($this->route()?->getName()) {
            'quotations.reject' => 'decide',
            'quotations.cancel' => 'cancel',
            'quotations.return' => 'approve',
            default => 'update',
        };
    }
}
