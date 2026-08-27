<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', Setting::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'in:'.implode(',', array_keys(SettingKey::groups()))],
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:5000'],

            // โลโก้และลายเซ็นสำหรับหัว/ท้ายใบเสนอราคา — ตรวจ mime จริงไม่ใช่แค่นามสกุล (spec 8)
            'logo' => ['nullable', 'file', 'mimetypes:image/png,image/jpeg', 'max:2048'],
            'signature' => ['nullable', 'file', 'mimetypes:image/png,image/jpeg', 'max:2048'],
        ];
    }

    /**
     * เก็บเฉพาะคีย์ที่รู้จักจริง — ค่าที่ปลอมมาจากฟอร์มถูกทิ้งตั้งแต่ชั้นนี้
     *
     * @return array<string, string|null>
     */
    public function knownValues(): array
    {
        $group = (string) $this->input('group');
        $allowed = array_map(
            static fn (SettingKey $key): string => $key->value,
            SettingKey::inGroup($group),
        );

        return array_filter(
            (array) $this->input('values', []),
            static fn (string $key): bool => in_array($key, $allowed, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'logo.mimetypes' => __('โลโก้ต้องเป็นไฟล์ PNG หรือ JPEG'),
            'signature.mimetypes' => __('ลายเซ็นต้องเป็นไฟล์ PNG หรือ JPEG'),
        ];
    }
}
