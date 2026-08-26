<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Uom;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $cost = $this->faker->randomFloat(2, 500, 200000);

        return [
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-####-??')),
            'name_th' => $this->faker->words(3, true),
            'name_en' => $this->faker->words(3, true),
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'model' => strtoupper($this->faker->bothify('MDL-###')),
            'part_number' => strtoupper($this->faker->bothify('PN-#####')),
            'uom' => $this->faker->randomElement(Uom::cases()),
            'cost_price' => $cost,
            'list_price' => round($cost * 1.35, 2),
            'dealer_price' => round($cost * 1.20, 2),
            'project_price' => round($cost * 1.15, 2),
            'is_serialized' => false,
            'track_lot' => false,
            'min_stock' => $this->faker->numberBetween(0, 10),
            'reorder_qty' => $this->faker->numberBetween(1, 20),
            'lead_time_days' => $this->faker->numberBetween(0, 90),
            'warranty_months' => $this->faker->randomElement([0, 12, 24, 36]),
            'spec' => null,
            'description' => null,
            'is_active' => true,
        ];
    }

    public function serialized(): self
    {
        return $this->state(fn (): array => ['is_serialized' => true, 'warranty_months' => 24]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
