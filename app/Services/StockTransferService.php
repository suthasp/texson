<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocType;
use App\Enums\StockDocumentStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\StockTransfer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ใบโอนคลัง — post แล้วเขียน ledger สองรายการ (transfer_out + transfer_in) ในทรานแซกชันเดียว
 */
class StockTransferService
{
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly StockService $stock,
        private readonly SerialNumberService $serials,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data): StockTransfer
    {
        $this->guardDifferentWarehouses((int) $data['from_warehouse_id'], (int) $data['to_warehouse_id']);

        return DB::transaction(function () use ($data): StockTransfer {
            $transfer = StockTransfer::create([
                'transfer_no' => $this->numbers->next(DocType::StockTransfer),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'transfer_date' => $data['transfer_date'],
                'status' => StockDocumentStatus::Draft,
                'note' => $data['note'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($transfer, $data['items'] ?? []);

            return $transfer->load('items.product');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(StockTransfer $transfer, array $data): StockTransfer
    {
        $this->guardEditable($transfer);
        $this->guardDifferentWarehouses((int) $data['from_warehouse_id'], (int) $data['to_warehouse_id']);

        return DB::transaction(function () use ($transfer, $data): StockTransfer {
            $transfer->update([
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'transfer_date' => $data['transfer_date'],
                'note' => $data['note'] ?? null,
            ]);

            $transfer->items()->delete();
            $this->syncItems($transfer, $data['items'] ?? []);

            return $transfer->refresh()->load('items.product');
        });
    }

    /**
     * @throws InvalidStatusTransitionException
     * @throws \App\Exceptions\Domain\InsufficientStockException เมื่อคลังต้นทางของไม่พอ
     */
    public function post(StockTransfer $transfer): StockTransfer
    {
        $this->guardTransition($transfer, StockDocumentStatus::Posted);

        return DB::transaction(function () use ($transfer): StockTransfer {
            $transfer->load('items.product', 'fromWarehouse', 'toWarehouse');

            foreach ($transfer->items as $item) {
                $this->stock->transfer(
                    $item->product,
                    $transfer->fromWarehouse,
                    $transfer->toWarehouse,
                    (string) $item->qty,
                    [
                        'ref' => $transfer,
                        'lot_no' => $item->lot_no,
                        'note' => __('โอนตามใบ :no', ['no' => $transfer->transfer_no]),
                        'moved_at' => $transfer->transfer_date->copy()->setTimeFrom(Carbon::now()),
                    ],
                );

                $this->serials->moveToWarehouse(
                    $item->product,
                    $transfer->fromWarehouse,
                    $transfer->toWarehouse,
                    $item->serial_numbers ?? [],
                );
            }

            $transfer->update([
                'status' => StockDocumentStatus::Posted,
                'posted_at' => Carbon::now(),
                'posted_by' => Auth::id(),
            ]);

            return $transfer->refresh();
        });
    }

    /**
     * @throws InvalidStatusTransitionException
     */
    public function cancel(StockTransfer $transfer): StockTransfer
    {
        $this->guardTransition($transfer, StockDocumentStatus::Cancelled);

        $transfer->update(['status' => StockDocumentStatus::Cancelled]);

        return $transfer->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(StockTransfer $transfer, array $items): void
    {
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);

            if ($productId === 0) {
                continue;
            }

            $transfer->items()->create([
                'product_id' => $productId,
                'qty' => $item['qty'],
                'lot_no' => $item['lot_no'] ?? null,
                'serial_numbers' => $this->cleanSerials($item['serial_numbers'] ?? null),
            ]);
        }
    }

    /**
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

    private function guardDifferentWarehouses(int $fromId, int $toId): void
    {
        if ($fromId === $toId) {
            throw ValidationException::withMessages([
                'to_warehouse_id' => __('คลังต้นทางและคลังปลายทางต้องไม่ใช่คลังเดียวกัน'),
            ]);
        }
    }

    private function guardEditable(StockTransfer $transfer): void
    {
        if (! $transfer->status->isEditable()) {
            throw new InvalidStatusTransitionException(
                $transfer->transfer_no,
                $transfer->status->label(),
                __('แก้ไข'),
            );
        }
    }

    private function guardTransition(StockTransfer $transfer, StockDocumentStatus $target): void
    {
        if (! $transfer->status->canTransitionTo($target)) {
            throw new InvalidStatusTransitionException(
                $transfer->transfer_no,
                $transfer->status->label(),
                $target->label(),
            );
        }
    }
}
