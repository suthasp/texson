<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SalesOrderStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ใบสั่งขาย (spec 4.4)
 *
 * กฎที่คลาสนี้รับผิดชอบ
 *  - ยืนยันใบ → จองของ (qty_reserved เพิ่ม แต่ qty_on_hand ไม่ลด)
 *  - ของไม่พอไม่ถือว่าผิด — จองเท่าที่มี แล้วบันทึกส่วนที่ขาดเป็น backorder
 *  - ยกเลิกใบ → คืนของที่จองไว้ทั้งหมด
 *  - สถานะการส่งของคำนวณจากยอดจริงในบรรทัด ไม่ใช่ตั้งค่าเอง
 *
 * การแตะสต็อกทุกครั้งผ่าน StockService เท่านั้น
 */
class SalesOrderService
{
    private const SCALE = 3;

    public function __construct(
        private readonly StockService $stock,
    ) {}

    /**
     * ยืนยันใบแล้วจองของ — หัวใจของสเปกข้อ 4.4
     *
     * ของไม่พอ "เตือนแต่อนุญาต" จึงจองเท่าที่มีจริง แล้วเก็บส่วนที่ขาดไว้ใน qty_reserved
     * ที่น้อยกว่า qty_ordered ผู้เรียกอ่านจำนวนที่ขาดได้จาก SalesOrder::shortageQty()
     *
     * @throws InvalidStatusTransitionException
     */
    public function confirm(SalesOrder $order, ?User $confirmedBy = null): SalesOrder
    {
        $this->guardTransition($order, SalesOrderStatus::Reserved);

        return DB::transaction(function () use ($order, $confirmedBy): SalesOrder {
            $order->load('items.product', 'warehouse');
            $warehouse = $order->warehouse;

            foreach ($order->items as $item) {
                if (! $item->isStockable()) {
                    continue;
                }

                $available = $this->availableFor($item, $order);

                // จองเท่าที่มีจริง ไม่เกินยอดที่สั่ง
                $toReserve = bccomp($available, (string) $item->qty_ordered, self::SCALE) < 0
                    ? $available
                    : (string) $item->qty_ordered;

                if (bccomp($toReserve, '0', self::SCALE) > 0) {
                    $this->stock->reserve($item->product, $warehouse, $toReserve);
                }

                $item->update(['qty_reserved' => bcadd($toReserve, '0', self::SCALE)]);
            }

            $order->update([
                'status' => SalesOrderStatus::Reserved,
                'confirmed_at' => Carbon::now(),
                'confirmed_by' => $confirmedBy?->id ?? Auth::id(),
            ]);

            return $order->refresh()->load('items.product');
        });
    }

    /**
     * ยกเลิกใบและคืนของที่จองไว้ทั้งหมด (spec 4.4)
     *
     * คืนเฉพาะส่วนที่ยังจองค้างอยู่ — ของที่ส่งออกไปแล้วถูกปลดจองไปตั้งแต่ตอน post ใบส่งของ
     *
     * @throws InvalidStatusTransitionException
     */
    public function cancel(SalesOrder $order, ?string $reason = null): SalesOrder
    {
        $this->guardTransition($order, SalesOrderStatus::Cancelled);

        return DB::transaction(function () use ($order, $reason): SalesOrder {
            $order->load('items.product', 'warehouse');

            if ($order->status->holdsReservation()) {
                foreach ($order->items as $item) {
                    $this->releaseRemaining($item, $order);
                }
            }

            $order->update([
                'status' => SalesOrderStatus::Cancelled,
                'closed_at' => Carbon::now(),
                'cancel_reason' => $reason,
            ]);

            return $order->refresh();
        });
    }

    /**
     * คำนวณสถานะใหม่จากยอดที่ส่งจริง — เรียกหลัง post หรือยกเลิกใบส่งของ
     *
     * ไม่ตั้งสถานะเองจากภายนอก เพื่อให้สถานะสะท้อนข้อมูลจริงเสมอ
     */
    public function refreshDeliveryStatus(SalesOrder $order): SalesOrder
    {
        $order->load('items');

        if ($order->status->isClosed() || $order->status === SalesOrderStatus::Pending) {
            return $order;
        }

        $deliverable = $order->items->filter(fn (SalesOrderItem $item): bool => $item->isDeliverable());

        $delivered = $deliverable->isNotEmpty()
            && $deliverable->every(fn (SalesOrderItem $item): bool => $item->isFullyDelivered());

        $anyDelivered = $order->items->contains(
            fn (SalesOrderItem $item): bool => bccomp((string) $item->qty_delivered, '0', self::SCALE) > 0,
        );

        $status = match (true) {
            $delivered => SalesOrderStatus::Delivered,
            $anyDelivered => SalesOrderStatus::PartiallyDelivered,
            default => SalesOrderStatus::Reserved,
        };

        if ($status !== $order->status) {
            $order->update([
                'status' => $status,
                'closed_at' => $status === SalesOrderStatus::Delivered ? Carbon::now() : null,
            ]);
        }

        // ส่งครบแล้วของที่ยังจองค้างอยู่ (เช่น จองเผื่อไว้เกิน) ต้องถูกคืนเข้าคลัง
        if ($status === SalesOrderStatus::Delivered) {
            $order->load('items.product', 'warehouse');

            foreach ($order->items as $item) {
                $this->releaseRemaining($item, $order);
            }
        }

        return $order->refresh();
    }

    /**
     * ปลดจองตามจำนวนที่ส่งออกไปจริง — เรียกจาก DeliveryService ตอน post
     */
    public function releaseReserved(SalesOrderItem $item, SalesOrder $order, string $qty): void
    {
        if (! $item->isStockable()) {
            return;
        }

        $item->loadMissing('product');
        $order->loadMissing('warehouse');

        // ปลดได้ไม่เกินที่จองไว้จริง — ของส่วนที่เป็น backorder ไม่เคยถูกจอง จึงไม่มีอะไรให้ปลด
        $reserved = (string) $item->qty_reserved;
        $toRelease = bccomp($qty, $reserved, self::SCALE) > 0 ? $reserved : $qty;

        if (bccomp($toRelease, '0', self::SCALE) <= 0) {
            return;
        }

        $this->stock->release($item->product, $order->warehouse, $toRelease);

        $item->update([
            'qty_reserved' => bcsub($reserved, $toRelease, self::SCALE),
        ]);
    }

    /**
     * ยอดที่ยังจองได้จริงในคลังของใบนี้
     *
     * ใช้ qty_available (ในมือ − ที่จองไว้แล้ว) เพื่อไม่ไปแย่งของที่ใบอื่นจองไว้ก่อน
     */
    private function availableFor(SalesOrderItem $item, SalesOrder $order): string
    {
        $level = $this->stock->levelFor($item->product, $order->warehouse);

        $available = $level->qty_available;

        return bccomp($available, '0', self::SCALE) > 0
            ? $available
            : bcadd('0', '0', self::SCALE);
    }

    /**
     * คืนของที่ยังจองค้างอยู่ในบรรทัดนี้ให้หมด
     */
    private function releaseRemaining(SalesOrderItem $item, SalesOrder $order): void
    {
        if (! $item->isStockable()) {
            return;
        }

        $reserved = (string) $item->qty_reserved;

        if (bccomp($reserved, '0', self::SCALE) <= 0) {
            return;
        }

        $item->loadMissing('product');

        $this->stock->release($item->product, $order->warehouse, $reserved);

        $item->update(['qty_reserved' => bcadd('0', '0', self::SCALE)]);
    }

    private function guardTransition(SalesOrder $order, SalesOrderStatus $target): void
    {
        if (! $order->status->canTransitionTo($target)) {
            throw new InvalidStatusTransitionException(
                $order->so_no,
                $order->status->label(),
                $target->label(),
            );
        }
    }
}
