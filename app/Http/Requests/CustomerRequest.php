<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PriceTier;
use App\Http\Requests\Concerns\AuthorizesResource;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    use AuthorizesResource;

    /** @return class-string<Customer> */
    protected function resourceClass(): string
    {
        return Customer::class;
    }

    protected function resourceRouteKey(): string
    {
        return 'customer';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('customers', 'code')->ignore($customerId)->withoutTrashed()],
            'name_th' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'digits:13'],
            'branch_code' => ['required', 'digits:5'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'subdistrict' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'digits:5'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'credit_term_days' => ['required', 'integer', 'min:0', 'max:365'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'price_tier' => ['required', Rule::enum(PriceTier::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'branch_code' => $this->filled('branch_code') ? $this->string('branch_code')->toString() : '00000',
        ]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => __('รหัสลูกค้า'),
            'name_th' => __('ชื่อลูกค้า (ไทย)'),
            'name_en' => __('ชื่อลูกค้า (อังกฤษ)'),
            'tax_id' => __('เลขประจำตัวผู้เสียภาษี'),
            'branch_code' => __('รหัสสาขา'),
            'address_line' => __('ที่อยู่'),
            'subdistrict' => __('ตำบล/แขวง'),
            'district' => __('อำเภอ/เขต'),
            'province' => __('จังหวัด'),
            'postcode' => __('รหัสไปรษณีย์'),
            'phone' => __('โทรศัพท์'),
            'email' => __('อีเมล'),
            'credit_term_days' => __('เครดิต (วัน)'),
            'payment_terms' => __('เงื่อนไขการชำระเงิน'),
            'price_tier' => __('ระดับราคา'),
            'notes' => __('หมายเหตุ'),
        ];
    }
}
