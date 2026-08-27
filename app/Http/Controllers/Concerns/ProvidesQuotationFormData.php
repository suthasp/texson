<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Enums\PermissionName;
use App\Enums\PriceTier;
use App\Enums\QuotationItemType;
use App\Enums\SettingKey;
use App\Models\Customer;
use App\Models\Product;
use App\Services\SettingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * ข้อมูลประกอบของหน้าสร้าง/แก้ไขใบเสนอราคา
 *
 * สินค้าถูกฝังไปกับหน้าพร้อมราคาทั้งสามระดับและยอดคงเหลือ เพื่อให้ Alpine
 * เปลี่ยนราคาตามระดับราคาของลูกค้าและโชว์สต็อกได้ทันทีโดยไม่ต้องยิง API ต่อบรรทัด (spec 7)
 */
trait ProvidesQuotationFormData
{
    /**
     * @return array<string, mixed>
     */
    protected function quotationFormData(): array
    {
        return [
            'customers' => Customer::query()
                ->active()
                ->with(['contacts:id,customer_id,name,is_primary', 'sites:id,customer_id,site_name'])
                ->orderBy('code')
                ->get(['id', 'code', 'name_th', 'price_tier', 'payment_terms', 'credit_term_days'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'code' => $customer->code,
                    'name' => $customer->name_th,
                    'price_tier' => $customer->price_tier->value,
                    'payment_terms' => $customer->payment_terms,
                    'contacts' => $customer->contacts
                        ->map(fn ($contact): array => ['id' => $contact->id, 'name' => $contact->name])
                        ->values()
                        ->all(),
                    'sites' => $customer->sites
                        ->map(fn ($site): array => ['id' => $site->id, 'name' => $site->site_name])
                        ->values()
                        ->all(),
                ]),

            'products' => $this->quotationProductOptions(),
            'priceTiers' => PriceTier::options(),
            'itemTypes' => QuotationItemType::options(),
            'canSeeCost' => Auth::user()?->can(PermissionName::ProductViewCost->value) ?? false,
            // เกณฑ์ margin ที่ทำให้ตัวเลขบนหน้าจอเปลี่ยนเป็นสีแดง (spec 4.5)
            'minMargin' => app(SettingService::class)->decimal(SettingKey::ApprovalMinMarginPercent),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function quotationProductOptions(): Collection
    {
        return Product::query()
            ->active()
            ->with('stockLevels:id,product_id,qty_on_hand,qty_reserved')
            ->orderBy('sku')
            ->get([
                'id', 'sku', 'name_th', 'name_en', 'model', 'uom',
                'cost_price', 'list_price', 'dealer_price', 'project_price',
                'lead_time_days',
            ])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name_th,
                'model' => $product->model,
                'uom' => $product->uom->label(),
                'lead_time_days' => $product->lead_time_days,
                'cost_price' => (string) $product->cost_price,
                'prices' => [
                    PriceTier::Standard->value => (string) $product->list_price,
                    PriceTier::Dealer->value => (string) $product->dealer_price,
                    PriceTier::Project->value => (string) $product->project_price,
                ],
                // ยอดพร้อมขายรวมทุกคลัง — แสดงข้างรายการตอนออกใบ (spec 7)
                'available' => $product->totalAvailable(),
            ]);
    }
}
