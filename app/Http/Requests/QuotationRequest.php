<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PriceTier;
use App\Enums\QuotationItemType;
use App\Http\Requests\Concerns\AuthorizesResource;
use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuotationRequest extends FormRequest
{
    use AuthorizesResource;

    /** @return class-string<Quotation> */
    protected function resourceClass(): string
    {
        return Quotation::class;
    }

    protected function resourceRouteKey(): string
    {
        return 'quotation';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $customerId = $this->integer('customer_id');

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],

            // ผู้ติดต่อและหน้างานต้องเป็นของลูกค้ารายนี้เท่านั้น ไม่ใช่ id ใดก็ได้ที่ยิงมา
            'customer_contact_id' => [
                'nullable', 'integer',
                Rule::exists('customer_contacts', 'id')->where('customer_id', $customerId),
            ],
            'customer_site_id' => [
                'nullable', 'integer',
                Rule::exists('customer_sites', 'id')->where('customer_id', $customerId),
            ],

            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'price_tier' => ['required', Rule::enum(PriceTier::class)],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],

            'payment_terms' => ['nullable', 'string', 'max:255'],
            'delivery_terms' => ['nullable', 'string', 'max:255'],
            'lead_time_note' => ['nullable', 'string', 'max:255'],
            'terms_and_conditions' => ['nullable', 'string', 'max:5000'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.item_type' => ['required', Rule::enum(QuotationItemType::class)],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.description' => ['required', 'string', 'max:2000'],
            'items.*.uom' => ['nullable', 'string', 'max:20'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'items.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ];
    }

    /**
     * กฎที่ต้องดูหลายฟิลด์พร้อมกัน
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                $type = QuotationItemType::tryFrom((string) ($item['item_type'] ?? ''));

                if ($type === null) {
                    continue;
                }

                // บรรทัดข้อความไม่ต้องมีจำนวน แต่บรรทัดที่มีมูลค่าต้องมากกว่าศูนย์
                if ($type->isMonetary() && (float) ($item['qty'] ?? 0) <= 0) {
                    $validator->errors()->add("items.{$index}.qty", __('จำนวนต้องมากกว่า 0'));
                }

                if ($type->requiresProduct() && blank($item['product_id'] ?? null)) {
                    $validator->errors()->add("items.{$index}.product_id", __('บรรทัดชนิดสินค้าต้องเลือกสินค้าจากระบบ'));
                }
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'customer_id' => __('ลูกค้า'),
            'customer_contact_id' => __('ผู้ติดต่อ'),
            'customer_site_id' => __('หน้างาน'),
            'issue_date' => __('วันที่ออกใบ'),
            'valid_until' => __('ยืนราคาถึง'),
            'price_tier' => __('ระดับราคา'),
            'vat_rate' => __('อัตรา VAT'),
            'discount_amount' => __('ส่วนลดท้ายบิล'),
            'items' => __('รายการ'),
            'items.*.description' => __('รายละเอียด'),
            'items.*.qty' => __('จำนวน'),
            'items.*.unit_price' => __('ราคาต่อหน่วย'),
            'items.*.discount_percent' => __('ส่วนลด (%)'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => __('ต้องมีรายการอย่างน้อย 1 บรรทัด'),
            'valid_until.after_or_equal' => __('วันยืนราคาต้องไม่ก่อนวันที่ออกใบ'),
        ];
    }
}
