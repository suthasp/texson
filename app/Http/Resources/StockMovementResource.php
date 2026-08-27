<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;

/**
 * รายการใน ledger — append-only ไม่มี endpoint ให้แก้หรือลบโดยเจตนา (spec 3.2)
 *
 * @mixin StockMovement
 */
class StockMovementResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'moved_at' => $this->moved_at->toIso8601String(),
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'product' => $this->whenLoaded('product', fn (): array => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name_th' => $this->product->name_th,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn (): array => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
            ]),
            'qty' => self::decimal($this->qty, 3),
            'balance_after' => self::decimal($this->balance_after, 3),
            'lot_no' => $this->lot_no,
            'note' => $this->note,
            // เอกสารต้นทาง — polymorphic เพื่อรองรับ WorkOrder ใน Phase 2 ของ roadmap
            'reference' => $this->ref_type === null ? null : [
                'type' => class_basename($this->ref_type),
                'id' => $this->ref_id,
            ],
            'user' => $this->whenLoaded('user', fn (): ?string => $this->user?->name),
        ];
    }
}
