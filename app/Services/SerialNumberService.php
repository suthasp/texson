<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SerialStatus;
use App\Exceptions\Domain\SerialNumberMismatchException;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * จัดการ serial รายชิ้นให้สอดคล้องกับยอดสต็อกเสมอ
 *
 * กติกา: จำนวน serial ที่ยังนับเป็นของในคลัง (in_stock + reserved) ของสินค้าหนึ่งในคลังหนึ่ง
 * ต้องเท่ากับ qty_on_hand ของคู่นั้นพอดี
 */
class SerialNumberService
{
    /**
     * ตรวจว่า serial ที่กรอกมาครบตามจำนวนและไม่ซ้ำกับที่มีอยู่แล้ว
     *
     * @param  array<int, string>  $serials
     *
     * @throws SerialNumberMismatchException
     * @throws ValidationException
     */
    public function validateForReceipt(Product $product, string $qty, array $serials): void
    {
        if (! $product->is_serialized) {
            return;
        }

        $serials = $this->normalize($serials);

        if (bccomp((string) count($serials), $qty, 3) !== 0) {
            throw new SerialNumberMismatchException($product->sku, $qty, count($serials));
        }

        $duplicatesInInput = array_diff_assoc($serials, array_unique($serials));

        if ($duplicatesInInput !== []) {
            throw ValidationException::withMessages([
                'items' => __('Serial ซ้ำกันในใบเดียวกัน: :serials', ['serials' => implode(', ', array_unique($duplicatesInInput))]),
            ]);
        }

        $existing = SerialNumber::query()
            ->where('product_id', $product->id)
            ->whereIn('serial_no', $serials)
            ->pluck('serial_no')
            ->all();

        if ($existing !== []) {
            throw ValidationException::withMessages([
                'items' => __('Serial นี้มีอยู่ในระบบแล้ว: :serials', ['serials' => implode(', ', $existing)]),
            ]);
        }
    }

    /**
     * สร้าง serial ตอน post ใบรับสินค้า
     *
     * @param  array<int, string>  $serials
     * @return array<int, SerialNumber>
     */
    public function createOnReceipt(
        Product $product,
        Warehouse $warehouse,
        array $serials,
        ?string $lotNo = null,
    ): array {
        if (! $product->is_serialized) {
            return [];
        }

        return DB::transaction(function () use ($product, $warehouse, $serials, $lotNo): array {
            $created = [];

            foreach ($this->normalize($serials) as $serialNo) {
                $created[] = SerialNumber::create([
                    'product_id' => $product->id,
                    'serial_no' => $serialNo,
                    'warehouse_id' => $warehouse->id,
                    'status' => SerialStatus::InStock,
                    'lot_no' => $lotNo,
                ]);
            }

            return $created;
        });
    }

    /**
     * ย้าย serial ตามใบโอนคลัง
     *
     * @param  array<int, string>  $serials
     *
     * @throws ValidationException เมื่อ serial ไม่ได้อยู่ในคลังต้นทาง
     */
    public function moveToWarehouse(Product $product, Warehouse $from, Warehouse $to, array $serials): void
    {
        if (! $product->is_serialized || $serials === []) {
            return;
        }

        DB::transaction(function () use ($product, $from, $to, $serials): void {
            foreach ($this->normalize($serials) as $serialNo) {
                $serial = SerialNumber::query()
                    ->where('product_id', $product->id)
                    ->where('serial_no', $serialNo)
                    ->lockForUpdate()
                    ->first();

                if ($serial === null || $serial->warehouse_id !== $from->id || $serial->status !== SerialStatus::InStock) {
                    throw ValidationException::withMessages([
                        'items' => __('Serial :serial ไม่ได้อยู่ในคลัง :warehouse หรือไม่พร้อมโอน', [
                            'serial' => $serialNo,
                            'warehouse' => $from->code,
                        ]),
                    ]);
                }

                $serial->update(['warehouse_id' => $to->id]);
            }
        });
    }

    /**
     * ตัดจำหน่าย serial ตอนปรับสต็อกลด
     *
     * @param  array<int, string>  $serials
     */
    public function scrap(Product $product, array $serials): void
    {
        foreach ($this->normalize($serials) as $serialNo) {
            SerialNumber::query()
                ->where('product_id', $product->id)
                ->where('serial_no', $serialNo)
                ->first()
                ?->transitionTo(SerialStatus::Scrapped, ['warehouse_id' => null]);
        }
    }

    /**
     * จำนวน serial ที่ยังนับเป็นของในคลัง — ใช้ตรวจว่าตรงกับ qty_on_hand หรือไม่
     */
    public function countOnHand(Product $product, Warehouse $warehouse): int
    {
        return SerialNumber::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereIn('status', [SerialStatus::InStock, SerialStatus::Reserved])
            ->count();
    }

    /**
     * ตัดช่องว่างและแถวว่างออก
     *
     * @param  array<int, string|null>  $serials
     * @return array<int, string>
     */
    private function normalize(array $serials): array
    {
        return array_values(array_filter(
            array_map(static fn (?string $serial): string => trim((string) $serial), $serials),
            static fn (string $serial): bool => $serial !== '',
        ));
    }
}
