<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * รายการสินค้าสำหรับตัวเลือกในฟอร์มเอกสารคลัง
 *
 * ฝังไปกับหน้าเลยแทนที่จะยิง API เพราะหน้าคลังถูกใช้บนแท็บเล็ตที่สัญญาณไม่แน่นอน
 * ถ้าจำนวน SKU โตจนหน้าหนัก ค่อยเปลี่ยนเป็นค้นหาแบบ async ใน Phase 3
 */
trait ProvidesProductOptions
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function productOptions(): Collection
    {
        return Product::query()
            ->active()
            ->orderBy('sku')
            ->get(['id', 'sku', 'name_th', 'model', 'uom', 'is_serialized', 'cost_price'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name_th,
                'model' => $product->model,
                'uom' => $product->uom->label(),
                'is_serialized' => $product->is_serialized,
                'cost_price' => (string) $product->cost_price,
            ]);
    }
}
