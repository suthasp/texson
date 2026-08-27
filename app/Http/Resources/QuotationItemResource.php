<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Models\QuotationItem;
use Illuminate\Http\Request;

/**
 * @mixin QuotationItem
 */
class QuotationItemResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeCost = $request->user()?->can(PermissionName::ProductViewCost->value) ?? false;

        return [
            'id' => $this->id,
            'line_no' => $this->line_no,
            'item_type' => [
                'value' => $this->item_type->value,
                'label' => $this->item_type->label(),
            ],
            'product_id' => $this->product_id,

            // ค่าที่ snapshot ไว้ตอนออกใบ — ไม่ใช่ค่าปัจจุบันของสินค้า (spec 3.3)
            'sku' => $this->sku_snapshot,
            'description' => $this->description,
            'uom' => $this->uom,
            'qty' => self::decimal($this->qty, 3),
            'unit_price' => self::decimal($this->unit_price),
            'discount_percent' => self::decimal($this->discount_percent),
            'discount_amount' => self::decimal($this->discount_amount),
            'line_total' => self::decimal($this->line_total),
            'lead_time_days' => $this->lead_time_days,

            'cost' => $this->when($canSeeCost, fn (): array => [
                'unit' => self::decimal($this->cost_snapshot),
                'total' => $this->costTotal(),
                'margin_amount' => $this->marginAmount(),
                'margin_percent' => $this->marginPercent(),
            ]),
        ];
    }
}
