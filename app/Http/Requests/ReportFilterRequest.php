<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\QuotationStatus;
use App\Enums\StockMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * ตัวกรองร่วมของหน้ารายงานและไฟล์ส่งออกทุกชุด
 *
 * ไม่ระบุช่วงวันที่มา = เดือนปัจจุบันถึงวันนี้ — ผู้ใช้กดเข้าหน้ารายงานแล้วเห็นตัวเลขทันที
 */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionName::ReportViewAny->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', Rule::enum(QuotationStatus::class)],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
            'low_stock' => ['nullable', 'boolean'],
        ];
    }

    public function from(): Carbon
    {
        return Carbon::parse($this->input('from') ?? Carbon::now()->startOfMonth()->toDateString())->startOfDay();
    }

    public function to(): Carbon
    {
        return Carbon::parse($this->input('to') ?? Carbon::now()->toDateString())->endOfDay();
    }

    public function warehouseId(): ?int
    {
        return $this->filled('warehouse_id') ? $this->integer('warehouse_id') : null;
    }

    public function productId(): ?int
    {
        return $this->filled('product_id') ? $this->integer('product_id') : null;
    }

    public function quotationStatus(): ?string
    {
        return $this->filled('status') ? $this->string('status')->toString() : null;
    }

    public function movementType(): ?string
    {
        return $this->filled('type') ? $this->string('type')->toString() : null;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'from' => __('ตั้งแต่วันที่'),
            'to' => __('ถึงวันที่'),
            'status' => __('สถานะ'),
            'warehouse_id' => __('คลัง'),
            'product_id' => __('สินค้า'),
            'type' => __('ประเภทการเคลื่อนไหว'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => __('วันสิ้นสุดต้องไม่ก่อนวันเริ่มต้น'),
        ];
    }
}
