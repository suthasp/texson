<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliveryItem> */
class DeliveryItemFactory extends Factory
{
    protected $model = DeliveryItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'sales_order_item_id' => SalesOrderItem::factory(),
            'qty' => '1.000',
        ];
    }
}
