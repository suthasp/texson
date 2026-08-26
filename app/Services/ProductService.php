<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::create($this->attributes($data));
            $this->syncSuppliers($product, $data['suppliers'] ?? []);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product->update($this->attributes($data));
            $this->syncSuppliers($product, $data['suppliers'] ?? []);

            return $product->refresh();
        });
    }

    /**
     * แปลง input ของฟอร์มเป็นคอลัมน์จริง
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = collect($data)
            ->except(['suppliers', 'spec'])
            ->all();

        $attributes['spec'] = $this->normalizeSpec($data['spec'] ?? null);

        return $attributes;
    }

    /**
     * spec มาจากฟอร์มเป็นแถว key/value — เก็บลง DB เป็น object เดียว
     * แถวที่ไม่มี key ถือว่าผู้ใช้เพิ่มช่องว่างทิ้งไว้ ให้ตัดออก
     *
     * @param  array<int, array{key?: string|null, value?: string|null}>|null  $rows
     * @return array<string, string>|null
     */
    private function normalizeSpec(?array $rows): ?array
    {
        if ($rows === null) {
            return null;
        }

        $spec = [];

        foreach ($rows as $row) {
            $key = trim((string) ($row['key'] ?? ''));

            if ($key === '') {
                continue;
            }

            $spec[$key] = trim((string) ($row['value'] ?? ''));
        }

        return $spec === [] ? null : $spec;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncSuppliers(Product $product, array $rows): void
    {
        $pivot = [];

        foreach ($rows as $row) {
            $supplierId = (int) ($row['supplier_id'] ?? 0);

            if ($supplierId === 0) {
                continue;
            }

            $pivot[$supplierId] = [
                'supplier_sku' => $row['supplier_sku'] ?? null,
                'cost_price' => $row['cost_price'] ?? 0,
                'lead_time_days' => $row['lead_time_days'] ?? 0,
                'is_preferred' => (bool) ($row['is_preferred'] ?? false),
            ];
        }

        // ผู้ขายหลักต้องมีได้แค่รายเดียวต่อสินค้า
        $preferred = collect($pivot)->filter(fn (array $row): bool => $row['is_preferred'])->keys();

        if ($preferred->count() > 1) {
            foreach ($preferred->skip(1) as $supplierId) {
                $pivot[$supplierId]['is_preferred'] = false;
            }
        }

        $product->suppliers()->sync($pivot);
    }
}
