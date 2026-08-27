<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Http\Request;

/**
 * @mixin SalesOrder
 */
class SalesOrderResource extends ApiResource
{
    /** @return array<int, string> */
    protected function reportableAbilities(): array
    {
        return ['view', 'update', 'confirm', 'cancel', 'deliver', 'delete'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeCost = $request->user()?->can(PermissionName::ProductViewCost->value) ?? false;

        return [
            'id' => $this->id,
            'so_no' => $this->so_no,

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_editable' => $this->status->isEditable(),
                'can_deliver' => $this->status->canDeliver(),
            ],

            'quotation' => $this->whenLoaded('quotation', fn (): ?array => $this->quotation === null ? null : [
                'id' => $this->quotation->id,
                'quote_no' => $this->quotation->quote_no,
                'display_no' => $this->quotation->displayNo(),
            ]),

            'customer' => [
                'id' => $this->customer_id,
                'code' => $this->whenLoaded('customer', fn (): string => $this->customer->code),
                'name_th' => $this->whenLoaded('customer', fn (): string => $this->customer->name_th),
            ],
            'site' => $this->whenLoaded('site', fn (): ?array => $this->site === null ? null : [
                'id' => $this->site->id,
                'site_name' => $this->site->site_name,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn (): array => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),
            'sales_user' => $this->whenLoaded('salesUser', fn (): array => [
                'id' => $this->salesUser->id,
                'name' => $this->salesUser->name,
            ]),

            'customer_po_no' => $this->customer_po_no,
            'has_customer_po_file' => filled($this->customer_po_file),
            'order_date' => $this->order_date->toDateString(),
            'required_date' => $this->required_date?->toDateString(),
            'currency' => $this->currency,

            'totals' => [
                'subtotal' => self::decimal($this->subtotal),
                'discount_amount' => self::decimal($this->discount_amount),
                'after_discount' => self::decimal($this->after_discount),
                'vat_rate' => self::decimal($this->vat_rate),
                'vat_amount' => self::decimal($this->vat_amount),
                'grand_total' => self::decimal($this->grand_total),
            ],

            'margin' => $this->when($canSeeCost, fn (): array => [
                'cost_total' => self::decimal($this->cost_total),
                'amount' => $this->marginAmount(),
                'percent' => $this->marginPercent(),
            ]),

            // ── ความคืบหน้า — คำนวณจากบรรทัดจริง ต้อง eager load items มาก่อน ──
            'fulfilment' => $this->when(
                $this->relationLoaded('items'),
                fn (): array => [
                    'progress_percent' => $this->deliveryProgressPercent(),
                    'has_outstanding' => $this->hasOutstanding(),
                    'outstanding_qty' => $this->outstandingQty(),
                    // ของที่จองไม่ได้เพราะสต็อกไม่พอ (spec 4.4)
                    'shortage_qty' => $this->shortageQty(),
                    'has_shortage' => $this->hasShortage(),
                ],
            ),

            'terms' => [
                'payment' => $this->payment_terms,
                'delivery' => $this->delivery_terms,
            ],
            'note' => $this->note,

            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,

            'items' => $this->whenLoaded('items', fn (): array => $this->items
                ->map(fn (SalesOrderItem $item): array => [
                    'id' => $item->id,
                    'line_no' => $item->line_no,
                    'item_type' => [
                        'value' => $item->item_type->value,
                        'label' => $item->item_type->label(),
                    ],
                    'product_id' => $item->product_id,
                    'sku' => $item->sku_snapshot,
                    'description' => $item->description,
                    'uom' => $item->uom,
                    'unit_price' => self::decimal($item->unit_price),
                    'line_total' => self::decimal($item->line_total),
                    'qty_ordered' => self::decimal($item->qty_ordered, 3),
                    'qty_reserved' => self::decimal($item->qty_reserved, 3),
                    'qty_delivered' => self::decimal($item->qty_delivered, 3),
                    'qty_outstanding' => $item->outstandingQty(),
                    'qty_shortage' => $item->shortageQty(),
                    'is_stockable' => $item->isStockable(),
                    'cost' => $this->when($canSeeCost, fn (): string => self::decimal($item->cost_snapshot)),
                ])->all()),

            'deliveries' => DeliveryResource::collection($this->whenLoaded('deliveries')),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
