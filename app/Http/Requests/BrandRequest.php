<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesResource;
use App\Models\Brand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    use AuthorizesResource;

    /** @return class-string<Brand> */
    protected function resourceClass(): string
    {
        return Brand::class;
    }

    protected function resourceRouteKey(): string
    {
        return 'brand';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $brandId = $this->route('brand')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brandId)],
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
        return ['name' => __('ชื่อยี่ห้อ')];
    }
}
