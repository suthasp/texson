<?php

declare(strict_types=1);

use App\Enums\QuotationItemType;
use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Enums\SettingKey;
use App\Exceptions\Domain\ApprovalRequiredException;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\QuotationService;
use App\Services\SettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * วงจรชีวิตใบเสนอราคาตามสเปกข้อ 4.3 — รวมเคสที่ต้องล้มเหลว
 */
beforeEach(function (): void {
    $this->sales = actingAsRole(RoleName::Sales);
    $this->service = app(QuotationService::class);
    $this->customer = Customer::factory()->create();

    app(SettingService::class)->setMany([
        SettingKey::ApprovalMaxDiscountPercent->value => '15.00',
        SettingKey::ApprovalMinMarginPercent->value => '10.00',
        SettingKey::ApprovalMaxGrandTotal->value => '500000.00',
        SettingKey::QuoteValidDays->value => 30,
    ]);
});

/** @param array<int, array<string, mixed>>|null $items */
function quotePayload(Customer $customer, ?array $items = null, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'price_tier' => 'standard',
        'vat_rate' => '7.00',
        'discount_amount' => '0',
        'items' => $items ?? [[
            'item_type' => QuotationItemType::Product->value,
            'description' => 'UPS 10kVA',
            'qty' => '2',
            'unit_price' => '50000',
            'cost_snapshot' => '35000',
        ]],
    ], $overrides);
}

// ── การสร้างและคำนวณ ────────────────────────────────────

it('สร้างใบร่างพร้อมเลขที่รูปแบบ QT-YYYYMM-#### และคำนวณยอดให้ครบ', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));

    expect($quotation->quote_no)->toMatch('/^QT-\d{6}-\d{4}$/')
        ->and($quotation->revision)->toBe(0)
        ->and($quotation->status)->toBe(QuotationStatus::Draft)
        ->and((string) $quotation->subtotal)->toBe('100000.00')
        ->and((string) $quotation->vat_amount)->toBe('7000.00')
        ->and((string) $quotation->grand_total)->toBe('107000.00')
        ->and($quotation->sales_user_id)->toBe($this->sales->id);
});

it('ต้นทุนมาจากฐานข้อมูลเสมอ ไม่ใช่จากค่าที่ฟอร์มส่งมา', function (): void {
    $product = Product::factory()->create(['cost_price' => '70000', 'list_price' => '100000']);

    $quotation = $this->service->createDraft(quotePayload($this->customer, [[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'description' => 'UPS',
        'qty' => '1',
        'unit_price' => '100000',
        // พยายามปลอมต้นทุนให้ต่ำเพื่อให้ margin ดูสวย
        'cost_snapshot' => '1',
    ]]));

    expect((string) $quotation->items->first()->cost_snapshot)->toBe('70000.00')
        ->and((string) $quotation->cost_total)->toBe('70000.00');
});

it('เก็บ snapshot ของราคาและชื่อสินค้าไว้ในบรรทัด — แก้สินค้าภายหลังไม่กระทบใบเก่า', function (): void {
    $product = Product::factory()->create([
        'sku' => 'UPS-SNAP-1',
        'name_th' => 'ชื่อเดิม',
        'cost_price' => '1000',
        'list_price' => '2000',
    ]);

    $quotation = $this->service->createDraft(quotePayload($this->customer, [[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '1',
    ]]));

    $before = (string) $quotation->grand_total;

    $product->update(['name_th' => 'ชื่อใหม่', 'list_price' => '9999', 'cost_price' => '8888']);

    $item = $quotation->fresh(['items'])->items->first();

    expect($item->sku_snapshot)->toBe('UPS-SNAP-1')
        ->and($item->description)->not->toContain('ชื่อใหม่')
        ->and((string) $item->unit_price)->toBe('2000.00')
        ->and((string) $item->cost_snapshot)->toBe('1000.00')
        ->and((string) $quotation->fresh()->grand_total)->toBe($before);
});

it('ใช้ราคาตามระดับราคาของลูกค้าเมื่อไม่ได้พิมพ์ราคามาเอง', function (): void {
    $product = Product::factory()->create([
        'list_price' => '1000', 'dealer_price' => '900', 'project_price' => '850', 'cost_price' => '500',
    ]);

    $dealer = Customer::factory()->tier(App\Enums\PriceTier::Dealer)->create();

    $quotation = $this->service->createDraft(quotePayload($dealer, [[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '1',
    ]], ['price_tier' => 'dealer']));

    expect((string) $quotation->items->first()->unit_price)->toBe('900.00');
});

// ── การเปลี่ยนสถานะ ─────────────────────────────────────

it('เดินครบวงจร draft → pending_approval → sent → accepted', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));

    $this->service->submit($quotation);
    expect($quotation->fresh()->status)->toBe(QuotationStatus::PendingApproval);

    $this->service->send($quotation);
    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent)
        ->and($quotation->fresh()->sent_at)->not->toBeNull();

    $this->service->accept($quotation);
    expect($quotation->fresh()->status)->toBe(QuotationStatus::Accepted)
        ->and($quotation->fresh()->decided_at)->not->toBeNull();
});

it('ส่งใบที่ไม่เข้าเกณฑ์อนุมัติได้เลยโดยไม่ต้องผ่านคิว', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));

    $this->service->send($quotation);

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent);
});

it('บันทึกเหตุผลที่ลูกค้าปฏิเสธไว้ใน lost_reason', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));
    $this->service->send($quotation);
    $this->service->reject($quotation, 'ราคาสูงกว่าคู่แข่ง 8%');

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Rejected)
        ->and($quotation->fresh()->lost_reason)->toBe('ราคาสูงกว่าคู่แข่ง 8%');
});

it('ใบที่ปิดวงจรแล้วเปลี่ยนสถานะต่อไม่ได้', function (QuotationStatus $closed): void {
    $quotation = Quotation::factory()->status($closed)->create(['customer_id' => $this->customer->id]);

    expect(fn () => $this->service->accept($quotation))->toThrow(InvalidStatusTransitionException::class);
})->with([
    QuotationStatus::Accepted,
    QuotationStatus::Rejected,
    QuotationStatus::Cancelled,
]);

it('อนุมัติใบที่ยังไม่ได้ส่งเข้าคิวไม่ได้', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));

    expect(fn () => $this->service->approve($quotation, $this->sales))
        ->toThrow(InvalidStatusTransitionException::class);
});

it('ตีกลับใบที่รออนุมัติแล้วการอนุมัติเดิมถูกล้าง', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));
    $this->service->submit($quotation);
    $this->service->approve($quotation, $this->sales);

    expect($quotation->fresh()->approved_at)->not->toBeNull();

    $this->service->returnToDraft($quotation, 'ส่วนลดสูงเกินไป');

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Draft)
        ->and($quotation->fresh()->approved_at)->toBeNull()
        ->and($quotation->fresh()->internal_note)->toContain('ส่วนลดสูงเกินไป');
});

it('ส่งเข้าคิวรออนุมัติใหม่แล้วการอนุมัติเดิมถูกล้าง เพราะตัวเลขอาจถูกแก้ไปแล้ว', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));
    $this->service->submit($quotation);
    $this->service->approve($quotation, $this->sales);
    $this->service->returnToDraft($quotation);
    $this->service->submit($quotation);

    expect($quotation->fresh()->approved_at)->toBeNull()
        ->and($quotation->fresh()->approved_by)->toBeNull();
});

// ── เกณฑ์การอนุมัติ (spec 4.3) ──────────────────────────

it('ส่วนลดรวมเกิน 15% ทำให้ต้องอนุมัติก่อนส่ง', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer, [[
        'item_type' => QuotationItemType::Product->value,
        'description' => 'สินค้า',
        'qty' => '1',
        'unit_price' => '10000',
        'discount_percent' => '20',
        'cost_snapshot' => '1000',
    ]]));

    expect($this->service->requiresApproval($quotation))->toBeTrue()
        ->and($this->service->approvalReasons($quotation)[0])->toContain('ส่วนลดรวม');

    $this->service->submit($quotation);

    expect(fn () => $this->service->send($quotation))->toThrow(ApprovalRequiredException::class);
});

it('margin ต่ำกว่า 10% ทำให้ต้องอนุมัติก่อนส่ง', function (): void {
    $product = Product::factory()->create(['cost_price' => '9500', 'list_price' => '10000']);

    $quotation = $this->service->createDraft(quotePayload($this->customer, [[
        'item_type' => QuotationItemType::Product->value,
        'product_id' => $product->id,
        'qty' => '1',
        'unit_price' => '10000',
    ]]));

    expect($this->service->approvalReasons($quotation))->toHaveCount(1)
        ->and($this->service->approvalReasons($quotation)[0])->toContain('margin');
});

it('ยอดสุทธิเกิน 500,000 ทำให้ต้องอนุมัติก่อนส่ง', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer, [[
        'item_type' => QuotationItemType::Product->value,
        'description' => 'ระบบ UPS ทั้งชุด',
        'qty' => '1',
        'unit_price' => '600000',
        'cost_snapshot' => '300000',
    ]]));

    expect($this->service->approvalReasons($quotation))->toHaveCount(1)
        ->and($this->service->approvalReasons($quotation)[0])->toContain('ยอดสุทธิ');
});

it('อนุมัติแล้วส่งได้ และผู้อนุมัติถูกบันทึกไว้', function (): void {
    $manager = userWithRole(RoleName::SalesManager);

    $quotation = $this->service->createDraft(quotePayload($this->customer, [[
        'item_type' => QuotationItemType::Product->value,
        'description' => 'สินค้า',
        'qty' => '1',
        'unit_price' => '600000',
        'cost_snapshot' => '300000',
    ]]));

    $this->service->submit($quotation);
    $this->service->approve($quotation, $manager);

    expect($quotation->fresh()->approved_by)->toBe($manager->id);

    $this->service->send($quotation);

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent);
});

it('ใบที่มีแต่บรรทัดข้อความไม่ติดเกณฑ์ margin เพราะไม่มีต้นทุนให้เทียบ', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer, [[
        'item_type' => QuotationItemType::Note->value,
        'description' => 'รายละเอียดจะแจ้งภายหลัง',
    ]]));

    expect($this->service->approvalReasons($quotation))->toBe([]);
});

// ── Revision (spec 4.3) ─────────────────────────────────

it('สร้าง revision จากใบที่ส่งแล้ว โดยใบเดิมยังอยู่ครบและถูกประทับว่าถูกแทนที่', function (): void {
    $original = $this->service->createDraft(quotePayload($this->customer));
    $this->service->send($original);

    $revision = $this->service->revise($original);

    expect($revision->quote_no)->toBe($original->quote_no)
        ->and($revision->revision)->toBe(1)
        ->and($revision->parent_quotation_id)->toBe($original->id)
        ->and($revision->status)->toBe(QuotationStatus::Draft)
        ->and($revision->items)->toHaveCount($original->items->count())
        ->and((string) $revision->grand_total)->toBe((string) $original->grand_total);

    $original->refresh();

    // ใบเดิมยังเป็น sent เพื่อไม่ให้รายงานยอดเสนอราคาเพี้ยน (ADR-002)
    expect($original->status)->toBe(QuotationStatus::Sent)
        ->and($original->superseded_at)->not->toBeNull()
        ->and($original->displayNo())->toBe($original->quote_no)
        ->and($revision->displayNo())->toBe($original->quote_no.'_rev1');
});

it('revision ซ้อน revision ได้และเลขเพิ่มทีละหนึ่ง', function (): void {
    $original = $this->service->createDraft(quotePayload($this->customer));
    $this->service->send($original);

    $rev1 = $this->service->revise($original);
    $this->service->send($rev1);
    $rev2 = $this->service->revise($rev1);

    expect($rev2->revision)->toBe(2)
        ->and($rev2->parent_quotation_id)->toBe($rev1->id)
        ->and($rev2->quote_no)->toBe($original->quote_no);
});

it('แก้ใบที่ส่งไปแล้วทับของเดิมไม่ได้ ต้องสร้าง revision เท่านั้น', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));
    $this->service->send($quotation);

    expect(fn () => $this->service->updateDraft($quotation, quotePayload($this->customer)))
        ->toThrow(InvalidStatusTransitionException::class);
});

it('สร้าง revision จากใบที่ลูกค้าตอบรับแล้วไม่ได้', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));
    $this->service->send($quotation);
    $this->service->accept($quotation);

    expect(fn () => $this->service->revise($quotation))
        ->toThrow(InvalidStatusTransitionException::class);
});

it('สร้าง revision จากใบที่ถูกปฏิเสธหรือหมดอายุได้ เพื่อเสนอราคาใหม่', function (QuotationStatus $status): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));
    $this->service->send($quotation);
    $quotation->update(['status' => $status]);

    expect($this->service->revise($quotation->refresh())->revision)->toBe(1);
})->with([QuotationStatus::Rejected, QuotationStatus::Expired]);

// ── หมดอายุอัตโนมัติ (spec 4.3) ─────────────────────────

it('job เปลี่ยนเฉพาะใบที่ส่งแล้วและเลยวันยืนราคา', function (): void {
    $overdue = Quotation::factory()->overdue()->create(['customer_id' => $this->customer->id]);
    $stillValid = Quotation::factory()->status(QuotationStatus::Sent)->create(['customer_id' => $this->customer->id]);
    $draftOverdue = Quotation::factory()->create([
        'customer_id' => $this->customer->id,
        'valid_until' => Carbon::now()->subDay()->toDateString(),
    ]);

    expect($this->service->expireOverdue())->toBe(1);

    expect($overdue->fresh()->status)->toBe(QuotationStatus::Expired)
        ->and($stillValid->fresh()->status)->toBe(QuotationStatus::Sent)
        // ใบร่างที่เลยวันไม่ถูกแตะ — ยังไม่เคยเสนอให้ลูกค้า
        ->and($draftOverdue->fresh()->status)->toBe(QuotationStatus::Draft);
});

it('ใบที่หมดอายุวันนี้พอดียังไม่ถูกเปลี่ยน', function (): void {
    $today = Quotation::factory()->status(QuotationStatus::Sent)->create([
        'customer_id' => $this->customer->id,
        'valid_until' => Carbon::now()->toDateString(),
    ]);

    expect($this->service->expireOverdue())->toBe(0)
        ->and($today->fresh()->status)->toBe(QuotationStatus::Sent);
});

it('คำสั่ง quotations:expire รันได้และรายงานจำนวนที่เปลี่ยน', function (): void {
    Quotation::factory()->overdue()->count(2)->create(['customer_id' => $this->customer->id]);

    $this->artisan('quotations:expire')
        ->expectsOutputToContain('2')
        ->assertExitCode(0);

    expect(Quotation::where('status', QuotationStatus::Expired)->count())->toBe(2);
});

// ── เลขที่เอกสาร ────────────────────────────────────────

it('เลขที่ใบเสนอราคาไม่ซ้ำกันแม้ออกติดกันหลายใบ', function (): void {
    $numbers = collect(range(1, 20))
        ->map(fn (): string => $this->service->createDraft(quotePayload($this->customer))->quote_no);

    expect($numbers->unique())->toHaveCount(20);
});

// ── Activity log (spec 8) ───────────────────────────────

it('บันทึกการเปลี่ยนสถานะลง activity log พร้อมค่าก่อนและหลัง', function (): void {
    $quotation = $this->service->createDraft(quotePayload($this->customer));
    $this->service->send($quotation);

    $log = Spatie\Activitylog\Models\Activity::query()
        ->where('subject_type', Quotation::class)
        ->where('subject_id', $quotation->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['old']['status'])->toBe(QuotationStatus::Draft->value)
        ->and($log->properties['attributes']['status'])->toBe(QuotationStatus::Sent->value)
        ->and($log->causer_id)->toBe($this->sales->id);
});

it('ผู้ขายที่ระบุไว้ยังคงเป็นเจ้าของใบแม้ผู้อื่นเป็นคนสร้าง', function (): void {
    $otherSales = User::factory()->create();

    Auth::login($this->sales);

    $quotation = $this->service->createDraft(quotePayload($this->customer, null, [
        'sales_user_id' => $otherSales->id,
    ]));

    expect($quotation->sales_user_id)->toBe($otherSales->id)
        ->and($quotation->created_by)->toBe($this->sales->id);
});
