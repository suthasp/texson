<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockDocumentStatus;
use App\Models\GoodsReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GoodsReceipt> */
class GoodsReceiptFactory extends Factory
{
    protected $model = GoodsReceipt::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'receipt_no' => 'GR-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'reference_no' => $this->faker->bothify('INV-####'),
            'received_date' => now()->toDateString(),
            'status' => StockDocumentStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
