<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ยืนยันการลบข้อมูลส่วนบุคคลตามคำขอ PDPA
 *
 * ทำแล้วย้อนไม่ได้ จึงบังคับให้พิมพ์รหัสลูกค้าซ้ำ กันมือลั่นจากปุ่มในตาราง
 */
class ErasePersonalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('forceDelete', $this->customer()) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', Rule::in([$this->customer()->code])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirmation.required' => __('พิมพ์รหัสลูกค้าเพื่อยืนยัน'),
            'confirmation.in' => __('รหัสลูกค้าที่พิมพ์ไม่ตรงกับ :code', ['code' => $this->customer()->code]),
            'reason.required' => __('ระบุเหตุผลของคำขอลบ เพื่อให้ตรวจสอบย้อนหลังได้'),
        ];
    }

    private function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return $customer;
    }
}
