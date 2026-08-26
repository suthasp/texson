<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesResource;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    use AuthorizesResource;

    /** @return class-string<Category> */
    protected function resourceClass(): string
    {
        return Category::class;
    }

    protected function resourceRouteKey(): string
    {
        return 'category';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name_th' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
                // กันหมวดชี้เป็นแม่ของตัวเอง
                Rule::notIn(array_filter([$categoryId])),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name_th' => __('ชื่อหมวด (ไทย)'),
            'name_en' => __('ชื่อหมวด (อังกฤษ)'),
            'parent_id' => __('หมวดแม่'),
            'sort_order' => __('ลำดับ'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'parent_id.not_in' => __('หมวดหมู่ต้องไม่เป็นหมวดแม่ของตัวเอง'),
        ];
    }
}
