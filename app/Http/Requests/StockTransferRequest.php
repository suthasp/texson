<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesResource;
use App\Models\StockTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferRequest extends FormRequest
{
    use AuthorizesResource;

    /** @return class-string<StockTransfer> */
    protected function resourceClass(): string
    {
        return StockTransfer::class;
    }

    protected function resourceRouteKey(): string
    {
        return 'stock_transfer';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'to_warehouse_id' => [
                'required', 'integer',
                Rule::exists('warehouses', 'id'),
                'different:from_warehouse_id',
            ],
            'transfer_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.lot_no' => ['nullable', 'string', 'max:50'],
            'items.*.serial_numbers' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'from_warehouse_id' => __('คลังต้นทาง'),
            'to_warehouse_id' => __('คลังปลายทาง'),
            'transfer_date' => __('วันที่โอน'),
            'items' => __('รายการสินค้า'),
            'items.*.product_id' => __('สินค้า'),
            'items.*.qty' => __('จำนวน'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to_warehouse_id.different' => __('คลังต้นทางและคลังปลายทางต้องไม่ใช่คลังเดียวกัน'),
            'items.required' => __('ต้องมีรายการสินค้าอย่างน้อย 1 บรรทัด'),
        ];
    }
}
