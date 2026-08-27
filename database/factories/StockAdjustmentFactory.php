<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockDocumentStatus;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockAdjustment> */
class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'adjust_no' => 'ADJ-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'warehouse_id' => Warehouse::factory(),
            'reason' => StockAdjustmentReason::StockCount,
            'adjusted_at' => now(),
            'status' => StockDocumentStatus::Draft,
            'user_id' => User::factory(),
        ];
    }
}
