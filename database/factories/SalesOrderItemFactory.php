<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuotationItemType;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalesOrderItem> */
class SalesOrderItemFactory extends Factory
{
    protected $model = SalesOrderItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $qty = (string) $this->faker->numberBetween(1, 10);
        $price = (string) $this->faker->randomFloat(2, 100, 50000);

        return [
            'sales_order_id' => SalesOrder::factory(),
            'line_no' => 1,
            'product_id' => Product::factory(),
            'item_type' => QuotationItemType::Product,
            'sku_snapshot' => strtoupper($this->faker->bothify('SKU-####')),
            'description' => $this->faker->words(4, true),
            'uom' => 'ชิ้น',
            'unit_price' => $price,
            'cost_snapshot' => bcdiv($price, '1.3', 2),
            'line_total' => bcmul($qty, $price, 2),
            'qty_ordered' => $qty,
            'qty_reserved' => '0.000',
            'qty_delivered' => '0.000',
        ];
    }

    /**
     * บรรทัดค่าแรง — ส่งมอบงานได้แต่ไม่มีของให้ตัดสต็อก
     */
    public function labour(): self
    {
        return $this->state(fn (): array => [
            'item_type' => QuotationItemType::Labour,
            'product_id' => null,
            'sku_snapshot' => null,
            'cost_snapshot' => '0.00',
        ]);
    }
}
