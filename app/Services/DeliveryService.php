<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocType;
use App\Enums\StockDocumentStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ใบส่งของ (spec 4.4)
 *
 * post แล้วเกิดสามอย่างพร้อมกันในทรานแซกชันเดียว
 *  1. qty_on_hand ลด และเขียน ledger type=issue
 *  2. qty_reserved ลดตามจำนวนที่ส่งจริง
 *  3. serial ที่จ่ายออกไปเปลี่ยนเป็น sold พร้อมตั้งช่วงรับประกัน
 *
 * ตรวจทุกบรรทัดให้จบก่อนแตะสต็อก เพื่อไม่ให้ส่งไปครึ่งใบแล้วค้าง (เหตุผลเดียวกับ ADR-006)
 */
class DeliveryService
{
    private const SCALE = 3;

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly StockService $stock,
        private readonly SerialNumberService $serials,
        private readonly SalesOrderService $salesOrders,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(SalesOrder $order, array $data): Delivery
    {
        $this->guardOrderCanDeliver($order);

        return DB::transaction(function () use ($order, $data): Delivery {
            $deliveryDate = Carbon::parse((string) ($data['delivery_date'] ?? Carbon::now()->toDateString()));

            $delivery = Delivery::create([
                'delivery_no' => $this->numbers->next(DocType::DeliveryNote, $deliveryDate),
                'sales_order_id' => $order->id,
                // ไม่ระบุคลังมาก็ใช้คลังของใบสั่งขาย ซึ่งเป็นคลังที่ของถูกจองไว้
                'warehouse_id' => $data['warehouse_id'] ?? $order->warehouse_id,
                'delivery_date' => $deliveryDate->toDateString(),
                'status' => StockDocumentStatus::Draft,
                'receiver_name' => $data['receiver_name'] ?? null,
                'vehicle_note' => $data['vehicle_note'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($delivery, $order, $data['items'] ?? []);

            return $delivery->refresh()->load('items.product', 'items.salesOrderItem');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidStatusTransitionException
     */
    public function updateDraft(Delivery $delivery, array $data): Delivery
    {
        $this->guardEditable($delivery);

        return DB::transaction(function () use ($delivery, $data): Delivery {
            $delivery->update([
                'warehouse_id' => $data['warehouse_id'] ?? $delivery->warehouse_id,
                'delivery_date' => $data['delivery_date'] ?? $delivery->delivery_date,
                'receiver_name' => $data['receiver_name'] ?? null,
                'vehicle_note' => $data['vehicle_note'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $delivery->items()->delete();
            $this->syncItems($delivery, $delivery->salesOrder, $data['items'] ?? []);

            return $delivery->refresh()->load('items.product', 'items.salesOrderItem');
        });
    }

    /**
     * ตัดสต็อกจริง — ย้อนกลับไม่ได้
     *
     * @throws InvalidStatusTransitionException
     * @throws \App\Exceptions\Domain\InsufficientStockException เมื่อของในคลังไม่พอ
     * @throws \App\Exceptions\Domain\SerialNumberMismatchException เมื่อ serial ไม่ครบ
     * @throws ValidationException เมื่อส่งเกินยอดที่ค้างอยู่
     */
    public function post(Delivery $delivery): Delivery
    {
        $this->guardTransition($delivery, StockDocumentStatus::Posted);

        return DB::transaction(function () use ($delivery): Delivery {
            $delivery->load('items.product', 'items.salesOrderItem', 'warehouse', 'salesOrder.warehouse');

            $order = $delivery->salesOrder;
            $warehouse = $delivery->warehouse;

            $this->guardOrderCanDeliver($order);

            // ── ตรวจให้จบก่อนแตะสต็อก ──
            foreach ($delivery->items as $item) {
                $this->guardNotOverDelivering($item);

                if ($item->isStockable()) {
                    $this->serials->validateForDelivery(
                        $item->product,
                        $warehouse,
                        (string) $item->qty,
                        $item->serials(),
                    );
                }
            }

            // ── ตัดสต็อกและปิดยอดจอง ──
            foreach ($delivery->items as $item) {
                $orderItem = $item->salesOrderItem;

                if ($item->isStockable()) {
                    $this->stock->issue($item->product, $warehouse, (string) $item->qty, [
                        'ref' => $delivery,
                        'unit_cost' => $orderItem->cost_snapshot,
                        'lot_no' => $item->lot_no,
                        'note' => __('ส่งของตามใบ :no (:so)', [
                            'no' => $delivery->delivery_no,
                            'so' => $order->so_no,
                        ]),
                        'moved_at' => $delivery->delivery_date->copy()->setTimeFrom(Carbon::now()),
                    ]);

                    $this->serials->markSoldOnDelivery(
                        $item->product,
                        $item->serials(),
                        $order,
                        $delivery->delivery_date,
                    );

                    // ของที่ส่งออกไปแล้วไม่ต้องจองไว้อีก
                    $this->salesOrders->releaseReserved($orderItem, $order, (string) $item->qty);
                }

                $orderItem->update([
                    'qty_delivered' => bcadd((string) $orderItem->qty_delivered, (string) $item->qty, self::SCALE),
                ]);
            }

            $delivery->update([
                'status' => StockDocumentStatus::Posted,
                'posted_at' => Carbon::now(),
                'posted_by' => Auth::id(),
            ]);

            $this->salesOrders->refreshDeliveryStatus($order->refresh());

            return $delivery->refresh();
        });
    }

    /**
     * ยกเลิกใบที่ยังเป็นร่าง — ใบที่ post แล้วยกเลิกไม่ได้ ต้องออกใบรับคืน
     *
     * @throws InvalidStatusTransitionException
     */
    public function cancel(Delivery $delivery): Delivery
    {
        $this->guardTransition($delivery, StockDocumentStatus::Cancelled);

        $delivery->update(['status' => StockDocumentStatus::Cancelled]);

        return $delivery->refresh();
    }

    /**
     * เตรียมบรรทัดตั้งต้นจากของที่ยังค้างส่งในใบสั่งขาย
     *
     * @return array<int, array<string, mixed>>
     */
    public function outstandingLines(SalesOrder $order): array
    {
        $order->loadMissing('items.product');

        return $order->items
            ->filter(fn (SalesOrderItem $item): bool => $item->hasOutstanding())
            ->map(fn (SalesOrderItem $item): array => [
                'sales_order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'sku' => $item->sku_snapshot,
                'description' => $item->description,
                'uom' => $item->uom,
                'is_serialized' => (bool) $item->product?->is_serialized,
                'qty_ordered' => (string) $item->qty_ordered,
                'qty_delivered' => (string) $item->qty_delivered,
                'qty' => $item->outstandingQty(),
                'serial_numbers' => '',
                'lot_no' => null,
            ])
            ->values()
            ->all();
    }

    // ── ภายใน ───────────────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Delivery $delivery, SalesOrder $order, array $items): void
    {
        $orderItems = $order->items()->get()->keyBy('id');

        foreach ($items as $row) {
            $orderItemId = (int) ($row['sales_order_item_id'] ?? 0);
            $qty = (string) ($row['qty'] ?? '0');

            if ($orderItemId === 0 || bccomp($qty, '0', self::SCALE) <= 0) {
                continue;
            }

            /** @var SalesOrderItem|null $orderItem */
            $orderItem = $orderItems->get($orderItemId);

            // บรรทัดที่ไม่ได้อยู่ในใบสั่งขายใบนี้ถูกทิ้ง — กันค่าที่ปลอมมาจากฟอร์ม
            if ($orderItem === null) {
                continue;
            }

            $delivery->items()->create([
                'sales_order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'qty' => $qty,
                'serial_numbers' => $this->cleanSerials($row['serial_numbers'] ?? null),
                'lot_no' => $row['lot_no'] ?? null,
            ]);
        }
    }

    /**
     * รับ serial มาเป็นข้อความหลายบรรทัดจาก textarea แล้วแปลงเป็น array
     *
     * @return array<int, string>|null
     */
    private function cleanSerials(string|array|null $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $lines = is_array($raw) ? $raw : (preg_split('/[\r\n,]+/', $raw) ?: []);

        $clean = array_values(array_filter(
            array_map(static fn (?string $line): string => trim((string) $line), $lines),
            static fn (string $line): bool => $line !== '',
        ));

        return $clean === [] ? null : $clean;
    }

    /**
     * ส่งเกินยอดที่สั่งไม่ได้ — ยอดส่งสะสมต้องไม่เกิน qty_ordered
     */
    private function guardNotOverDelivering(DeliveryItem $item): void
    {
        $orderItem = $item->salesOrderItem;
        $outstanding = $orderItem->outstandingQty();

        if (bccomp((string) $item->qty, $outstanding, self::SCALE) > 0) {
            throw ValidationException::withMessages([
                'items' => __('บรรทัด :desc ส่งได้อีกไม่เกิน :outstanding แต่ระบุมา :qty', [
                    'desc' => $orderItem->description,
                    'outstanding' => rtrim(rtrim($outstanding, '0'), '.'),
                    'qty' => rtrim(rtrim((string) $item->qty, '0'), '.'),
                ]),
            ]);
        }
    }

    private function guardOrderCanDeliver(SalesOrder $order): void
    {
        if (! $order->status->canDeliver()) {
            throw new InvalidStatusTransitionException(
                $order->so_no,
                $order->status->label(),
                __('ออกใบส่งของ'),
            );
        }
    }

    private function guardEditable(Delivery $delivery): void
    {
        if (! $delivery->status->isEditable()) {
            throw new InvalidStatusTransitionException(
                $delivery->delivery_no,
                $delivery->status->label(),
                __('แก้ไข'),
            );
        }
    }

    private function guardTransition(Delivery $delivery, StockDocumentStatus $target): void
    {
        if (! $delivery->status->canTransitionTo($target)) {
            throw new InvalidStatusTransitionException(
                $delivery->delivery_no,
                $delivery->status->label(),
                $target->label(),
            );
        }
    }
}
