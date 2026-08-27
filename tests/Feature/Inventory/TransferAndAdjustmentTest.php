<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\SerialStatus;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockDocumentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use App\Services\StockService;
use App\Services\StockTransferService;

beforeEach(function (): void {
    actingAsRole(RoleName::Warehouse);

    $this->transfers = app(StockTransferService::class);
    $this->adjustments = app(StockAdjustmentService::class);
    $this->stock = app(StockService::class);

    $this->from = Warehouse::factory()->create(['code' => 'HQ']);
    $this->to = Warehouse::factory()->create(['code' => 'VAN']);
    $this->product = Product::factory()->create();
});

// ── ใบโอนคลัง ───────────────────────────────────────────

it('post ใบโอนแล้วเขียน ledger สองรายการที่จับคู่กัน', function (): void {
    $this->stock->receive($this->product, $this->from, '20');

    $transfer = $this->transfers->createDraft([
        'from_warehouse_id' => $this->from->id,
        'to_warehouse_id' => $this->to->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $this->product->id, 'qty' => '8']],
    ]);

    expect($transfer->transfer_no)->toStartWith('TR-');

    $this->transfers->post($transfer);

    expect((string) $this->stock->levelFor($this->product, $this->from)->qty_on_hand)->toBe('12.000')
        ->and((string) $this->stock->levelFor($this->product, $this->to)->qty_on_hand)->toBe('8.000');

    $movements = StockMovement::whereIn('type', [StockMovementType::TransferOut, StockMovementType::TransferIn])->get();

    expect($movements)->toHaveCount(2)
        ->and($movements->every(fn ($m) => $m->ref_type === StockTransfer::class))->toBeTrue()
        ->and($movements->every(fn ($m) => $m->ref_id === $transfer->id))->toBeTrue();
});

it('โอนของไม่พอแล้วยกเลิกทั้งใบ สถานะยังเป็นร่าง', function (): void {
    $this->stock->receive($this->product, $this->from, '3');

    $transfer = $this->transfers->createDraft([
        'from_warehouse_id' => $this->from->id,
        'to_warehouse_id' => $this->to->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $this->product->id, 'qty' => '10']],
    ]);

    expect(fn () => $this->transfers->post($transfer))->toThrow(InsufficientStockException::class);

    expect($transfer->refresh()->status)->toBe(StockDocumentStatus::Draft)
        ->and((string) $this->stock->levelFor($this->product, $this->from)->qty_on_hand)->toBe('3.000')
        ->and((string) $this->stock->levelFor($this->product, $this->to)->qty_on_hand)->toBe('0.000');
});

it('โอนเข้าคลังเดียวกับต้นทางไม่ได้', function (): void {
    expect(fn () => $this->transfers->createDraft([
        'from_warehouse_id' => $this->from->id,
        'to_warehouse_id' => $this->from->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $this->product->id, 'qty' => '1']],
    ]))->toThrow(Illuminate\Validation\ValidationException::class);
});

it('โอนสินค้าที่มี serial แล้ว serial ย้ายคลังตาม', function (): void {
    $product = Product::factory()->serialized()->create();

    $this->stock->receive($product, $this->from, '2');
    SerialNumber::factory()->create(['product_id' => $product->id, 'serial_no' => 'SN-A', 'warehouse_id' => $this->from->id]);
    SerialNumber::factory()->create(['product_id' => $product->id, 'serial_no' => 'SN-B', 'warehouse_id' => $this->from->id]);

    $transfer = $this->transfers->createDraft([
        'from_warehouse_id' => $this->from->id,
        'to_warehouse_id' => $this->to->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty' => '2', 'serial_numbers' => "SN-A\nSN-B"]],
    ]);

    $this->transfers->post($transfer);

    expect(SerialNumber::where('serial_no', 'SN-A')->value('warehouse_id'))->toBe($this->to->id)
        ->and(SerialNumber::where('serial_no', 'SN-B')->value('warehouse_id'))->toBe($this->to->id);
});

it('โอน serial ที่ไม่ได้อยู่ในคลังต้นทางไม่ได้', function (): void {
    $product = Product::factory()->serialized()->create();

    $this->stock->receive($product, $this->from, '1');
    SerialNumber::factory()->create([
        'product_id' => $product->id, 'serial_no' => 'SN-OTHER', 'warehouse_id' => $this->to->id,
    ]);

    $transfer = $this->transfers->createDraft([
        'from_warehouse_id' => $this->from->id,
        'to_warehouse_id' => $this->to->id,
        'transfer_date' => now()->toDateString(),
        'items' => [['product_id' => $product->id, 'qty' => '1', 'serial_numbers' => 'SN-OTHER']],
    ]);

    expect(fn () => $this->transfers->post($transfer))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

// ── ใบปรับปรุงสต็อก ─────────────────────────────────────

it('ปรับยอดขึ้นเขียน ledger เป็น adjust_in', function (): void {
    $this->stock->receive($this->product, $this->from, '10');

    $adjustment = $this->adjustments->createDraft([
        'warehouse_id' => $this->from->id,
        'reason' => StockAdjustmentReason::Found->value,
        'adjusted_at' => now(),
        'items' => [['product_id' => $this->product->id, 'qty_counted' => '14']],
    ]);

    expect($adjustment->adjust_no)->toStartWith('ADJ-');

    $this->adjustments->post($adjustment);

    $movement = StockMovement::where('type', StockMovementType::AdjustIn)->firstOrFail();

    expect((string) $movement->qty)->toBe('4.000')
        ->and($movement->ref_type)->toBe(StockAdjustment::class)
        ->and((string) $this->stock->levelFor($this->product, $this->from)->qty_on_hand)->toBe('14.000');
});

it('ปรับยอดลงเขียน ledger เป็น adjust_out', function (): void {
    $this->stock->receive($this->product, $this->from, '10');

    $adjustment = $this->adjustments->createDraft([
        'warehouse_id' => $this->from->id,
        'reason' => StockAdjustmentReason::Damaged->value,
        'adjusted_at' => now(),
        'items' => [['product_id' => $this->product->id, 'qty_counted' => '6.500']],
    ]);

    $this->adjustments->post($adjustment);

    $movement = StockMovement::where('type', StockMovementType::AdjustOut)->firstOrFail();

    expect((string) $movement->qty)->toBe('-3.500')
        ->and((string) $this->stock->levelFor($this->product, $this->from)->qty_on_hand)->toBe('6.500');
});

it('คำนวณผลต่างใหม่ตอน post ไม่ใช้ค่าที่ snapshot ไว้ตอนสร้างใบ', function (): void {
    $this->stock->receive($this->product, $this->from, '10');

    $adjustment = $this->adjustments->createDraft([
        'warehouse_id' => $this->from->id,
        'reason' => StockAdjustmentReason::StockCount->value,
        'adjusted_at' => now(),
        'items' => [['product_id' => $this->product->id, 'qty_counted' => '12']],
    ]);

    // ตอนสร้างใบ ผลต่างคือ +2 (12 - 10)
    expect((string) $adjustment->items->first()->qty_system)->toBe('10.000')
        ->and((string) $adjustment->items->first()->qty_diff)->toBe('2.000');

    // มีคนรับของเพิ่มระหว่างที่ใบยังเป็นร่าง
    $this->stock->receive($this->product, $this->from, '5');

    $this->adjustments->post($adjustment);

    $item = $adjustment->refresh()->items->first();

    // ผลต่างต้องคำนวณใหม่จากยอดจริง 15 → นับได้ 12 = -3
    expect((string) $item->qty_system)->toBe('15.000')
        ->and((string) $item->qty_diff)->toBe('-3.000')
        ->and((string) $this->stock->levelFor($this->product, $this->from)->qty_on_hand)->toBe('12.000');
});

it('นับได้เท่ากับยอดในระบบพอดี ไม่เขียน ledger เปล่า ๆ', function (): void {
    $this->stock->receive($this->product, $this->from, '10');
    $movementsBefore = StockMovement::count();

    $adjustment = $this->adjustments->createDraft([
        'warehouse_id' => $this->from->id,
        'reason' => StockAdjustmentReason::StockCount->value,
        'adjusted_at' => now(),
        'items' => [['product_id' => $this->product->id, 'qty_counted' => '10']],
    ]);

    $this->adjustments->post($adjustment);

    expect(StockMovement::count())->toBe($movementsBefore)
        ->and($adjustment->refresh()->status)->toBe(StockDocumentStatus::Posted);
});

it('ยอดยกมาตั้งสต็อกจากศูนย์ได้', function (): void {
    $adjustment = $this->adjustments->createDraft([
        'warehouse_id' => $this->from->id,
        'reason' => StockAdjustmentReason::Opening->value,
        'adjusted_at' => now(),
        'items' => [['product_id' => $this->product->id, 'qty_counted' => '25']],
    ]);

    $this->adjustments->post($adjustment);

    expect((string) $this->stock->levelFor($this->product, $this->from)->qty_on_hand)->toBe('25.000');
});

it('post ใบปรับปรุงซ้ำไม่ได้', function (): void {
    $adjustment = $this->adjustments->createDraft([
        'warehouse_id' => $this->from->id,
        'reason' => StockAdjustmentReason::Opening->value,
        'adjusted_at' => now(),
        'items' => [['product_id' => $this->product->id, 'qty_counted' => '5']],
    ]);

    $this->adjustments->post($adjustment);

    expect(fn () => $this->adjustments->post($adjustment->refresh()))
        ->toThrow(InvalidStatusTransitionException::class);

    expect((string) $this->stock->levelFor($this->product, $this->from)->qty_on_hand)->toBe('5.000');
});

it('ปรับยอดลงต่ำกว่าศูนย์ไม่ได้', function (): void {
    $this->stock->receive($this->product, $this->from, '5');

    $adjustment = $this->adjustments->createDraft([
        'warehouse_id' => $this->from->id,
        'reason' => StockAdjustmentReason::Lost->value,
        'adjusted_at' => now(),
        'items' => [['product_id' => $this->product->id, 'qty_counted' => '0']],
    ]);

    // นับได้ 0 = ปรับลง 5 พอดี ทำได้
    $this->adjustments->post($adjustment);

    expect((string) $this->stock->levelFor($this->product, $this->from)->qty_on_hand)->toBe('0.000');
});

it('serial ที่ตัดจำหน่ายไม่นับเป็นของในคลังอีกต่อไป', function (): void {
    $serial = SerialNumber::factory()->create([
        'warehouse_id' => $this->from->id,
        'status' => SerialStatus::InStock,
    ]);

    $serial->transitionTo(SerialStatus::Scrapped, ['warehouse_id' => null]);

    expect($serial->refresh()->status)->toBe(SerialStatus::Scrapped)
        ->and($serial->status->countsAsOnHand())->toBeFalse()
        ->and($serial->warehouse_id)->toBeNull();
});

it('เปลี่ยนสถานะ serial ข้ามขั้นไม่ได้', function (): void {
    $serial = SerialNumber::factory()->create(['status' => SerialStatus::Scrapped]);

    expect(fn () => $serial->transitionTo(SerialStatus::InStock))
        ->toThrow(InvalidStatusTransitionException::class);
});
