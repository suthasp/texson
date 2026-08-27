<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocType;
use App\Enums\StockDocumentStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ใบปรับปรุงสต็อก — ปรับยอดในระบบให้ตรงกับของจริงหน้างาน
 *
 * qty_system ตอนสร้างใบเป็นแค่ snapshot ไว้ให้คนกรอกเห็น ตอน post จะอ่านยอดจริงใหม่
 * แล้วคำนวณ qty_diff ใหม่ เพราะยอดอาจขยับระหว่างที่ใบยังเป็น draft
 */
class StockAdjustmentService
{
    private const SCALE = 3;

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly StockService $stock,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data): StockAdjustment
    {
        return DB::transaction(function () use ($data): StockAdjustment {
            $adjustment = StockAdjustment::create([
                'adjust_no' => $this->numbers->next(DocType::StockAdjustment),
                'warehouse_id' => $data['warehouse_id'],
                'reason' => $data['reason'],
                'adjusted_at' => $data['adjusted_at'] ?? Carbon::now(),
                'status' => StockDocumentStatus::Draft,
                'note' => $data['note'] ?? null,
                'user_id' => Auth::id(),
            ]);

            $this->syncItems($adjustment, $data['items'] ?? []);

            return $adjustment->load('items.product');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(StockAdjustment $adjustment, array $data): StockAdjustment
    {
        $this->guardEditable($adjustment);

        return DB::transaction(function () use ($adjustment, $data): StockAdjustment {
            $adjustment->update([
                'warehouse_id' => $data['warehouse_id'],
                'reason' => $data['reason'],
                'adjusted_at' => $data['adjusted_at'] ?? $adjustment->adjusted_at,
                'note' => $data['note'] ?? null,
            ]);

            $adjustment->items()->delete();
            $this->syncItems($adjustment, $data['items'] ?? []);

            return $adjustment->refresh()->load('items.product');
        });
    }

    /**
     * @throws InvalidStatusTransitionException
     */
    public function post(StockAdjustment $adjustment): StockAdjustment
    {
        $this->guardTransition($adjustment, StockDocumentStatus::Posted);

        return DB::transaction(function () use ($adjustment): StockAdjustment {
            $adjustment->load('items.product', 'warehouse');
            $warehouse = $adjustment->warehouse;

            foreach ($adjustment->items as $item) {
                // อ่านยอดจริง ณ เวลา post ไม่ใช่ค่าที่ snapshot ไว้ตอนสร้างใบ
                $current = (string) $this->stock->levelFor($item->product, $warehouse)->qty_on_hand;
                $diff = bcsub((string) $item->qty_counted, $current, self::SCALE);

                $item->update([
                    'qty_system' => $current,
                    'qty_diff' => $diff,
                ]);

                $this->stock->adjustBy($item->product, $warehouse, $diff, [
                    'ref' => $adjustment,
                    'lot_no' => $item->lot_no,
                    'note' => __('ปรับปรุงตามใบ :no (:reason)', [
                        'no' => $adjustment->adjust_no,
                        'reason' => $adjustment->reason->label(),
                    ]),
                    'moved_at' => $adjustment->adjusted_at,
                ]);
            }

            $adjustment->update([
                'status' => StockDocumentStatus::Posted,
                'posted_at' => Carbon::now(),
                'posted_by' => Auth::id(),
            ]);

            return $adjustment->refresh();
        });
    }

    /**
     * @throws InvalidStatusTransitionException
     */
    public function cancel(StockAdjustment $adjustment): StockAdjustment
    {
        $this->guardTransition($adjustment, StockDocumentStatus::Cancelled);

        $adjustment->update(['status' => StockDocumentStatus::Cancelled]);

        return $adjustment->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(StockAdjustment $adjustment, array $items): void
    {
        $warehouse = $adjustment->warehouse()->firstOrFail();

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId === 0) {
                continue;
            }

            $product = Product::findOrFail($productId);
            $system = (string) $this->stock->levelFor($product, $warehouse)->qty_on_hand;
            $counted = (string) $item['qty_counted'];

            $adjustment->items()->create([
                'product_id' => $product->id,
                'qty_system' => $system,
                'qty_counted' => $counted,
                'qty_diff' => bcsub($counted, $system, self::SCALE),
                'lot_no' => $item['lot_no'] ?? null,
            ]);
        }
    }

    private function guardEditable(StockAdjustment $adjustment): void
    {
        if (! $adjustment->status->isEditable()) {
            throw new InvalidStatusTransitionException(
                $adjustment->adjust_no,
                $adjustment->status->label(),
                __('แก้ไข'),
            );
        }
    }

    private function guardTransition(StockAdjustment $adjustment, StockDocumentStatus $target): void
    {
        if (! $adjustment->status->canTransitionTo($target)) {
            throw new InvalidStatusTransitionException(
                $adjustment->adjust_no,
                $adjustment->status->label(),
                $target->label(),
            );
        }
    }
}
