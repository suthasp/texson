<?php

declare(strict_types=1);

use App\Enums\DocType;
use App\Models\NumberSequence;
use App\Services\NumberSequenceService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->numbers = app(NumberSequenceService::class);
});

it('ออกเลขตามรูปแบบ PREFIX-YYYYMM-####', function (): void {
    Carbon::setTestNow('2026-08-27 10:00:00');

    expect($this->numbers->next(DocType::GoodsReceipt))->toBe('GR-202608-0001')
        ->and($this->numbers->next(DocType::GoodsReceipt))->toBe('GR-202608-0002')
        ->and($this->numbers->next(DocType::StockAdjustment))->toBe('ADJ-202608-0001');

    Carbon::setTestNow();
});

it('running รีเซ็ตเมื่อขึ้นเดือนใหม่', function (): void {
    Carbon::setTestNow('2026-08-31 23:59:00');
    expect($this->numbers->next(DocType::GoodsReceipt))->toBe('GR-202608-0001');

    Carbon::setTestNow('2026-09-01 00:01:00');
    expect($this->numbers->next(DocType::GoodsReceipt))->toBe('GR-202609-0001');

    Carbon::setTestNow();
});

it('เอกสารคนละประเภทนับแยกกัน', function (): void {
    Carbon::setTestNow('2026-08-27 10:00:00');

    $this->numbers->next(DocType::GoodsReceipt);
    $this->numbers->next(DocType::GoodsReceipt);
    $this->numbers->next(DocType::GoodsReceipt);

    expect($this->numbers->next(DocType::StockTransfer))->toBe('TR-202608-0001')
        ->and($this->numbers->next(DocType::GoodsReceipt))->toBe('GR-202608-0004');

    Carbon::setTestNow();
});

/**
 * เคสที่ spec ข้อ 4.1 ระบุไว้ตรง ๆ: ยิงพร้อมกัน 20 ครั้งต้องไม่ได้เลขซ้ำ
 *
 * PHP รันเทสต์แบบ single thread จึงจำลอง "พร้อมกัน" ด้วยการยิงติดกันรัว ๆ
 * แล้วพิสูจน์สองอย่าง: ไม่มีเลขซ้ำ และเลขต่อเนื่องไม่มีช่องว่าง
 * ส่วนการกันชนจริงระหว่าง process มาจาก lockForUpdate ในทรานแซกชัน
 */
it('ยิงขอเลข 20 ครั้งติดกันต้องไม่ได้เลขซ้ำและไม่มีเลขข้าม', function (): void {
    Carbon::setTestNow('2026-08-27 10:00:00');

    $issued = [];

    foreach (range(1, 20) as $ignored) {
        $issued[] = $this->numbers->next(DocType::GoodsReceipt);
    }

    expect($issued)->toHaveCount(20)
        ->and(array_unique($issued))->toHaveCount(20)
        ->and($issued[0])->toBe('GR-202608-0001')
        ->and($issued[19])->toBe('GR-202608-0020');

    // ตัวนับในตารางต้องตรงกับจำนวนที่ออกไปจริง
    expect(NumberSequence::where('doc_type', 'GR')->where('period', '202608')->value('last_no'))->toBe(20);

    Carbon::setTestNow();
});

it('ออกเลขพร้อมกันจากหลายทรานแซกชันซ้อนกันก็ไม่ชน', function (): void {
    Carbon::setTestNow('2026-08-27 10:00:00');

    $issued = [];

    // เลียนแบบผู้ใช้หลายคนกดสร้างเอกสารในเวลาใกล้กัน โดยแต่ละครั้งอยู่ในทรานแซกชันของตัวเอง
    foreach (range(1, 10) as $ignored) {
        $issued[] = DB::transaction(fn (): string => $this->numbers->next(DocType::StockAdjustment));
    }

    expect(array_unique($issued))->toHaveCount(10);

    Carbon::setTestNow();
});

it('สร้างแถวตัวนับเองอัตโนมัติเมื่อยังไม่มีของเดือนนั้น', function (): void {
    Carbon::setTestNow('2026-12-01 08:00:00');

    expect(NumberSequence::count())->toBe(0);

    $this->numbers->next(DocType::StockTransfer);

    expect(NumberSequence::where('doc_type', 'TR')->where('period', '202612')->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('lastIssued อ่านเลขล่าสุดได้โดยไม่กินเลข', function (): void {
    Carbon::setTestNow('2026-08-27 10:00:00');

    $this->numbers->next(DocType::GoodsReceipt);
    $this->numbers->next(DocType::GoodsReceipt);

    expect($this->numbers->lastIssued(DocType::GoodsReceipt))->toBe(2)
        ->and($this->numbers->next(DocType::GoodsReceipt))->toBe('GR-202608-0003');

    Carbon::setTestNow();
});

it('DocType เตรียมรองรับ WO และ SR ของ Phase 2 ได้โดยไม่ต้องแก้ตาราง', function (): void {
    // number_sequences.doc_type เก็บเป็น string ไม่ใช่ enum ของ DB
    $column = DB::getSchemaBuilder()->getColumnType('number_sequences', 'doc_type');

    expect($column)->toBeIn(['string', 'varchar']);
});
