<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class CustomerContactRequest extends FormRequest
{
    /**
     * ผู้ติดต่อเป็นทรัพยากรลูก — ใครแก้ลูกค้าได้ก็แก้ผู้ติดต่อได้
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'line_id' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_primary' => $this->boolean('is_primary')]);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => __('ชื่อผู้ติดต่อ'),
            'position' => __('ตำแหน่ง'),
            'phone' => __('โทรศัพท์'),
            'email' => __('อีเมล'),
            'line_id' => __('LINE ID'),
        ];
    }
}
