<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ทางเดียวที่สต็อกจะเปลี่ยนได้ (spec 4.4)
 *
 * หลักการ
 * 1. ทุกการเปลี่ยนแปลงอยู่ใน DB::transaction และล็อกแถว stock_levels ด้วย lockForUpdate
 *    ก่อนอ่านยอด เพื่อกันสองคนอ่านยอดเดียวกันแล้วเขียนทับกัน
 * 2. ทุกการเปลี่ยนแปลงเขียน ledger เสมอ พร้อม balance_after ที่คำนวณจากยอดที่ล็อกไว้
 *    ทำให้ SUM(qty) ของ ledger เท่ากับ qty_on_hand ตลอดเวลา
 * 3. คำนวณจำนวนด้วย bcmath scale 3 ไม่ใช่ float เพื่อไม่ให้ปัดเศษเพี้ยนสะสม
 */
class StockService
{
    /** ทศนิยมของจำนวน ตรงกับ decimal(15,3) ใน schema */
    private const SCALE = 3;

    /**
     * รับสินค้าเข้าคลัง
     *
     * @param  array<string, mixed>  $options
     */
    public function receive(
        Product $product,
        Warehouse $warehouse,
        string $qty,
        array $options = [],
    ): StockMovement {
        return $this->apply($product, $warehouse, StockMovementType::Receive, $qty, $options);
    }

    /**
     * ตัดสต็อกออก เช่น ตอน post ใบส่งของ
     *
     * @param  array<string, mixed>  $options
     *
     * @throws InsufficientStockException เมื่อของไม่พอ — สต็อกกายภาพติดลบไม่ได้
     */
    public function issue(
        Product $product,
        Warehouse $warehouse,
        string $qty,
        array $options = [],
    ): StockMovement {
        return $this->apply($product, $warehouse, StockMovementType::Issue, $qty, $options);
    }

    /**
     * รับคืนเข้าคลัง
     *
     * @param  array<string, mixed>  $options
     */
    public function returnIn(
        Product $product,
        Warehouse $warehouse,
        string $qty,
        array $options = [],
    ): StockMovement {
        return $this->apply($product, $warehouse, StockMovementType::ReturnIn, $qty, $options);
    }

    /**
     * ปรับยอดตามผลต่าง — บวกคือปรับเพิ่ม ลบคือปรับลด
     *
     * @param  array<string, mixed>  $options
     */
    public function adjustBy(
        Product $product,
        Warehouse $warehouse,
        string $diff,
        array $options = [],
    ): ?StockMovement {
        $comparison = bccomp($diff, '0', self::SCALE);

        if ($comparison === 0) {
            return null;
        }

        $type = $comparison > 0 ? StockMovementType::AdjustIn : StockMovementType::AdjustOut;

        return $this->apply($product, $warehouse, $type, ltrim($diff, '-'), $options);
    }

    /**
     * โอนระหว่างคลัง — เขียน ledger สองรายการที่จับคู่กัน
     *
     * @param  array<string, mixed>  $options
     * @return array{out: StockMovement, in: StockMovement}
     *
     * @throws InsufficientStockException เมื่อคลังต้นทางของไม่พอ
     */
    public function transfer(
        Product $product,
        Warehouse $from,
        Warehouse $to,
        string $qty,
        array $options = [],
    ): array {
        return DB::transaction(function () use ($product, $from, $to, $qty, $options): array {
            // ตัดออกก่อนเสมอ ถ้าของไม่พอจะ throw แล้ว rollback ทั้งคู่ ไม่มีของงอกที่ปลายทาง
            $out = $this->apply($product, $from, StockMovementType::TransferOut, $qty, $options);
            $in = $this->apply($product, $to, StockMovementType::TransferIn, $qty, $options);

            return ['out' => $out, 'in' => $in];
        });
    }

    /**
     * จองของให้ใบสั่งขาย — ไม่ลด qty_on_hand (spec 4.4)
     *
     * ของไม่พอไม่ถือว่าผิด แต่คืนจำนวนที่ขาดกลับไปให้ผู้เรียกเตือนผู้ใช้และบันทึกเป็น backorder
     *
     * @return string จำนวนที่ขาด (0 ถ้าของพอ)
     */
    public function reserve(Product $product, Warehouse $warehouse, string $qty): string
    {
        return DB::transaction(function () use ($product, $warehouse, $qty): string {
            $level = $this->lockLevel($product->id, $warehouse->id);

            $newReserved = bcadd((string) $level->qty_reserved, $qty, self::SCALE);
            $level->update(['qty_reserved' => $newReserved]);

            $shortage = bcsub($newReserved, (string) $level->qty_on_hand, self::SCALE);

            return bccomp($shortage, '0', self::SCALE) > 0
                ? $shortage
                : bcadd('0', '0', self::SCALE);
        });
    }

    /**
     * คืนของที่จองไว้ เช่น ตอนยกเลิกใบสั่งขาย หรือหลัง post ใบส่งของ
     */
    public function release(Product $product, Warehouse $warehouse, string $qty): void
    {
        DB::transaction(function () use ($product, $warehouse, $qty): void {
            $level = $this->lockLevel($product->id, $warehouse->id);

            $newReserved = bcsub((string) $level->qty_reserved, $qty, self::SCALE);

            // กันยอดจองติดลบเมื่อคืนซ้ำ
            if (bccomp($newReserved, '0', self::SCALE) < 0) {
                $newReserved = bcadd('0', '0', self::SCALE);
            }

            $level->update(['qty_reserved' => $newReserved]);
        });
    }

    /**
     * ยอดคงเหลือของสินค้าในคลังหนึ่ง (สร้างแถวยอด 0 ให้ถ้ายังไม่เคยมี)
     */
    public function levelFor(Product $product, Warehouse $warehouse): StockLevel
    {
        return StockLevel::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['qty_on_hand' => 0, 'qty_reserved' => 0],
        );
    }

    /**
     * ผลรวมของ ledger — ใช้ตรวจว่ายอดสรุปยังตรงกับประวัติอยู่หรือไม่
     */
    public function ledgerBalance(Product $product, Warehouse $warehouse): string
    {
        $sum = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->sum('qty');

        return number_format((float) $sum, self::SCALE, '.', '');
    }

    /**
     * เขียนรายการลง ledger พร้อมอัปเดตยอดสรุป — หัวใจของทั้งคลาส
     *
     * @param  array<string, mixed>  $options  ref, unit_cost, lot_no, note, moved_at, user_id
     */
    private function apply(
        Product $product,
        Warehouse $warehouse,
        StockMovementType $type,
        string $qty,
        array $options,
    ): StockMovement {
        $this->guardPositiveQty($qty);

        return DB::transaction(function () use ($product, $warehouse, $type, $qty, $options): StockMovement {
            $level = $this->lockLevel($product->id, $warehouse->id);

            $signedQty = $type->sign() < 0
                ? '-'.$qty
                : bcadd($qty, '0', self::SCALE);

            $balanceAfter = bcadd((string) $level->qty_on_hand, $signedQty, self::SCALE);

            if (bccomp($balanceAfter, '0', self::SCALE) < 0) {
                throw new InsufficientStockException(
                    $product,
                    $warehouse,
                    $qty,
                    (string) $level->qty_on_hand,
                );
            }

            $level->update(['qty_on_hand' => $balanceAfter]);

            $ref = $options['ref'] ?? null;

            return StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => $type,
                'qty' => $signedQty,
                'unit_cost' => $options['unit_cost'] ?? null,
                'balance_after' => $balanceAfter,
                'ref_type' => $ref instanceof Model ? $ref->getMorphClass() : null,
                'ref_id' => $ref instanceof Model ? $ref->getKey() : null,
                'lot_no' => $options['lot_no'] ?? null,
                'note' => $options['note'] ?? null,
                'user_id' => $options['user_id'] ?? Auth::id(),
                'moved_at' => $options['moved_at'] ?? Carbon::now(),
            ]);
        });
    }

    /**
     * ล็อกแถวยอดคงเหลือ — ต้องเรียกภายใน transaction เท่านั้น
     */
    private function lockLevel(int $productId, int $warehouseId): StockLevel
    {
        StockLevel::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['qty_on_hand' => 0, 'qty_reserved' => 0],
        );

        return StockLevel::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function guardPositiveQty(string $qty): void
    {
        if (bccomp($qty, '0', self::SCALE) <= 0) {
            throw new \InvalidArgumentException('จำนวนที่เคลื่อนไหวต้องมากกว่า 0');
        }
    }
}
