<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerSiteRequest extends FormRequest
{
    /**
     * หน้างานเป็นทรัพยากรลูก — ใครแก้ลูกค้าได้ก็แก้หน้างานได้
     *
     * ต้องตรวจที่นี่ ไม่ใช่ใน Controller เพราะ authorize() ทำงานก่อน validation
     */
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && (bool) $this->user()?->can('update', $customer);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $customerId = $this->route('customer')->id;
        $siteId = $this->route('site')?->id;

        return [
            'site_code' => [
                'required', 'string', 'max:30',
                Rule::unique('customer_sites', 'site_code')
                    ->where('customer_id', $customerId)
                    ->ignore($siteId),
            ],
            'site_name' => ['required', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'access_note' => ['nullable', 'string', 'max:2000'],
            'primary_contact_id' => [
                'nullable', 'integer',
                Rule::exists('customer_contacts', 'id')->where('customer_id', $customerId),
            ],
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
            'site_code' => __('รหัสหน้างาน'),
            'site_name' => __('ชื่อหน้างาน'),
            'address_line' => __('ที่อยู่'),
            'province' => __('จังหวัด'),
            'access_note' => __('หมายเหตุการเข้าพื้นที่'),
            'primary_contact_id' => __('ผู้ติดต่อหลัก'),
        ];
    }
}
