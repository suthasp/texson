<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * แก้ไขหัวใบสั่งขายระหว่างที่ยังเป็น pending
 *
 * รายการและราคาแก้ที่นี่ไม่ได้ — ยกมาจากใบเสนอราคาที่ลูกค้าตอบรับแล้ว
 * ถ้าต้องเปลี่ยนราคาต้องกลับไปสร้าง revision ของใบเสนอราคาแล้วแปลงใหม่
 */
class SalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('sales_order');

        return $user !== null
            && $order instanceof SalesOrder
            && $user->can('updateAny', $order);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'customer_site_id' => [
                'nullable', 'integer',
                Rule::exists('customer_sites', 'id')->where('customer_id', $this->salesOrderCustomerId()),
            ],
            'required_date' => ['nullable', 'date'],
            'customer_po_no' => ['nullable', 'string', 'max:60'],
            'customer_po_file' => ['nullable', 'file', 'mimetypes:application/pdf,image/png,image/jpeg', 'max:10240'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'delivery_terms' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * หน้างานที่เลือกได้ต้องเป็นของลูกค้ารายนี้เท่านั้น ไม่ใช่ id ใดก็ได้ที่ยิงมา
     */
    private function salesOrderCustomerId(): int
    {
        $order = $this->route('sales_order');

        return $order instanceof SalesOrder ? $order->customer_id : 0;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'warehouse_id' => __('คลังที่จ่ายของ'),
            'customer_site_id' => __('หน้างาน'),
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
        ];
    }
}
