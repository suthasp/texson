<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerContact> */
class CustomerContactFactory extends Factory
{
    protected $model = CustomerContact::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => $this->faker->name(),
            'position' => $this->faker->jobTitle(),
            'phone' => $this->faker->numerify('08########'),
            'email' => $this->faker->unique()->safeEmail(),
            'line_id' => null,
            'is_primary' => false,
        ];
    }

    public function primary(): self
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }
}
