<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SerialStatus;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SerialNumber> */
class SerialNumberFactory extends Factory
{
    protected $model = SerialNumber::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->serialized(),
            'serial_no' => strtoupper($this->faker->unique()->bothify('SN########')),
            'warehouse_id' => Warehouse::factory(),
            'status' => SerialStatus::InStock,
            'warranty_start' => null,
            'warranty_end' => null,
        ];
    }

    public function status(SerialStatus $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
