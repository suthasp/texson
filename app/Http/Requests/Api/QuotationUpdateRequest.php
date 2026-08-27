<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Requests\QuotationRequest;
use App\Models\Quotation;

/**
 * PUT /api/v1/quotations/{id} — เฉพาะ draft (spec 6)
 *
 * ใช้กฎ validation ชุดเดียวกับฟอร์มบนเว็บ แต่ตรวจสิทธิ์ด้วย ability 'updateAny'
 * ที่ยังไม่ดูสถานะ เพื่อให้ใบที่ส่งไปแล้วตกไปโดน InvalidStatusTransitionException (409)
 * แทนที่จะได้ 403 ซึ่งสื่อผิดว่าผู้เรียกไม่มีสิทธิ์
 */
class QuotationUpdateRequest extends QuotationRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $quotation = $this->route('quotation');

        return $user !== null
            && $quotation instanceof Quotation
            && $user->can('updateAny', $quotation);
    }
}
