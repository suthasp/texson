<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuotationItemType;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuotationItem> */
class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $qty = (string) $this->faker->numberBetween(1, 10);
        $price = (string) $this->faker->randomFloat(2, 100, 50000);

        return [
            'quotation_id' => Quotation::factory(),
            'line_no' => 1,
            'item_type' => QuotationItemType::Product,
            'sku_snapshot' => strtoupper($this->faker->bothify('SKU-####')),
            'description' => $this->faker->words(4, true),
            'uom' => 'ชิ้น',
            'qty' => $qty,
            'unit_price' => $price,
            'cost_snapshot' => bcdiv($price, '1.3', 2),
            'discount_percent' => '0.00',
            'discount_amount' => '0.00',
            'line_total' => bcmul($qty, $price, 2),
            'lead_time_days' => null,
        ];
    }

    public function service(): self
    {
        return $this->state(fn (): array => [
            'item_type' => QuotationItemType::Service,
            'product_id' => null,
            'sku_snapshot' => null,
            'cost_snapshot' => '0.00',
        ]);
    }
}
