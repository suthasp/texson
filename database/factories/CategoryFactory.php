<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name_th' => $this->faker->unique()->word(),
            'name_en' => $this->faker->unique()->word(),
            'parent_id' => null,
            'sort_order' => $this->faker->numberBetween(0, 50),
        ];
    }

    public function childOf(Category $parent): self
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }
}
