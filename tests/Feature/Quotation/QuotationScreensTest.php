<?php

declare(strict_types=1);

use App\Enums\QuotationItemType;
use App\Enums\QuotationStatus;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Support\Facades\Mail;

/**
 * ใบเสนอราคาผ่าน HTTP — สิทธิ์ การมองเห็น และ validation
 */

/** @return array<string, mixed> */
function quoteForm(Customer $customer, array $overrides = []): array
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
            // ต้นทุนตายตัวเพื่อให้ margin คงที่ ไม่งั้นราคาสุ่มของ factory
            // จะทำให้ใบเข้าเกณฑ์ต้องอนุมัติแบบสุ่ม แล้วเทสต์กะพริบ
            'product_id' => Product::factory()->create(['cost_price' => '15000'])->id,
            'description' => 'UPS 10kVA',
            'qty' => '2',
            'unit_price' => '25000',
        ]],
    ], $overrides);
}

// ── CRUD ────────────────────────────────────────────────

it('ฝ่ายขายสร้างใบเสนอราคาผ่านหน้าเว็บได้', function (): void {
    $sales = actingAsRole(RoleName::Sales);
    $customer = Customer::factory()->create();

    $this->post(route('quotations.store'), quoteForm($customer))->assertRedirect();

    $quotation = Quotation::firstOrFail();

    expect($quotation->status)->toBe(QuotationStatus::Draft)
        ->and($quotation->sales_user_id)->toBe($sales->id)
        ->and((string) $quotation->grand_total)->toBe('53500.00');
});

it('ใบที่ไม่มีรายการเลยบันทึกไม่ได้', function (): void {
    actingAsRole(RoleName::Sales);
    $customer = Customer::factory()->create();

    $this->post(route('quotations.store'), quoteForm($customer, ['items' => []]))
        ->assertSessionHasErrors('items');

    expect(Quotation::count())->toBe(0);
});

it('วันยืนราคาก่อนวันที่ออกใบบันทึกไม่ได้', function (): void {
    actingAsRole(RoleName::Sales);
    $customer = Customer::factory()->create();

    $this->post(route('quotations.store'), quoteForm($customer, [
        'issue_date' => now()->toDateString(),
        'valid_until' => now()->subDay()->toDateString(),
    ]))->assertSessionHasErrors('valid_until');
});

it('บรรทัดชนิดสินค้าที่ไม่ได้เลือกสินค้าบันทึกไม่ได้', function (): void {
    actingAsRole(RoleName::Sales);
    $customer = Customer::factory()->create();

    $this->post(route('quotations.store'), quoteForm($customer, [
        'items' => [[
            'item_type' => QuotationItemType::Product->value,
            'description' => 'พิมพ์เอง',
            'qty' => '1',
            'unit_price' => '100',
        ]],
    ]))->assertSessionHasErrors('items.0.product_id');
});

it('บรรทัดค่าแรงพิมพ์อิสระได้โดยไม่ต้องผูกกับสินค้า', function (): void {
    actingAsRole(RoleName::Sales);
    $customer = Customer::factory()->create();

    $this->post(route('quotations.store'), quoteForm($customer, [
        'items' => [[
            'item_type' => QuotationItemType::Labour->value,
            'description' => 'ค่าแรงติดตั้ง',
            'qty' => '1',
            'unit_price' => '15000',
        ]],
    ]))->assertRedirect();

    expect((string) Quotation::firstOrFail()->grand_total)->toBe('16050.00');
});

it('บรรทัดที่มีมูลค่าแต่จำนวนเป็นศูนย์บันทึกไม่ได้', function (): void {
    actingAsRole(RoleName::Sales);
    $customer = Customer::factory()->create();

    $this->post(route('quotations.store'), quoteForm($customer, [
        'items' => [[
            'item_type' => QuotationItemType::Freight->value,
            'description' => 'ค่าขนส่ง',
            'qty' => '0',
            'unit_price' => '500',
        ]],
    ]))->assertSessionHasErrors('items.0.qty');
});

it('ผู้ติดต่อของลูกค้ารายอื่นถูกปฏิเสธ', function (): void {
    actingAsRole(RoleName::Sales);

    $customer = Customer::factory()->create();
    $otherContact = CustomerContact::factory()->create();

    $this->post(route('quotations.store'), quoteForm($customer, [
        'customer_contact_id' => $otherContact->id,
    ]))->assertSessionHasErrors('customer_contact_id');
});

// ── สิทธิ์ (spec 8) ─────────────────────────────────────

it('sales เปิดใบของ sales คนอื่นไม่ได้', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    $quotation = Quotation::factory()->forSales($theirs)->create();

    $this->actingAs($mine)
        ->get(route('quotations.show', $quotation))
        ->assertForbidden();
});

it('รายการใบเสนอราคาของ sales แสดงเฉพาะใบของตัวเอง', function (): void {
    $mine = userWithRole(RoleName::Sales);
    $theirs = userWithRole(RoleName::Sales);

    Quotation::factory()->forSales($mine)->create(['quote_no' => 'QT-202608-0001']);
    Quotation::factory()->forSales($theirs)->create(['quote_no' => 'QT-202608-0002']);

    $this->actingAs($mine)
        ->get(route('quotations.index'))
        ->assertOk()
        ->assertSee('QT-202608-0001')
        ->assertDontSee('QT-202608-0002');
});

it('ผู้จัดการฝ่ายขายเห็นใบของทุกคน', function (): void {
    $manager = userWithRole(RoleName::SalesManager);
    $sales = userWithRole(RoleName::Sales);

    $quotation = Quotation::factory()->forSales($sales)->create(['quote_no' => 'QT-202608-0009']);

    $this->actingAs($manager)
        ->get(route('quotations.index'))
        ->assertOk()
        ->assertSee('QT-202608-0009');

    $this->get(route('quotations.show', $quotation))->assertOk();
});

it('sales อนุมัติใบของตัวเองไม่ได้ แม้จะเป็นคนเดียวที่ดูแลดีลนี้', function (): void {
    $sales = actingAsRole(RoleName::Sales);

    $quotation = Quotation::factory()
        ->forSales($sales)
        ->status(QuotationStatus::PendingApproval)
        ->create();

    $this->post(route('quotations.approve', $quotation))->assertForbidden();
});

it('ผู้จัดการฝ่ายขายอนุมัติใบของ sales ได้', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $manager = userWithRole(RoleName::SalesManager);

    $quotation = Quotation::factory()
        ->forSales($sales)
        ->status(QuotationStatus::PendingApproval)
        ->create();

    $this->actingAs($manager)
        ->post(route('quotations.approve', $quotation))
        ->assertRedirect();

    expect($quotation->fresh()->approved_by)->toBe($manager->id);
});

it('ผู้จัดการอนุมัติใบของตัวเองไม่ได้ — ต้องมีคนที่สองเสมอ', function (): void {
    $manager = actingAsRole(RoleName::SalesManager);

    $quotation = Quotation::factory()
        ->forSales($manager)
        ->status(QuotationStatus::PendingApproval)
        ->create();

    $this->post(route('quotations.approve', $quotation))->assertForbidden();
});

it('admin อนุมัติใบของตัวเองได้ เพื่อไม่ให้ระบบล็อกตายในองค์กรเล็ก', function (): void {
    $admin = actingAsRole(RoleName::Admin);

    $quotation = Quotation::factory()
        ->forSales($admin)
        ->status(QuotationStatus::PendingApproval)
        ->create();

    $this->post(route('quotations.approve', $quotation))->assertRedirect();
});

it('คลังสินค้าแตะใบเสนอราคาไม่ได้เลย', function (): void {
    actingAsRole(RoleName::Warehouse);

    $this->get(route('quotations.index'))->assertForbidden();
    $this->get(route('quotations.create'))->assertForbidden();
    $this->post(route('quotations.store'), [])->assertForbidden();
});

it('ผู้ไม่มีสิทธิ์สร้างได้ 403 ไม่ใช่ 302 พร้อม validation error', function (): void {
    actingAsRole(RoleName::Viewer);

    // ถ้าได้ 302 แปลว่า validation รันก่อนตรวจสิทธิ์ = รั่วข้อมูลว่ากฎ validation คืออะไร
    $this->post(route('quotations.store'), [])->assertForbidden();
});

// ── สถานะกับปุ่มบนหน้าจอ ────────────────────────────────

it('ใบที่ส่งแล้วแก้ไม่ได้ และหน้ารายละเอียดไม่แสดงลิงก์แก้ไข', function (): void {
    $sales = actingAsRole(RoleName::Sales);

    $quotation = app(QuotationService::class)->createDraft(quoteForm(Customer::factory()->create()));

    $this->get(route('quotations.show', $quotation))
        ->assertOk()
        ->assertSee(route('quotations.edit', $quotation), escape: false);

    app(QuotationService::class)->send($quotation);

    $this->get(route('quotations.show', $quotation->refresh()))
        ->assertOk()
        ->assertDontSee(route('quotations.edit', $quotation), escape: false)
        ->assertSee(route('quotations.revise', $quotation), escape: false);

    $this->get(route('quotations.edit', $quotation))->assertForbidden();
});

it('ส่งใบที่ต้องอนุมัติแต่ยังไม่อนุมัติแล้วได้ข้อความบอกผู้ใช้ ไม่ใช่ error 500', function (): void {
    actingAsRole(RoleName::Sales);

    $quotation = app(QuotationService::class)->createDraft(quoteForm(Customer::factory()->create(), [
        'items' => [[
            'item_type' => QuotationItemType::Product->value,
            'product_id' => Product::factory()->create(['cost_price' => '600000'])->id,
            'description' => 'ระบบ UPS',
            'qty' => '1',
            'unit_price' => '900000',
        ]],
    ]));

    $this->from(route('quotations.show', $quotation))
        ->post(route('quotations.send', $quotation), ['send_email' => '0'])
        ->assertRedirect(route('quotations.show', $quotation))
        ->assertSessionHas('error');

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Draft);
});

it('API ที่ส่งใบซึ่งต้องอนุมัติตอบ 409 พร้อมเหตุผล', function (): void {
    $sales = userWithRole(RoleName::Sales);
    $this->actingAs($sales);

    $quotation = app(QuotationService::class)->createDraft(quoteForm(Customer::factory()->create(), [
        'items' => [[
            'item_type' => QuotationItemType::Product->value,
            'product_id' => Product::factory()->create(['cost_price' => '600000'])->id,
            'description' => 'ระบบ UPS',
            'qty' => '1',
            'unit_price' => '900000',
        ]],
    ]));

    $this->postJson(route('quotations.send', $quotation), ['send_email' => false])
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'approval_reasons']);
});

it('ส่งอีเมลพร้อม PDF แนบเมื่อเลือกส่งอีเมล', function (): void {
    Mail::fake();

    actingAsRole(RoleName::Sales);

    $quotation = app(QuotationService::class)->createDraft(quoteForm(Customer::factory()->create()));

    $this->post(route('quotations.send', $quotation), [
        'send_email' => '1',
        'email' => 'buyer@example.com',
        'locale' => 'th',
    ])->assertRedirect();

    Mail::assertSent(App\Mail\QuotationMail::class, fn ($mail): bool => $mail->hasTo('buyer@example.com'));

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent);
});

it('เลือกส่งอีเมลแต่ไม่กรอกอีเมลบันทึกไม่ได้ และสถานะไม่เปลี่ยน', function (): void {
    actingAsRole(RoleName::Sales);

    $quotation = app(QuotationService::class)->createDraft(quoteForm(Customer::factory()->create()));

    $this->post(route('quotations.send', $quotation), ['send_email' => '1'])
        ->assertSessionHasErrors('email');

    expect($quotation->fresh()->status)->toBe(QuotationStatus::Draft);
});

it('สร้างฉบับแก้ไขผ่านหน้าเว็บแล้วเด้งไปหน้าแก้ไขของฉบับใหม่', function (): void {
    actingAsRole(RoleName::Sales);

    $service = app(QuotationService::class);
    $quotation = $service->createDraft(quoteForm(Customer::factory()->create()));
    $service->send($quotation);

    $this->post(route('quotations.revise', $quotation))->assertRedirect();

    $revision = Quotation::where('revision', 1)->firstOrFail();

    expect($revision->parent_quotation_id)->toBe($quotation->id)
        ->and($quotation->fresh()->superseded_at)->not->toBeNull();
});

// ── ความปลอดภัยของช่องค้นหาและข้อความอิสระ (spec 11) ────

it('payload SQL injection ในช่องค้นหาไม่ทำให้ตารางหาย', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get(route('quotations.index', ['q' => "'; DROP TABLE quotations; --"]))->assertOk();

    expect(Illuminate\Support\Facades\Schema::hasTable('quotations'))->toBeTrue();
});

it('สคริปต์ใน description ถูก escape ตอนแสดงผล', function (): void {
    $sales = actingAsRole(RoleName::Sales);

    $quotation = app(QuotationService::class)->createDraft(quoteForm(Customer::factory()->create(), [
        'items' => [[
            'item_type' => QuotationItemType::Labour->value,
            'description' => '<script>alert("xss")</script>',
            'qty' => '1',
            'unit_price' => '100',
        ]],
    ]));

    $this->get(route('quotations.show', $quotation))
        ->assertOk()
        ->assertDontSee('<script>alert("xss")</script>', escape: false)
        ->assertSee('&lt;script&gt;', escape: false);
});
