<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerSite> */
class CustomerSiteFactory extends Factory
{
    protected $model = CustomerSite::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'site_code' => strtoupper($this->faker->unique()->bothify('SITE-##')),
            'site_name' => $this->faker->words(3, true),
            'address_line' => $this->faker->streetAddress(),
            'province' => $this->faker->randomElement(['กรุงเทพมหานคร', 'นนทบุรี', 'ชลบุรี']),
            'access_note' => null,
            'primary_contact_id' => null,
            'is_active' => true,
        ];
    }
}
