<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Models\Quotation;
use App\Support\BahtText;
use Illuminate\Http\Request;

/**
 * @mixin Quotation
 */
class QuotationResource extends ApiResource
{
    /** @return array<int, string> */
    protected function reportableAbilities(): array
    {
        return ['view', 'update', 'submit', 'approve', 'send', 'decide', 'revise', 'cancel', 'delete'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeCost = $request->user()?->can(PermissionName::ProductViewCost->value) ?? false;

        return [
            'id' => $this->id,
            'quote_no' => $this->quote_no,
            'revision' => $this->revision,
            'display_no' => $this->displayNo(),
            'parent_quotation_id' => $this->parent_quotation_id,

            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'is_editable' => $this->status->isEditable(),
                'can_be_revised' => $this->status->canBeRevised(),
            ],
            // ใบที่ถูกแทนที่ยังคงสถานะเดิมไว้ ต้องดูฟิลด์นี้แยก (ADR-002)
            'superseded_at' => $this->superseded_at?->toIso8601String(),

            'customer' => [
                'id' => $this->customer_id,
                'code' => $this->whenLoaded('customer', fn (): string => $this->customer->code),
                'name_th' => $this->whenLoaded('customer', fn (): string => $this->customer->name_th),
            ],
            'contact' => $this->whenLoaded('contact', fn (): ?array => $this->contact === null ? null : [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'email' => $this->contact->email,
            ]),
            'site' => $this->whenLoaded('site', fn (): ?array => $this->site === null ? null : [
                'id' => $this->site->id,
                'site_name' => $this->site->site_name,
            ]),
            'sales_user' => $this->whenLoaded('salesUser', fn (): array => [
                'id' => $this->salesUser->id,
                'name' => $this->salesUser->name,
            ]),

            'issue_date' => $this->issue_date->toDateString(),
            'valid_until' => $this->valid_until->toDateString(),
            'days_until_expiry' => $this->daysUntilExpiry(),
            'currency' => $this->currency,
            'price_tier' => [
                'value' => $this->price_tier->value,
                'label' => $this->price_tier->label(),
            ],

            'totals' => [
                'subtotal' => self::decimal($this->subtotal),
                'discount_amount' => self::decimal($this->discount_amount),
                'after_discount' => self::decimal($this->after_discount),
                'vat_rate' => self::decimal($this->vat_rate),
                'vat_amount' => self::decimal($this->vat_amount),
                'grand_total' => self::decimal($this->grand_total),
                'grand_total_in_words_th' => BahtText::convert((string) $this->grand_total),
            ],

            // หัก ณ ที่จ่าย 3% เป็นข้อมูลประกอบ ไม่ได้หักจาก grand_total (spec 4.2)
            'withholding' => $this->when(
                $this->relationLoaded('items'),
                fn (): array => $this->withholding(),
            ),

            'margin' => $this->when($canSeeCost, fn (): array => [
                'cost_total' => self::decimal($this->cost_total),
                'amount' => $this->marginAmount(),
                'percent' => $this->marginPercent(),
            ]),

            'terms' => [
                'payment' => $this->payment_terms,
                'delivery' => $this->delivery_terms,
                'lead_time' => $this->lead_time_note,
                'conditions' => $this->terms_and_conditions,
            ],
            'customer_note' => $this->customer_note,
            // บันทึกภายในไม่ควรหลุดออกไปพร้อมข้อมูลที่อาจถูกส่งต่อให้ลูกค้า
            'internal_note' => $this->when($this->status->isEditable() || $canSeeCost, $this->internal_note),

            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => $this->whenLoaded('approver', fn (): ?string => $this->approver?->name),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'lost_reason' => $this->lost_reason,

            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
