<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesResource;
use App\Models\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoodsReceiptRequest extends FormRequest
{
    use AuthorizesResource;

    /** @return class-string<GoodsReceipt> */
    protected function resourceClass(): string
    {
        return GoodsReceipt::class;
    }

    protected function resourceRouteKey(): string
    {
        return 'goods_receipt';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'received_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'items.*.lot_no' => ['nullable', 'string', 'max:50'],
            'items.*.serial_numbers' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'supplier_id' => __('ผู้ขาย'),
            'warehouse_id' => __('คลังที่รับเข้า'),
            'reference_no' => __('เลขที่อ้างอิงของผู้ขาย'),
            'received_date' => __('วันที่รับ'),
            'items' => __('รายการสินค้า'),
            'items.*.product_id' => __('สินค้า'),
            'items.*.qty' => __('จำนวน'),
            'items.*.unit_cost' => __('ราคาทุนต่อหน่วย'),
            'items.*.serial_numbers' => __('Serial'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => __('ต้องมีรายการสินค้าอย่างน้อย 1 บรรทัด'),
            'items.*.qty.gt' => __('จำนวนต้องมากกว่า 0'),
        ];
    }
}
