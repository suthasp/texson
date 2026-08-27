<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DocType;
use App\Enums\QuotationStatus;
use App\Enums\SalesOrderStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\QuotationAlreadyConvertedException;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * แปลงใบเสนอราคาที่ลูกค้าตอบรับแล้วเป็นใบสั่งขาย (spec 4.3)
 *
 * กติกา
 *  - ต้องเป็นใบสถานะ accepted เท่านั้น
 *  - หนึ่งใบเสนอราคาสร้างใบสั่งขายได้ครั้งเดียว (บังคับซ้ำที่ระดับ unique index ด้วย)
 *  - ยอดเงินและ snapshot ทุกบรรทัดคัดลอกมาทั้งชุด ไม่คำนวณใหม่จากราคาสินค้าปัจจุบัน
 *    เพราะราคาที่ตกลงกับลูกค้าคือราคาบนใบเสนอราคา ไม่ใช่ราคาในตาราง products วันนี้
 */
class ConvertQuotationToSalesOrder
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides  customer_po_no, required_date, warehouse_id, note
     *
     * @throws InvalidStatusTransitionException
     * @throws QuotationAlreadyConvertedException
     */
    public function handle(Quotation $quotation, array $overrides = []): SalesOrder
    {
        $this->guardAccepted($quotation);
        $this->guardNotConverted($quotation);

        return DB::transaction(function () use ($quotation, $overrides): SalesOrder {
            $quotation->loadMissing('items', 'customer');

            $orderDate = Carbon::parse((string) ($overrides['order_date'] ?? Carbon::now()->toDateString()));

            $order = SalesOrder::create([
                'so_no' => $this->numbers->next(DocType::SalesOrder, $orderDate),
                'quotation_id' => $quotation->id,
                'customer_id' => $quotation->customer_id,
                'customer_site_id' => $quotation->customer_site_id,
                'warehouse_id' => $this->resolveWarehouseId($overrides),
                'sales_user_id' => $quotation->sales_user_id,
                'customer_po_no' => $overrides['customer_po_no'] ?? null,
                'order_date' => $orderDate->toDateString(),
                'required_date' => $overrides['required_date'] ?? null,
                'currency' => $quotation->currency,

                // ยอดเงินยกมาทั้งชุด — ราคาที่ตกลงกันแล้วห้ามเปลี่ยนตามราคาสินค้าปัจจุบัน
                'subtotal' => $quotation->subtotal,
                'discount_amount' => $quotation->discount_amount,
                'after_discount' => $quotation->after_discount,
                'vat_rate' => $quotation->vat_rate,
                'vat_amount' => $quotation->vat_amount,
                'grand_total' => $quotation->grand_total,
                'cost_total' => $quotation->cost_total,

                'status' => SalesOrderStatus::Pending,
                'payment_terms' => $quotation->payment_terms,
                'delivery_terms' => $quotation->delivery_terms,
                'note' => $overrides['note'] ?? $quotation->customer_note,
                'internal_note' => $quotation->internal_note,
                'created_by' => Auth::id(),
            ]);

            foreach ($quotation->items as $item) {
                $order->items()->create($this->lineFrom($item));
            }

            return $order->refresh()->load('items.product');
        });
    }

    /**
     * คัดลอกบรรทัดจากใบเสนอราคาพร้อม snapshot ทั้งหมด
     *
     * @return array<string, mixed>
     */
    private function lineFrom(QuotationItem $item): array
    {
        return [
            'line_no' => $item->line_no,
            'quotation_item_id' => $item->id,
            'product_id' => $item->product_id,
            'item_type' => $item->item_type,
            'sku_snapshot' => $item->sku_snapshot,
            'description' => $item->description,
            'uom' => $item->uom,
            'unit_price' => $item->unit_price,
            'cost_snapshot' => $item->cost_snapshot,
            'discount_percent' => $item->discount_percent,
            'discount_amount' => $item->discount_amount,
            'line_total' => $item->line_total,
            'qty_ordered' => $item->qty,
            // ยังไม่จองและยังไม่ส่ง จนกว่าจะกดยืนยันใบ
            'qty_reserved' => '0.000',
            'qty_delivered' => '0.000',
        ];
    }

    /**
     * คลังที่จะจ่ายของ — ไม่ระบุมาก็ใช้คลังเริ่มต้นของระบบ (ADR-017)
     */
    private function resolveWarehouseId(array $overrides): int
    {
        if (filled($overrides['warehouse_id'] ?? null)) {
            return (int) $overrides['warehouse_id'];
        }

        $default = Warehouse::query()->where('is_default', true)->first()
            ?? Warehouse::query()->orderBy('id')->firstOrFail();

        return $default->id;
    }

    private function guardAccepted(Quotation $quotation): void
    {
        if ($quotation->status !== QuotationStatus::Accepted) {
            throw new InvalidStatusTransitionException(
                $quotation->displayNo(),
                $quotation->status->label(),
                __('สร้างใบสั่งขาย'),
            );
        }
    }

    private function guardNotConverted(Quotation $quotation): void
    {
        $existing = SalesOrder::withTrashed()
            ->where('quotation_id', $quotation->id)
            ->first();

        if ($existing !== null) {
            throw new QuotationAlreadyConvertedException(
                $quotation->displayNo(),
                $existing->id,
                $existing->so_no,
            );
        }
    }
}
