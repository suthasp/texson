<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocType;
use App\Enums\StockDocumentStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\GoodsReceipt;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ใบรับสินค้า — draft แก้ได้ · post แล้วกระทบสต็อกและแก้ไม่ได้อีก
 */
class GoodsReceiptService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly StockService $stock,
        private readonly SerialNumberService $serials,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($data): GoodsReceipt {
            $receipt = GoodsReceipt::create([
                'receipt_no' => $this->numbers->next(DocType::GoodsReceipt),
                'supplier_id' => $data['supplier_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'reference_no' => $data['reference_no'] ?? null,
                'received_date' => $data['received_date'],
                'status' => StockDocumentStatus::Draft,
                'note' => $data['note'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($receipt, $data['items'] ?? []);

            return $receipt->load('items.product');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(GoodsReceipt $receipt, array $data): GoodsReceipt
    {
        $this->guardEditable($receipt);

        return DB::transaction(function () use ($receipt, $data): GoodsReceipt {
            $receipt->update([
                'supplier_id' => $data['supplier_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'reference_no' => $data['reference_no'] ?? null,
                'received_date' => $data['received_date'],
                'note' => $data['note'] ?? null,
            ]);

            $receipt->items()->delete();
            $this->syncItems($receipt, $data['items'] ?? []);

            return $receipt->refresh()->load('items.product');
        });
    }

    /**
     * บันทึกใบเข้าสต็อกจริง — ตรวจ serial ให้ครบก่อน แล้วค่อยเขียน ledger
     *
     * @throws InvalidStatusTransitionException
     */
    public function post(GoodsReceipt $receipt): GoodsReceipt
    {
        $this->guardTransition($receipt, StockDocumentStatus::Posted);

        return DB::transaction(function () use ($receipt): GoodsReceipt {
            $receipt->load('items.product', 'warehouse');
            $warehouse = $receipt->warehouse;

            // ตรวจ serial ของทุกบรรทัดก่อนแตะสต็อก เพื่อไม่ให้รับเข้าไปครึ่งใบแล้วค้าง
            foreach ($receipt->items as $item) {
                $this->serials->validateForReceipt(
                    $item->product,
                    (string) $item->qty,
                    $item->serial_numbers ?? [],
                );
            }

            foreach ($receipt->items as $item) {
                $this->stock->receive($item->product, $warehouse, (string) $item->qty, [
                    'ref' => $receipt,
                    'unit_cost' => $item->unit_cost,
                    'lot_no' => $item->lot_no,
                    'note' => __('รับเข้าตามใบ :no', ['no' => $receipt->receipt_no]),
                    'moved_at' => $receipt->received_date->copy()->setTimeFrom(Carbon::now()),
                ]);

                $this->serials->createOnReceipt(
                    $item->product,
                    $warehouse,
                    $item->serial_numbers ?? [],
                    $item->lot_no,
                );
            }

            $receipt->update([
                'status' => StockDocumentStatus::Posted,
                'posted_at' => Carbon::now(),
                'posted_by' => Auth::id(),
            ]);

            return $receipt->refresh();
        });
    }

    /**
     * @throws InvalidStatusTransitionException
     */
    public function cancel(GoodsReceipt $receipt): GoodsReceipt
    {
        $this->guardTransition($receipt, StockDocumentStatus::Cancelled);

        $receipt->update(['status' => StockDocumentStatus::Cancelled]);

        return $receipt->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(GoodsReceipt $receipt, array $items): void
    {
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId === 0) {
                continue;
            }

            $product = Product::findOrFail($productId);

            $receipt->items()->create([
                'product_id' => $product->id,
                'qty' => $item['qty'],
                'unit_cost' => $item['unit_cost'] ?? 0,
                'lot_no' => $item['lot_no'] ?? null,
                'serial_numbers' => $this->cleanSerials($item['serial_numbers'] ?? null),
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

    private function guardEditable(GoodsReceipt $receipt): void
    {
        if (! $receipt->status->isEditable()) {
            throw new InvalidStatusTransitionException(
                $receipt->receipt_no,
                $receipt->status->label(),
                __('แก้ไข'),
            );
        }
    }

    private function guardTransition(GoodsReceipt $receipt, StockDocumentStatus $target): void
    {
        if (! $receipt->status->canTransitionTo($target)) {
            throw new InvalidStatusTransitionException(
                $receipt->receipt_no,
                $receipt->status->label(),
                $target->label(),
            );
        }
    }
}
