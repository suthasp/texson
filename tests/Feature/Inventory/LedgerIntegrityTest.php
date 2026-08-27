<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * DoD ของ Phase 2: ยอดคงเหลือต้องเท่ากับผลรวมของ ledger เสมอ
 */

/**
 * ตรวจ invariant กับทุกคู่ (สินค้า, คลัง) ที่มีอยู่ในระบบ
 */
function assertLedgerMatchesLevels(): void
{
    $levels = StockLevel::all();

    expect($levels)->not->toBeEmpty();

    foreach ($levels as $level) {
        $ledgerSum = StockMovement::query()
            ->where('product_id', $level->product_id)
            ->where('warehouse_id', $level->warehouse_id)
            ->sum('qty');

        expect(number_format((float) $ledgerSum, 3, '.', ''))
            ->toBe((string) $level->qty_on_hand);
    }
}

beforeEach(function (): void {
    actingAsRole(RoleName::Warehouse);

    $this->stock = app(StockService::class);
    $this->product = Product::factory()->create(['min_stock' => 5]);
    $this->warehouse = Warehouse::factory()->create();
});

it('ยอดคงเหลือเท่ากับผลรวม ledger หลังรับเข้าครั้งเดียว', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '10.000');

    assertLedgerMatchesLevels();

    expect((string) $this->stock->levelFor($this->product, $this->warehouse)->qty_on_hand)->toBe('10.000');
});

it('ยอดคงเหลือเท่ากับผลรวม ledger หลังทำรายการปนกันหลายแบบ', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '100.000');
    $this->stock->issue($this->product, $this->warehouse, '30.500');
    $this->stock->receive($this->product, $this->warehouse, '12.250');
    $this->stock->adjustBy($this->product, $this->warehouse, '-1.750');
    $this->stock->returnIn($this->product, $this->warehouse, '5.000');
    $this->stock->adjustBy($this->product, $this->warehouse, '4.000');

    assertLedgerMatchesLevels();

    // 100 - 30.5 + 12.25 - 1.75 + 5 + 4
    expect((string) $this->stock->levelFor($this->product, $this->warehouse)->qty_on_hand)->toBe('89.000')
        ->and(StockMovement::count())->toBe(6);
});

it('balance_after ของแต่ละรายการตรงกับยอดสะสม ณ จังหวะนั้น', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '10.000');
    $this->stock->receive($this->product, $this->warehouse, '5.500');
    $this->stock->issue($this->product, $this->warehouse, '3.250');

    $balances = StockMovement::query()->orderBy('id')->pluck('balance_after')->all();

    expect($balances)->toBe(['10.000', '15.500', '12.250']);
});

it('เศษทศนิยมไม่เพี้ยนสะสม เพราะคำนวณด้วย bcmath ไม่ใช่ float', function (): void {
    // 0.1 + 0.2 ด้วย float จะได้ 0.30000000000000004
    foreach (range(1, 30) as $ignored) {
        $this->stock->receive($this->product, $this->warehouse, '0.001');
    }

    expect((string) $this->stock->levelFor($this->product, $this->warehouse)->qty_on_hand)->toBe('0.030');

    assertLedgerMatchesLevels();
});

it('ตัดสต็อกเกินที่มีอยู่แล้วโยน InsufficientStockException และไม่แตะยอดเลย', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '5.000');

    expect(fn () => $this->stock->issue($this->product, $this->warehouse, '5.001'))
        ->toThrow(InsufficientStockException::class);

    expect((string) $this->stock->levelFor($this->product, $this->warehouse)->qty_on_hand)->toBe('5.000')
        ->and(StockMovement::where('type', 'issue')->count())->toBe(0);

    assertLedgerMatchesLevels();
});

it('ตัดสต็อกพอดีเป๊ะจนเหลือศูนย์ได้', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '5.000');
    $this->stock->issue($this->product, $this->warehouse, '5.000');

    expect((string) $this->stock->levelFor($this->product, $this->warehouse)->qty_on_hand)->toBe('0.000');

    assertLedgerMatchesLevels();
});

it('InsufficientStockException บอกได้ว่าขาดที่สินค้าไหน คลังไหน เท่าไร', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '2.000');

    try {
        $this->stock->issue($this->product, $this->warehouse, '10.000');
        $this->fail('ควรโยน InsufficientStockException');
    } catch (InsufficientStockException $e) {
        expect($e->httpStatus())->toBe(422)
            // ข้อความสำหรับคนอ่านตัดศูนย์ท้ายทิ้งให้อ่านง่าย
            ->and($e->getMessage())->toContain($this->product->sku)
            ->and($e->getMessage())->toContain('ขอตัด 10')
            // แต่ context ที่ส่งให้ API เก็บทศนิยมครบตามที่บันทึกจริง
            ->and($e->context()['shortages'][0]['requested'])->toBe('10.000')
            ->and($e->context()['shortages'][0]['available'])->toBe('2.000')
            ->and($e->context()['shortages'][0]['sku'])->toBe($this->product->sku)
            ->and($e->context()['shortages'][0]['warehouse_code'])->toBe($this->warehouse->code);
    }
});

it('โอนคลังย้ายของครบและ ledger ทั้งสองฝั่งตรงกับยอด', function (): void {
    $to = Warehouse::factory()->create();

    $this->stock->receive($this->product, $this->warehouse, '20.000');
    $this->stock->transfer($this->product, $this->warehouse, $to, '8.000');

    expect((string) $this->stock->levelFor($this->product, $this->warehouse)->qty_on_hand)->toBe('12.000')
        ->and((string) $this->stock->levelFor($this->product, $to)->qty_on_hand)->toBe('8.000');

    assertLedgerMatchesLevels();

    // ของไม่งอกและไม่หายจากระบบโดยรวม
    expect(number_format((float) StockMovement::sum('qty'), 3, '.', ''))->toBe('20.000');
});

it('โอนแล้วคลังต้นทางไม่พอ ต้อง rollback ทั้งคู่ ไม่มีของงอกที่ปลายทาง', function (): void {
    $to = Warehouse::factory()->create();

    $this->stock->receive($this->product, $this->warehouse, '3.000');

    expect(fn () => $this->stock->transfer($this->product, $this->warehouse, $to, '10.000'))
        ->toThrow(InsufficientStockException::class);

    expect((string) $this->stock->levelFor($this->product, $this->warehouse)->qty_on_hand)->toBe('3.000')
        ->and((string) $this->stock->levelFor($this->product, $to)->qty_on_hand)->toBe('0.000')
        ->and(StockMovement::whereIn('type', ['transfer_in', 'transfer_out'])->count())->toBe(0);

    assertLedgerMatchesLevels();
});

it('adjustBy ที่ผลต่างเป็นศูนย์ไม่เขียน ledger เปล่า ๆ', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '5.000');

    expect($this->stock->adjustBy($this->product, $this->warehouse, '0.000'))->toBeNull()
        ->and(StockMovement::count())->toBe(1);
});

it('ledger เป็น append-only — แก้ไขรายการเดิมไม่ได้', function (): void {
    $movement = $this->stock->receive($this->product, $this->warehouse, '5.000');

    expect(fn () => $movement->update(['qty' => '999']))->toThrow(LogicException::class);

    expect((string) $movement->fresh()->qty)->toBe('5.000');
});

it('ledger เป็น append-only — ลบรายการไม่ได้', function (): void {
    $movement = $this->stock->receive($this->product, $this->warehouse, '5.000');

    expect(fn () => $movement->delete())->toThrow(LogicException::class);

    expect(StockMovement::count())->toBe(1);
});

it('ทุก movement บันทึกว่าใครเป็นคนทำ', function (): void {
    $movement = $this->stock->receive($this->product, $this->warehouse, '5.000');

    expect($movement->user_id)->toBe(auth()->id());
});

it('จำนวนที่เคลื่อนไหวต้องมากกว่า 0 เสมอ', function (string $qty): void {
    expect(fn () => $this->stock->receive($this->product, $this->warehouse, $qty))
        ->toThrow(InvalidArgumentException::class);
})->with(['0', '0.000', '-5']);

it('การจองไม่ลดยอดคงเหลือ แต่ลดยอดพร้อมขาย', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '10.000');
    $shortage = $this->stock->reserve($this->product, $this->warehouse, '4.000');

    $level = $this->stock->levelFor($this->product, $this->warehouse)->refresh();

    expect($shortage)->toBe('0.000')
        ->and((string) $level->qty_on_hand)->toBe('10.000')
        ->and((string) $level->qty_reserved)->toBe('4.000')
        ->and($level->qty_available)->toBe('6.000');

    assertLedgerMatchesLevels();
});

it('จองเกินของที่มีได้แต่ต้องคืนจำนวนที่ขาดกลับมาเป็น backorder', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '3.000');

    $shortage = $this->stock->reserve($this->product, $this->warehouse, '10.000');

    expect($shortage)->toBe('7.000')
        ->and((string) $this->stock->levelFor($this->product, $this->warehouse)->refresh()->qty_on_hand)->toBe('3.000');
});

it('คืนของที่จองไว้แล้วยอดจองไม่ติดลบแม้คืนซ้ำ', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '10.000');
    $this->stock->reserve($this->product, $this->warehouse, '4.000');

    $this->stock->release($this->product, $this->warehouse, '4.000');
    $this->stock->release($this->product, $this->warehouse, '4.000');

    expect((string) $this->stock->levelFor($this->product, $this->warehouse)->refresh()->qty_reserved)->toBe('0.000');
});

it('ยอดคงเหลือของแต่ละคลังแยกกันจริง ไม่ปนกัน', function (): void {
    $other = Warehouse::factory()->create();

    $this->stock->receive($this->product, $this->warehouse, '10.000');
    $this->stock->receive($this->product, $other, '25.000');

    expect((string) $this->stock->levelFor($this->product, $this->warehouse)->qty_on_hand)->toBe('10.000')
        ->and((string) $this->stock->levelFor($this->product, $other)->qty_on_hand)->toBe('25.000');

    assertLedgerMatchesLevels();
});

it('ledgerBalance คำนวณจาก ledger ตรง ๆ และตรงกับยอดสรุป', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '17.500');
    $this->stock->issue($this->product, $this->warehouse, '2.500');

    expect($this->stock->ledgerBalance($this->product, $this->warehouse))->toBe('15.000')
        ->and((string) $this->stock->levelFor($this->product, $this->warehouse)->qty_on_hand)->toBe('15.000');
});

it('การเคลื่อนไหวทั้งหมดอยู่ในทรานแซกชันเดียวกับการอัปเดตยอด', function (): void {
    $this->stock->receive($this->product, $this->warehouse, '10.000');

    // จำลองความล้มเหลวกลางคัน — ทั้งยอดและ ledger ต้องไม่เปลี่ยน
    try {
        DB::transaction(function (): void {
            $this->stock->issue($this->product, $this->warehouse, '4.000');

            throw new RuntimeException('พังกลางคัน');
        });
    } catch (RuntimeException) {
        // ตั้งใจให้พัง
    }

    expect((string) $this->stock->levelFor($this->product, $this->warehouse)->qty_on_hand)->toBe('10.000')
        ->and(StockMovement::count())->toBe(1);

    assertLedgerMatchesLevels();
});
