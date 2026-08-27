<?php

declare(strict_types=1);

use App\Enums\QuotationItemType;
use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

/**
 * ใบเสนอราคาผ่าน REST API (spec 6)
 */

/** @return array<string, mixed> */
function apiQuotePayload(Customer $customer, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'price_tier' => 'standard',
        'vat_rate' => '7.00',
        'discount_amount' => '0',
        'items' => [[
            'item_type' => QuotationItemType::Product->value,
            'product_id' => Product::factory()->create(['cost_price' => '15000'])->id,
            'description' => 'UPS 10kVA',
            'qty' => '2',
            'unit_price' => '25000',
        ]],
    ], $overrides);
}

// ── CRUD ────────────────────────────────────────────────

it('สร้างใบเสนอราคาผ่าน API ได้ 201 พร้อมยอดที่คำนวณแล้ว', function (): void {
    $sales = userWithRole(RoleName::Sales);
    Sanctum::actingAs($sales);

    $response = $this->postJson('/api/v1/quotations', apiQuotePayload(Customer::factory()->create()));

    $response->assertStatus(201)
        ->assertJsonPath('data.status.value', QuotationStatus::Draft->value)
        ->assertJsonPath('data.revision', 0)
        ->assertJsonPath('data.totals.subtotal', '50000.00')
        ->assertJsonPath('data.totals.vat_amount', '3500.00')
        ->assertJsonPath('data.totals.grand_total', '53500.00')
        ->assertJsonPath('data.totals.grand_total_in_words_th', 'ห้าหมื่นสามพันห้าร้อยบาทถ้วน');

    expect($response->json('data.quote_no'))->toMatch('/^QT-\d{6}-\d{4}$/')
        ->and(Quotation::firstOrFail()->sales_user_id)->toBe($sales->id);
});

it('รายการใบเสนอราคาของ sales เห็นเฉพาะของตัวเอง (spec 8)', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    Quotation::factory()->forSales($mine)->create(['quote_no' => 'QT-202608-0001']);
    Quotation::factory()->forSales($theirs)->create(['quote_no' => 'QT-202608-0002']);

    Sanctum::actingAs($mine);

    $response = $this->getJson('/api/v1/quotations')->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.quote_no'))->toBe('QT-202608-0001');
});

it('ผู้จัดการฝ่ายขายเห็นใบของทุกคนผ่าน API', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $manager = userWithRole(RoleName::SalesManager);

    Quotation::factory()->forSales($sales)->count(2)->create();

    Sanctum::actingAs($manager);

    expect($this->getJson('/api/v1/quotations')->assertOk()->json('meta.total'))->toBe(2);
});

it('เปิดใบของ sales คนอื่นผ่าน API ไม่ได้ (403)', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    $quotation = Quotation::factory()->forSales($theirs)->create();

    Sanctum::actingAs($mine);

    $this->getJson("/api/v1/quotations/{$quotation->id}")->assertStatus(403);
});

it('กรองตามสถานะ ลูกค้า และช่วงวันที่ได้ตามสเปกข้อ 6', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $customer = Customer::factory()->create();

    Quotation::factory()->forSales($sales)->status(QuotationStatus::Sent)->create([
        'customer_id' => $customer->id,
        'issue_date' => now()->subDays(3)->toDateString(),
        'quote_no' => 'QT-202608-1001',
    ]);
    Quotation::factory()->forSales($sales)->status(QuotationStatus::Draft)->create([
        'issue_date' => now()->subDays(40)->toDateString(),
        'quote_no' => 'QT-202607-1002',
    ]);

    Sanctum::actingAs($sales);

    expect($this->getJson('/api/v1/quotations?status=sent')->json('meta.total'))->toBe(1)
        ->and($this->getJson("/api/v1/quotations?customer_id={$customer->id}")->json('meta.total'))->toBe(1)
        ->and($this->getJson('/api/v1/quotations?from='.now()->subDays(7)->toDateString())->json('meta.total'))->toBe(1);
});

it('แก้ใบร่างผ่าน PUT ได้ และยอดถูกคำนวณใหม่', function (): void {
    $sales = userWithRole(RoleName::Sales);
    Sanctum::actingAs($sales);

    $quotation = app(QuotationService::class)->createDraft(apiQuotePayload(Customer::factory()->create()));

    $response = $this->putJson("/api/v1/quotations/{$quotation->id}", apiQuotePayload($quotation->customer, [
        'items' => [[
            'item_type' => QuotationItemType::Labour->value,
            'description' => 'ค่าแรงติดตั้ง',
            'qty' => '1',
            'unit_price' => '10000',
        ]],
    ]));

    $response->assertOk()
        ->assertJsonPath('data.totals.subtotal', '10000.00')
        ->assertJsonPath('data.totals.grand_total', '10700.00');

    expect($quotation->fresh()->items)->toHaveCount(1);
});

it('แก้ใบที่ส่งไปแล้วตอบ 409 ไม่ใช่ 403 — เป็นปัญหาลำดับการทำงาน ไม่ใช่เรื่องสิทธิ์', function (): void {
    $sales = userWithRole(RoleName::Sales);
    Sanctum::actingAs($sales);

    $service = app(QuotationService::class);
    $quotation = $service->createDraft(apiQuotePayload(Customer::factory()->create()));
    $service->send($quotation);

    $this->putJson("/api/v1/quotations/{$quotation->id}", apiQuotePayload($quotation->customer))
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'document', 'from', 'to']);
});

it('แก้ใบของคนอื่นตอบ 403 ไม่ใช่ 409', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    $quotation = Quotation::factory()->forSales($theirs)->create();

    Sanctum::actingAs($mine);

    $this->putJson("/api/v1/quotations/{$quotation->id}", apiQuotePayload(Customer::factory()->create()))
        ->assertStatus(403);
});

it('payload ที่ผิดตอบ 422 พร้อม errors (spec 6)', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $this->postJson('/api/v1/quotations', ['items' => []])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors'])
        ->assertJsonValidationErrors(['customer_id', 'issue_date', 'valid_until', 'items']);
});

// ── วงจรชีวิต ───────────────────────────────────────────

it('เดินครบวงจร submit → approve → send → accept ผ่าน API', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $manager = userWithRole(RoleName::SalesManager);

    Sanctum::actingAs($sales);

    $quotation = app(QuotationService::class)->createDraft(apiQuotePayload(Customer::factory()->create()));

    $this->postJson("/api/v1/quotations/{$quotation->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status.value', QuotationStatus::PendingApproval->value);

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/quotations/{$quotation->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.approved_by', $manager->name);

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($sales);

    $this->postJson("/api/v1/quotations/{$quotation->id}/send")
        ->assertOk()
        ->assertJsonPath('data.status.value', QuotationStatus::Sent->value)
        ->assertJsonPath('meta.emailed_to', null);

    $this->postJson("/api/v1/quotations/{$quotation->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status.value', QuotationStatus::Accepted->value);
});

it('ส่งใบที่เข้าเกณฑ์ต้องอนุมัติโดยยังไม่อนุมัติ ตอบ 409 พร้อมเหตุผล', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $quotation = app(QuotationService::class)->createDraft(apiQuotePayload(Customer::factory()->create(), [
        'items' => [[
            'item_type' => QuotationItemType::Product->value,
            'product_id' => Product::factory()->create(['cost_price' => '600000'])->id,
            'description' => 'ระบบ UPS ทั้งชุด',
            'qty' => '1',
            'unit_price' => '900000',
        ]],
    ]));

    $response = $this->postJson("/api/v1/quotations/{$quotation->id}/send")
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'approval_reasons']);

    expect($response->json('approval_reasons.0'))->toContain('ยอดสุทธิ')
        ->and($quotation->fresh()->status)->toBe(QuotationStatus::Draft);
});

it('เปลี่ยนสถานะข้ามขั้นตอบ 409 ทุก endpoint (spec 6)', function (string $action): void {
    $sales = userWithRole(RoleName::Sales);
    Sanctum::actingAs($sales);

    // ใบที่ลูกค้าตอบรับแล้วปิดวงจรไปแล้ว ทำอะไรต่อไม่ได้
    $quotation = Quotation::factory()->forSales($sales)->status(QuotationStatus::Accepted)->create();

    // ผู้เรียกมีสิทธิ์และเป็นเจ้าของใบ — ที่ผิดคือ "สถานะ" จึงต้องเป็น 409 ไม่ใช่ 403
    $this->postJson("/api/v1/quotations/{$quotation->id}/{$action}")
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'document', 'from', 'to']);
})->with(['submit', 'send', 'accept', 'reject', 'cancel']);

it('ใบที่ปิดวงจรแล้วสร้าง revision ไม่ได้ และตอบ 409 เช่นกัน', function (): void {
    $sales = userWithRole(RoleName::Sales);
    Sanctum::actingAs($sales);

    $quotation = Quotation::factory()->forSales($sales)->status(QuotationStatus::Accepted)->create();

    $this->postJson("/api/v1/quotations/{$quotation->id}/revise")->assertStatus(409);
});

it('อนุมัติใบที่ยังไม่ได้ส่งเข้าคิวตอบ 409 แต่อนุมัติใบตัวเองตอบ 403', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $manager = userWithRole(RoleName::SalesManager);

    $draft = Quotation::factory()->forSales($sales)->create();

    Sanctum::actingAs($manager);

    // สถานะผิด → 409
    $this->postJson("/api/v1/quotations/{$draft->id}/approve")->assertStatus(409);

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($manager);

    // ใบของตัวเอง → เป็นข้อห้ามเชิงสิทธิ์ จึงเป็น 403 ไม่ใช่ 409
    $own = Quotation::factory()->forSales($manager)->status(QuotationStatus::PendingApproval)->create();

    $this->postJson("/api/v1/quotations/{$own->id}/approve")->assertStatus(403);
});

it('ระบุอีเมลตอนส่ง แล้วระบบส่งเมลพร้อม PDF แนบ', function (): void {
    Mail::fake();

    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $quotation = app(QuotationService::class)->createDraft(apiQuotePayload(Customer::factory()->create()));

    $this->postJson("/api/v1/quotations/{$quotation->id}/send", [
        'email' => 'buyer@example.com',
        'locale' => 'en',
    ])->assertOk()->assertJsonPath('meta.emailed_to', 'buyer@example.com');

    Mail::assertSent(App\Mail\QuotationMail::class, fn ($mail): bool => $mail->hasTo('buyer@example.com')
        && $mail->pdfLocale === 'en');
});

it('บันทึกเหตุผลตอนลูกค้าปฏิเสธผ่าน API', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $service = app(QuotationService::class);
    $quotation = $service->createDraft(apiQuotePayload(Customer::factory()->create()));
    $service->send($quotation);

    $this->postJson("/api/v1/quotations/{$quotation->id}/reject", ['reason' => 'ราคาสูงกว่าคู่แข่ง'])
        ->assertOk()
        ->assertJsonPath('data.status.value', QuotationStatus::Rejected->value)
        ->assertJsonPath('data.lost_reason', 'ราคาสูงกว่าคู่แข่ง');
});

it('สร้าง revision ผ่าน API แล้วใบเดิมถูกประทับว่าถูกแทนที่', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $service = app(QuotationService::class);
    $quotation = $service->createDraft(apiQuotePayload(Customer::factory()->create()));
    $service->send($quotation);

    // 201 เพราะ revision เป็นใบใหม่คนละใบกับใบเดิม ไม่ใช่การแก้ใบเดิม
    $response = $this->postJson("/api/v1/quotations/{$quotation->id}/revise")->assertStatus(201);

    expect($response->json('data.revision'))->toBe(1)
        ->and($response->json('data.quote_no'))->toBe($quotation->quote_no)
        ->and($response->json('data.display_no'))->toBe($quotation->quote_no.'_rev1')
        ->and($response->json('meta.superseded_quotation_id'))->toBe($quotation->id)
        ->and($quotation->fresh()->superseded_at)->not->toBeNull();
});

it('sales อนุมัติใบของตัวเองผ่าน API ไม่ได้', function (): void {
    $sales = userWithRole(RoleName::Sales);
    Sanctum::actingAs($sales);

    $quotation = Quotation::factory()->forSales($sales)->status(QuotationStatus::PendingApproval)->create();

    $this->postJson("/api/v1/quotations/{$quotation->id}/approve")->assertStatus(403);
});

// ── PDF ─────────────────────────────────────────────────

it('ดาวน์โหลด PDF ผ่าน API ได้ทั้งไทยและอังกฤษ', function (string $lang, string $suffix): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $quotation = app(QuotationService::class)->createDraft(apiQuotePayload(Customer::factory()->create()));

    $response = $this->get("/api/v1/quotations/{$quotation->id}/pdf?lang={$lang}");

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain($quotation->quote_no.$suffix.'.pdf')
        ->and($response->getContent())->toStartWith('%PDF-');
})->with([
    ['th', ''],
    ['en', '_EN'],
]);

it('พิมพ์ใบของ sales คนอื่นผ่าน API ไม่ได้', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    $quotation = Quotation::factory()->forSales($theirs)->create();

    Sanctum::actingAs($mine);

    $this->getJson("/api/v1/quotations/{$quotation->id}/pdf")->assertStatus(403);
});

// ── รูปแบบ response ─────────────────────────────────────

it('meta.can บอกว่าทำอะไรกับใบนี้ได้บ้างตามสถานะปัจจุบัน', function (): void {
    $sales = userWithRole(RoleName::Sales);
    Sanctum::actingAs($sales);

    $service = app(QuotationService::class);
    $quotation = $service->createDraft(apiQuotePayload(Customer::factory()->create()));

    $draft = $this->getJson("/api/v1/quotations/{$quotation->id}")->assertOk();

    expect($draft->json('meta.can.update'))->toBeTrue()
        ->and($draft->json('meta.can.submit'))->toBeTrue()
        ->and($draft->json('meta.can.revise'))->toBeFalse()
        ->and($draft->json('meta.can.approve'))->toBeFalse();

    $service->send($quotation);

    $sent = $this->getJson("/api/v1/quotations/{$quotation->id}")->assertOk();

    expect($sent->json('meta.can.update'))->toBeFalse()
        ->and($sent->json('meta.can.revise'))->toBeTrue()
        ->and($sent->json('meta.can.decide'))->toBeTrue();
});

it('ผู้ที่เห็นราคาทุนได้เท่านั้นที่ได้ margin กลับมาใน response', function (): void {
    $sales = userWithRole(RoleName::Sales);
    Sanctum::actingAs($sales);

    $quotation = app(QuotationService::class)->createDraft(apiQuotePayload(Customer::factory()->create()));

    $withCost = $this->getJson("/api/v1/quotations/{$quotation->id}")->assertOk();

    expect($withCost->json('data.margin.percent'))->not->toBeNull()
        ->and($withCost->json('data.items.0.cost.unit'))->toBe('15000.00');
});

it('ค่า decimal ทุกตัวเป็น string ไม่ใช่ float', function (): void {
    Sanctum::actingAs(userWithRole(RoleName::Sales));

    $quotation = app(QuotationService::class)->createDraft(apiQuotePayload(Customer::factory()->create()));

    $totals = $this->getJson("/api/v1/quotations/{$quotation->id}")->assertOk()->json('data.totals');

    foreach (['subtotal', 'after_discount', 'vat_amount', 'grand_total'] as $field) {
        // ถ้าเป็น float ฝั่ง JS จะปัดเศษเงินเพี้ยนเงียบ ๆ
        expect($totals[$field])->toBeString();
    }
});
