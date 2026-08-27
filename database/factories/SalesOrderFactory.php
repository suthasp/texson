<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocType;
use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<SalesOrder> */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $orderDate = Carbon::now();

        return [
            // เลขที่สุ่มพอสำหรับเทสต์ — ของจริงต้องออกผ่าน NumberSequenceService เท่านั้น
            'so_no' => DocType::SalesOrder->value.'-'.$orderDate->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'customer_id' => Customer::factory(),
            'warehouse_id' => Warehouse::factory(),
            'sales_user_id' => User::factory(),
            'created_by' => User::factory(),
            'order_date' => $orderDate->toDateString(),
            'currency' => 'THB',
            'vat_rate' => '7.00',
            'status' => SalesOrderStatus::Pending,
        ];
    }

    public function status(SalesOrderStatus $status): self
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

    public function inWarehouse(Warehouse $warehouse): self
    {
        return $this->state(fn (): array => ['warehouse_id' => $warehouse->id]);
    }
}
