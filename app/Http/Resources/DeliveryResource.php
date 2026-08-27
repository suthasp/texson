<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use Illuminate\Http\Request;

/**
 * @mixin Delivery
 */
class DeliveryResource extends ApiResource
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
            'delivery_no' => $this->delivery_no,
            'sales_order_id' => $this->sales_order_id,
            'sales_order_no' => $this->whenLoaded('salesOrder', fn (): string => $this->salesOrder->so_no),

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_editable' => $this->status->isEditable(),
            ],

            'warehouse' => $this->whenLoaded('warehouse', fn (): array => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),

            'delivery_date' => $this->delivery_date->toDateString(),
            'receiver_name' => $this->receiver_name,
            'vehicle_note' => $this->vehicle_note,
            'note' => $this->note,

            'posted_at' => $this->posted_at?->toIso8601String(),
            'posted_by' => $this->whenLoaded('poster', fn (): ?string => $this->poster?->name),

            'items' => $this->whenLoaded('items', fn (): array => $this->items
                ->map(fn (DeliveryItem $item): array => [
                    'id' => $item->id,
                    'sales_order_item_id' => $item->sales_order_item_id,
                    'product_id' => $item->product_id,
                    'sku' => $item->salesOrderItem?->sku_snapshot,
                    'description' => $item->salesOrderItem?->description,
                    'qty' => self::decimal($item->qty, 3),
                    'lot_no' => $item->lot_no,
                    'serial_numbers' => $item->serials(),
                ])->all()),

            // รายการที่เขียนลง ledger จริง — พิสูจน์ว่าตัดสต็อกไปเท่าไร
            'movements' => StockMovementResource::collection($this->whenLoaded('movements')),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
