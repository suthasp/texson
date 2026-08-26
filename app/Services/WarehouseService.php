<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Warehouse
    {
        return DB::transaction(function () use ($data): Warehouse {
            $warehouse = Warehouse::create($data);
            $this->enforceSingleDefault($warehouse);

            return $warehouse;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        return DB::transaction(function () use ($warehouse, $data): Warehouse {
            $warehouse->update($data);
            $this->enforceSingleDefault($warehouse);

            return $warehouse->refresh();
        });
    }

    /**
     * คลังเริ่มต้นมีได้คลังเดียว — ตั้งคลังใหม่เป็น default แล้วคลังเดิมต้องถูกปลด
     */
    private function enforceSingleDefault(Warehouse $warehouse): void
    {
        if (! $warehouse->is_default) {
            return;
        }

        Warehouse::query()
            ->whereKeyNot($warehouse->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
