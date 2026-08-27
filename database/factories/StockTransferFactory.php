<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockDocumentStatus;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockTransfer> */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'transfer_no' => 'TR-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'transfer_date' => now()->toDateString(),
            'status' => StockDocumentStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
