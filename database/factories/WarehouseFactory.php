<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('WH##')),
            'name' => $this->faker->words(2, true),
            'address' => $this->faker->address(),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): self
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
