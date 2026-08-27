<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StockAdjustmentReason;
use App\Http\Requests\Concerns\AuthorizesResource;
use App\Models\StockAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    use AuthorizesResource;

    /** @return class-string<StockAdjustment> */
    protected function resourceClass(): string
    {
        return StockAdjustment::class;
    }

    protected function resourceRouteKey(): string
    {
        return 'stock_adjustment';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'reason' => ['required', Rule::enum(StockAdjustmentReason::class)],
            'adjusted_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            // นับได้ 0 ถือว่าปกติ (ของหมด) จึงใช้ min:0 ไม่ใช่ gt:0
            'items.*.qty_counted' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.lot_no' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'warehouse_id' => __('คลัง'),
            'reason' => __('เหตุผล'),
            'adjusted_at' => __('วันที่ปรับปรุง'),
            'items' => __('รายการสินค้า'),
            'items.*.product_id' => __('สินค้า'),
            'items.*.qty_counted' => __('จำนวนที่นับได้'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => __('ต้องมีรายการสินค้าอย่างน้อย 1 บรรทัด'),
        ];
    }
}
