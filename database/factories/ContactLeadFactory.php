<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactLeadStatus;
use App\Enums\ServiceInterest;
use App\Models\ContactLead;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactLead> */
class ContactLeadFactory extends Factory
{
    protected $model = ContactLead::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company' => fake()->company(),
            'contact' => fake()->safeEmail(),
            'service_interest' => fake()->randomElement(ServiceInterest::cases()),
            'message' => fake()->sentence(),
            'status' => ContactLeadStatus::New,
            'locale' => 'th',
            'ip' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (test)',
        ];
    }

    public function status(ContactLeadStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
