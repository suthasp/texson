<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocType;
use App\Enums\StockDocumentStatus;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Delivery> */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $date = Carbon::now();

        return [
            'delivery_no' => DocType::DeliveryNote->value.'-'.$date->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'sales_order_id' => SalesOrder::factory(),
            'warehouse_id' => Warehouse::factory(),
            'delivery_date' => $date->toDateString(),
            'status' => StockDocumentStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function status(StockDocumentStatus $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
