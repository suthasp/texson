<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockAdjustment;
use Illuminate\Http\Request;

/**
 * @mixin StockAdjustment
 */
class StockAdjustmentResource extends ApiResource
{
    /** @return array<int, string> */
    protected function reportableAbilities(): array
    {
        return ['view', 'update', 'post', 'delete'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adjust_no' => $this->adjust_no,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'reason' => [
                'value' => $this->reason->value,
                'label' => $this->reason->label(),
            ],
            'warehouse' => $this->whenLoaded('warehouse', fn (): array => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),
            'adjusted_at' => $this->adjusted_at->toIso8601String(),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'note' => $this->note,

            'items' => $this->whenLoaded('items', fn (): array => $this->items
                ->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'sku' => $item->product?->sku,
                    // qty_system ถูกอ่านใหม่ตอน post จึงเป็นยอดจริง ณ เวลาที่ตัดสต็อก
                    'qty_system' => self::decimal($item->qty_system, 3),
                    'qty_counted' => self::decimal($item->qty_counted, 3),
                    'qty_diff' => self::decimal($item->qty_diff, 3),
                    'lot_no' => $item->lot_no,
                ])->all()),

            'movements' => StockMovementResource::collection($this->whenLoaded('movements')),
        ];
    }
}
