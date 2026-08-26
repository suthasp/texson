<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('SUP-####')),
            'name' => $this->faker->company(),
            'tax_id' => $this->faker->numerify('#############'),
            'contact_name' => $this->faker->name(),
            'phone' => $this->faker->numerify('0#-###-####'),
            'email' => $this->faker->unique()->companyEmail(),
            'lead_time_days' => $this->faker->numberBetween(3, 60),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
