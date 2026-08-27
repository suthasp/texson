<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;

/**
 * @mixin Customer
 */
class CustomerResource extends ApiResource
{
    /** @return array<int, string> */
    protected function reportableAbilities(): array
    {
        return ['view', 'update', 'delete', 'export'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name_th' => $this->name_th,
            'name_en' => $this->name_en,
            'tax_id' => $this->tax_id,
            'branch_code' => $this->branch_code,
            'branch_label' => $this->branchLabel(),
            'address' => [
                'line' => $this->address_line,
                'subdistrict' => $this->subdistrict,
                'district' => $this->district,
                'province' => $this->province,
                'postcode' => $this->postcode,
                'full' => $this->fullAddress(),
            ],
            'phone' => $this->phone,
            'email' => $this->email,
            'credit_term_days' => $this->credit_term_days,
            'payment_terms' => $this->payment_terms,
            'price_tier' => [
                'value' => $this->price_tier->value,
                'label' => $this->price_tier->label(),
            ],
            'is_active' => $this->is_active,

            /*
             * ผู้ติดต่อเป็นข้อมูลส่วนบุคคลตาม PDPA (spec 8) จึงส่งเฉพาะตอนขอรายละเอียด
             * รายเดียว (eager load มา) ไม่ติดไปกับ list ทั้งหน้าโดยอัตโนมัติ
             */
            'contacts' => $this->whenLoaded('contacts', fn (): array => $this->contacts
                ->map(fn ($contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'position' => $contact->position,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'is_primary' => $contact->is_primary,
                ])->all()),

            'sites' => $this->whenLoaded('sites', fn (): array => $this->sites
                ->map(fn ($site): array => [
                    'id' => $site->id,
                    'site_code' => $site->site_code,
                    'site_name' => $site->site_name,
                    'province' => $site->province,
                ])->all()),
        ];
    }
}
