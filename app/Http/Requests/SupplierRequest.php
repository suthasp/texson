<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesResource;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    use AuthorizesResource;

    /** @return class-string<Supplier> */
    protected function resourceClass(): string
    {
        return Supplier::class;
    }

    protected function resourceRouteKey(): string
    {
        return 'supplier';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('suppliers', 'code')->ignore($supplierId)],
            'name' => ['required', 'string', 'max:255'],
            'tax_id' => ['nullable', 'digits:13'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => __('รหัสผู้ขาย'),
            'name' => __('ชื่อผู้ขาย'),
            'tax_id' => __('เลขประจำตัวผู้เสียภาษี'),
            'contact_name' => __('ผู้ติดต่อ'),
            'phone' => __('โทรศัพท์'),
            'email' => __('อีเมล'),
            'lead_time_days' => __('ระยะเวลาส่งของ (วัน)'),
            'notes' => __('หมายเหตุ'),
        ];
    }
}
