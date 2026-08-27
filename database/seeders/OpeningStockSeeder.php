<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StockAdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * ยอดสต็อกตั้งต้นสำหรับเครื่องพัฒนา
 *
 * ใช้ใบปรับปรุงเหตุผล "ยอดยกมา" แทนการ INSERT ตรง เพื่อให้ ledger กับยอดสรุปตรงกัน
 * ตั้งแต่แถวแรก — ถ้า seed ด้วยการเขียน stock_levels ตรง ๆ ยอดจะไม่มีที่มาใน ledger
 *
 * ข้ามสินค้าที่ติดตาม serial เพราะยอดตั้งต้นของสินค้าพวกนั้นต้องมี serial จริงประกอบ
 * ให้รับเข้าผ่านใบรับสินค้าแทน
 */
class OpeningStockSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('ข้ามการตั้งยอดสต็อกตัวอย่าง เพราะกำลังรันบน production');

            return;
        }

        $warehouse = Warehouse::query()->where('code', 'HQ')->first();
        $actor = User::query()->where('email', 'warehouse@texson.local')->first()
            ?? User::query()->first();

        if ($warehouse === null || $actor === null) {
            return;
        }

        // ใบปรับปรุงต้องรู้ว่าใครเป็นคนทำ — service อ่านจาก Auth
        Auth::login($actor);

        $alreadySeeded = StockAdjustment::query()
            ->where('reason', StockAdjustmentReason::Opening)
            ->exists();

        if ($alreadySeeded) {
            Auth::logout();

            return;
        }

        $products = Product::query()
            ->where('is_serialized', false)
            ->where('is_active', true)
            ->orderBy('sku')
            ->get();

        if ($products->isEmpty()) {
            Auth::logout();

            return;
        }

        $items = $products->map(function (Product $product): array {
            // ตั้งยอดให้บางรายการต่ำกว่าจุดสั่งซื้อ เพื่อให้หน้า Low Stock มีข้อมูลให้ดู
            $minStock = (float) $product->min_stock;
            $qty = $minStock > 0 && $product->id % 4 === 0
                ? max(0.0, floor($minStock / 2))
                : $minStock * 3 + 5;

            return [
                'product_id' => $product->id,
                'qty_counted' => number_format($qty, 3, '.', ''),
            ];
        })->all();

        $service = app(StockAdjustmentService::class);

        $adjustment = $service->createDraft([
            'warehouse_id' => $warehouse->id,
            'reason' => StockAdjustmentReason::Opening->value,
            'adjusted_at' => now(),
            'note' => 'ยอดยกมาตอนเริ่มใช้ระบบ (ข้อมูลตัวอย่างสำหรับเครื่องพัฒนา)',
            'items' => $items,
        ]);

        $service->post($adjustment);

        Auth::logout();

        $this->command?->info("ตั้งยอดสต็อกตั้งต้นที่คลัง {$warehouse->code} จำนวน {$products->count()} รายการแล้ว");
    }
}
