<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\SerialStatus;
use App\Enums\StockDocumentStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\SerialNumberMismatchException;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\GoodsReceiptService;
use App\Services\StockService;

beforeEach(function (): void {
    actingAsRole(RoleName::Warehouse);

    $this->service = app(GoodsReceiptService::class);
    $this->stock = app(StockService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->supplier = Supplier::factory()->create();
});

function receiptPayload(array $overrides = [], array $items = []): array
{
    return [
        'warehouse_id' => test()->warehouse->id,
        'supplier_id' => test()->supplier->id,
        'reference_no' => 'INV-0001',
        'received_date' => now()->toDateString(),
        'note' => null,
        'items' => $items,
        ...$overrides,
    ];
}

it('สร้างใบรับสินค้าเป็นร่างแล้วยังไม่กระทบสต็อก', function (): void {
    $product = Product::factory()->create();

    $receipt = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '10', 'unit_cost' => '500'],
    ]));

    expect($receipt->receipt_no)->toStartWith('GR-')
        ->and($receipt->status)->toBe(StockDocumentStatus::Draft)
        ->and($receipt->items)->toHaveCount(1)
        ->and((string) $this->stock->levelFor($product, $this->warehouse)->qty_on_hand)->toBe('0.000')
        ->and(StockMovement::count())->toBe(0);
});

it('post ใบรับสินค้าแล้วสต็อกเพิ่มและ ledger ผูกกลับมาที่ใบได้', function (): void {
    $product = Product::factory()->create();

    $receipt = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '10', 'unit_cost' => '500'],
    ]));

    $this->service->post($receipt);

    expect($receipt->refresh()->status)->toBe(StockDocumentStatus::Posted)
        ->and($receipt->posted_at)->not->toBeNull()
        ->and((string) $this->stock->levelFor($product, $this->warehouse)->qty_on_hand)->toBe('10.000');

    $movement = StockMovement::firstOrFail();

    expect($movement->ref_type)->toBe(GoodsReceipt::class)
        ->and($movement->ref_id)->toBe($receipt->id)
        ->and((string) $movement->unit_cost)->toBe('500.00')
        ->and($receipt->movements)->toHaveCount(1);
});

it('post ซ้ำสองครั้งไม่ได้', function (): void {
    $product = Product::factory()->create();

    $receipt = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '5', 'unit_cost' => '100'],
    ]));

    $this->service->post($receipt);

    expect(fn () => $this->service->post($receipt->refresh()))
        ->toThrow(InvalidStatusTransitionException::class);

    // สต็อกต้องไม่ถูกบวกซ้ำ
    expect((string) $this->stock->levelFor($product, $this->warehouse)->qty_on_hand)->toBe('5.000')
        ->and(StockMovement::count())->toBe(1);
});

it('แก้ไขใบที่ post แล้วไม่ได้', function (): void {
    $product = Product::factory()->create();

    $receipt = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '5', 'unit_cost' => '100'],
    ]));

    $this->service->post($receipt);

    expect(fn () => $this->service->updateDraft($receipt->refresh(), receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '999', 'unit_cost' => '100'],
    ])))->toThrow(InvalidStatusTransitionException::class);
});

it('สินค้าที่ติดตาม serial ต้องกรอก serial ครบเท่าจำนวนจึงจะ post ได้', function (): void {
    $product = Product::factory()->serialized()->create();

    $receipt = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '3', 'unit_cost' => '1000', 'serial_numbers' => "SN001\nSN002"],
    ]));

    expect(fn () => $this->service->post($receipt))
        ->toThrow(SerialNumberMismatchException::class);

    // ต้องไม่รับเข้าไปครึ่งใบ
    expect((string) $this->stock->levelFor($product, $this->warehouse)->qty_on_hand)->toBe('0.000')
        ->and(SerialNumber::count())->toBe(0)
        ->and($receipt->refresh()->status)->toBe(StockDocumentStatus::Draft);
});

it('post ใบที่มี serial ครบแล้วสร้าง serial สถานะ in_stock ให้ครบ', function (): void {
    $product = Product::factory()->serialized()->create();

    $receipt = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '3', 'unit_cost' => '1000', 'serial_numbers' => "SN001\nSN002\nSN003"],
    ]));

    $this->service->post($receipt);

    $serials = SerialNumber::where('product_id', $product->id)->get();

    expect($serials)->toHaveCount(3)
        ->and($serials->pluck('serial_no')->all())->toBe(['SN001', 'SN002', 'SN003'])
        ->and($serials->every(fn ($s) => $s->status === SerialStatus::InStock))->toBeTrue()
        ->and($serials->every(fn ($s) => $s->warehouse_id === $this->warehouse->id))->toBeTrue();

    // จำนวน serial ในคลังต้องเท่ากับ qty_on_hand
    expect((string) $this->stock->levelFor($product, $this->warehouse)->qty_on_hand)->toBe('3.000')
        ->and($serials->count())->toBe(3);
});

it('serial ซ้ำกับที่มีอยู่แล้วในระบบ post ไม่ผ่าน', function (): void {
    $product = Product::factory()->serialized()->create();
    SerialNumber::factory()->create(['product_id' => $product->id, 'serial_no' => 'SN001']);

    $receipt = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '1', 'unit_cost' => '1000', 'serial_numbers' => 'SN001'],
    ]));

    expect(fn () => $this->service->post($receipt))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    expect(SerialNumber::count())->toBe(1);
});

it('serial ซ้ำกันเองภายในใบเดียว post ไม่ผ่าน', function (): void {
    $product = Product::factory()->serialized()->create();

    $receipt = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '2', 'unit_cost' => '1000', 'serial_numbers' => "SN001\nSN001"],
    ]));

    expect(fn () => $this->service->post($receipt))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('ยกเลิกใบที่ยังเป็นร่างได้ แต่ยกเลิกใบที่ post แล้วไม่ได้', function (): void {
    $product = Product::factory()->create();

    $draft = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '1', 'unit_cost' => '1'],
    ]));

    $this->service->cancel($draft);
    expect($draft->refresh()->status)->toBe(StockDocumentStatus::Cancelled);

    $posted = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '1', 'unit_cost' => '1'],
    ]));
    $this->service->post($posted);

    expect(fn () => $this->service->cancel($posted->refresh()))
        ->toThrow(InvalidStatusTransitionException::class);
});

it('รับเข้าหลายบรรทัดในใบเดียวเขียน ledger ครบทุกบรรทัด', function (): void {
    $first = Product::factory()->create();
    $second = Product::factory()->create();

    $receipt = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $first->id, 'qty' => '10', 'unit_cost' => '100'],
        ['product_id' => $second->id, 'qty' => '2.500', 'unit_cost' => '250'],
    ]));

    $this->service->post($receipt);

    expect(StockMovement::count())->toBe(2)
        ->and((string) $this->stock->levelFor($first, $this->warehouse)->qty_on_hand)->toBe('10.000')
        ->and((string) $this->stock->levelFor($second, $this->warehouse)->qty_on_hand)->toBe('2.500');
});

it('แก้ไขใบร่างแล้วรายการเดิมถูกแทนที่ ไม่ทับซ้อน', function (): void {
    $product = Product::factory()->create();

    $receipt = $this->service->createDraft(receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '10', 'unit_cost' => '100'],
    ]));

    $this->service->updateDraft($receipt, receiptPayload(items: [
        ['product_id' => $product->id, 'qty' => '7', 'unit_cost' => '120'],
    ]));

    expect($receipt->refresh()->items)->toHaveCount(1)
        ->and((string) $receipt->items->first()->qty)->toBe('7.000')
        ->and((string) $receipt->items->first()->unit_cost)->toBe('120.00');
});
