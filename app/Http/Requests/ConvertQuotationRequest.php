<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * สร้างใบสั่งขายจากใบเสนอราคาที่ลูกค้าตอบรับแล้ว (spec 4.3)
 *
 * รับเฉพาะข้อมูลที่ใบเสนอราคาไม่มี — รายการและราคายกมาจากใบเสนอราคาทั้งชุด
 * ห้ามให้แก้ตรงนี้ เพราะราคาที่ตกลงกับลูกค้าคือราคาบนใบที่ลูกค้าเซ็นรับ
 */
class ConvertQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $quotation = $this->route('quotation');

        return $user !== null
            && $quotation instanceof Quotation
            && $user->can('convertToSalesOrder', $quotation);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'order_date' => ['nullable', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'customer_po_no' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:2000'],

            // ใบสั่งซื้อของลูกค้า — ตรวจ mime จริงด้วย finfo ไม่ใช่แค่นามสกุล (spec 8)
            'customer_po_file' => ['nullable', 'file', 'mimetypes:application/pdf,image/png,image/jpeg', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'warehouse_id' => __('คลังที่จ่ายของ'),
            'order_date' => __('วันที่สั่งซื้อ'),
            'required_date' => __('วันที่ต้องการรับของ'),
            'customer_po_no' => __('เลขที่ใบสั่งซื้อของลูกค้า'),
            'customer_po_file' => __('ไฟล์ใบสั่งซื้อ'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer_po_file.mimetypes' => __('ไฟล์ใบสั่งซื้อต้องเป็น PDF, PNG หรือ JPEG'),
            'customer_po_file.max' => __('ไฟล์ใบสั่งซื้อต้องไม่เกิน 10 MB'),
            'required_date.after_or_equal' => __('วันที่ต้องการรับของต้องไม่ก่อนวันที่สั่งซื้อ'),
        ];
    }
}
