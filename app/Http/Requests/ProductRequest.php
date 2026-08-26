<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Uom;
use App\Http\Requests\Concerns\AuthorizesResource;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    use AuthorizesResource;

    /** @return class-string<Product> */
    protected function resourceClass(): string
    {
        return Product::class;
    }

    protected function resourceRouteKey(): string
    {
        return 'product';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($productId)->withoutTrashed()],
            'name_th' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'model' => ['nullable', 'string', 'max:100'],
            'part_number' => ['nullable', 'string', 'max:100'],
            'uom' => ['required', Rule::enum(Uom::class)],

            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'list_price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'dealer_price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'project_price' => ['required', 'numeric', 'min:0', 'max:9999999999999'],

            'is_serialized' => ['boolean'],
            'track_lot' => ['boolean'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'reorder_qty' => ['required', 'numeric', 'min:0'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'warranty_months' => ['required', 'integer', 'min:0', 'max:600'],

            // spec เป็น key/value ที่ผู้ใช้เพิ่มเองได้จากหน้าจอ เช่น kva = 10
            'spec' => ['nullable', 'array', 'max:30'],
            'spec.*.key' => ['nullable', 'string', 'max:50'],
            'spec.*.value' => ['nullable', 'string', 'max:255'],

            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],

            'suppliers' => ['nullable', 'array', 'max:20'],
            'suppliers.*.supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'suppliers.*.supplier_sku' => ['nullable', 'string', 'max:100'],
            'suppliers.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'suppliers.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'suppliers.*.is_preferred' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_serialized' => $this->boolean('is_serialized'),
            'track_lot' => $this->boolean('track_lot'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'sku' => __('รหัสสินค้า (SKU)'),
            'name_th' => __('ชื่อสินค้า (ไทย)'),
            'name_en' => __('ชื่อสินค้า (อังกฤษ)'),
            'category_id' => __('หมวดหมู่'),
            'brand_id' => __('ยี่ห้อ'),
            'model' => __('รุ่น'),
            'part_number' => __('Part Number'),
            'uom' => __('หน่วยนับ'),
            'cost_price' => __('ราคาทุน'),
            'list_price' => __('ราคามาตรฐาน'),
            'dealer_price' => __('ราคาตัวแทนจำหน่าย'),
            'project_price' => __('ราคาโครงการ'),
            'min_stock' => __('สต็อกขั้นต่ำ'),
            'reorder_qty' => __('จำนวนสั่งซื้อซ้ำ'),
            'lead_time_days' => __('ระยะเวลาสั่งของ (วัน)'),
            'warranty_months' => __('ระยะประกัน (เดือน)'),
            'description' => __('รายละเอียด'),
        ];
    }
}
