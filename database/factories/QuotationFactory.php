<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocType;
use App\Enums\PriceTier;
use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<Quotation> */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $issue = Carbon::now();

        return [
            // เลขที่แบบสุ่มพอสำหรับเทสต์ — ของจริงต้องออกผ่าน NumberSequenceService เท่านั้น
            'quote_no' => DocType::Quotation->value.'-'.$issue->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'revision' => 0,
            'customer_id' => Customer::factory(),
            'sales_user_id' => User::factory(),
            'created_by' => User::factory(),
            'issue_date' => $issue->toDateString(),
            'valid_until' => $issue->copy()->addDays(30)->toDateString(),
            'currency' => 'THB',
            'price_tier' => PriceTier::Standard,
            'vat_rate' => '7.00',
            'status' => QuotationStatus::Draft,
        ];
    }

    public function status(QuotationStatus $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function forSales(User $user): self
    {
        return $this->state(fn (): array => [
            'sales_user_id' => $user->id,
            'created_by' => $user->id,
        ]);
    }

    /**
     * ใบที่ส่งไปแล้วและเลยวันยืนราคา — ใช้ทดสอบ job ที่เปลี่ยนเป็นหมดอายุ
     */
    public function overdue(): self
    {
        return $this->state(fn (): array => [
            'status' => QuotationStatus::Sent,
            'issue_date' => Carbon::now()->subDays(45)->toDateString(),
            'valid_until' => Carbon::now()->subDay()->toDateString(),
            'sent_at' => Carbon::now()->subDays(45),
        ]);
    }
}
