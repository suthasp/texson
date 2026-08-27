<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * @mixin Product
 */
class ProductResource extends ApiResource
{
    /** @return array<int, string> */
    protected function reportableAbilities(): array
    {
        return ['view', 'update', 'delete'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // ราคาทุนเป็นข้อมูลอ่อนไหว — role ที่ไม่ได้ทำงานขายต้องไม่เห็นแม้ผ่าน API (ADR-012)
        $canSeeCost = $request->user()?->can(PermissionName::ProductViewCost->value) ?? false;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name_th' => $this->name_th,
            'name_en' => $this->name_en,
            'model' => $this->model,
            'part_number' => $this->part_number,
            'uom' => [
                'value' => $this->uom->value,
                'label' => $this->uom->label(),
            ],
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'name_th' => $this->category->name_th,
            ]),
            'brand' => $this->whenLoaded('brand', fn (): ?array => $this->brand === null ? null : [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ]),

            'prices' => [
                'list' => self::decimal($this->list_price),
                'dealer' => self::decimal($this->dealer_price),
                'project' => self::decimal($this->project_price),
                'cost' => $this->when($canSeeCost, fn (): string => self::decimal($this->cost_price)),
            ],

            'is_serialized' => $this->is_serialized,
            'track_lot' => $this->track_lot,
            'min_stock' => self::decimal($this->min_stock, 3),
            'reorder_qty' => self::decimal($this->reorder_qty, 3),
            'lead_time_days' => $this->lead_time_days,
            'warranty_months' => $this->warranty_months,
            'spec' => $this->spec,
            'is_active' => $this->is_active,

            // มีเฉพาะตอน eager load stockLevels มาแล้ว — กัน N+1 ในหน้า list
            'stock' => $this->whenLoaded('stockLevels', fn (): array => [
                'on_hand' => $this->totalOnHand(),
                'available' => $this->totalAvailable(),
                'is_low' => bccomp($this->totalAvailable(), (string) $this->min_stock, 3) < 0,
            ]),
        ];
    }
}
