<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PriceTier;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('CUS-####')),
            'name_th' => $this->faker->company(),
            'name_en' => $this->faker->company(),
            'tax_id' => $this->faker->numerify('#############'),
            'branch_code' => '00000',
            'address_line' => $this->faker->streetAddress(),
            'subdistrict' => $this->faker->word(),
            'district' => $this->faker->word(),
            'province' => $this->faker->randomElement(['กรุงเทพมหานคร', 'นนทบุรี', 'ชลบุรี', 'ปทุมธานี', 'สมุทรปราการ']),
            'postcode' => $this->faker->numerify('#####'),
            'phone' => $this->faker->numerify('0#-###-####'),
            'email' => $this->faker->unique()->companyEmail(),
            'credit_term_days' => $this->faker->randomElement([0, 15, 30, 45, 60]),
            'payment_terms' => null,
            'price_tier' => PriceTier::Standard,
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function tier(PriceTier $tier): self
    {
        return $this->state(fn (): array => ['price_tier' => $tier]);
    }
}
