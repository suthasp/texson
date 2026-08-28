<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerSite;
use App\Models\Quotation;
use App\Services\PersonalDataService;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * PDPA ตาม spec 8
 *
 * ข้อมูลผู้ติดต่อลูกค้าเป็นข้อมูลส่วนบุคคล ต้อง log การเข้าถึง/แก้ไข ·
 * soft delete + ลบถาวรสำหรับ admin · ส่งออกข้อมูลรายคนได้
 */
beforeEach(function (): void {
    $this->customer = Customer::factory()->create([
        'code' => 'CUS-9001',
        'name_th' => 'บริษัท ทดสอบ พีดีพีเอ จำกัด',
        'tax_id' => '0105512345678',
        'phone' => '02-000-0000',
        'email' => 'contact@example.co.th',
    ]);

    CustomerContact::factory()->create([
        'customer_id' => $this->customer->id,
        'name' => 'สมชาย ใจดี',
        'phone' => '081-000-0000',
        'is_primary' => true,
    ]);
});

// ── บันทึกการเข้าถึง ──────────────────────────────────

it('บันทึก log เมื่อมีคนเปิดดูข้อมูลผู้ติดต่อของลูกค้า', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get(route('customers.show', $this->customer))->assertOk();

    $entry = Activity::query()->where('log_name', PersonalDataService::LOG)->where('event', 'accessed')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->subject_id)->toBe($this->customer->id)
        ->and($entry->properties->get('customer_code'))->toBe('CUS-9001');
});

it('ยุบ log การเปิดดูเป็นวันละครั้งต่อคนต่อลูกค้า', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get(route('customers.show', $this->customer))->assertOk();
    $this->get(route('customers.show', $this->customer))->assertOk();
    $this->get(route('customers.show', $this->customer))->assertOk();

    $count = fn (): int => Activity::query()
        ->where('log_name', PersonalDataService::LOG)
        ->where('event', 'accessed')
        ->count();

    expect($count())->toBe(1);

    // ข้ามวันแล้วต้องบันทึกใหม่ ไม่งั้นตอบไม่ได้ว่าใครเข้าถึงข้อมูลวันไหน
    Carbon::setTestNow(Carbon::now()->addDay());

    $this->get(route('customers.show', $this->customer))->assertOk();

    expect($count())->toBe(2);

    Carbon::setTestNow();
});

it('แยก log ของคนละคน ไม่ยุบรวมกัน', function (): void {
    $first = actingAsRole(RoleName::Sales);
    $this->get(route('customers.show', $this->customer))->assertOk();

    $this->app['auth']->forgetGuards();
    $second = userWithRole(RoleName::SalesManager);
    $this->actingAs($second);
    $this->get(route('customers.show', $this->customer))->assertOk();

    $causers = Activity::query()
        ->where('log_name', PersonalDataService::LOG)
        ->where('event', 'accessed')
        ->pluck('causer_id');

    expect($causers)->toHaveCount(2)
        ->and($causers->all())->toContain($first->id, $second->id);
});

it('ไม่บันทึกการเข้าถึงถ้าลูกค้ายังไม่มีข้อมูลผู้ติดต่อ', function (): void {
    actingAsRole(RoleName::Sales);

    $bare = Customer::factory()->create(['code' => 'CUS-9002']);

    $this->get(route('customers.show', $bare))->assertOk();

    expect(Activity::query()->where('log_name', PersonalDataService::LOG)->count())->toBe(0);
});

it('บันทึกค่าก่อน/หลังเมื่อแก้ข้อมูลผู้ติดต่อ', function (): void {
    actingAsRole(RoleName::Sales);

    $contact = $this->customer->contacts()->first();

    $this->put(route('customers.contacts.update', [$this->customer, $contact]), [
        'name' => 'สมหญิง รักดี',
        'phone' => '081-999-9999',
        'is_primary' => '1',
    ])->assertRedirect();

    $entry = Activity::query()->where('subject_type', CustomerContact::class)->where('event', 'updated')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->properties->get('old')['name'])->toBe('สมชาย ใจดี')
        ->and($entry->properties->get('attributes')['name'])->toBe('สมหญิง รักดี');
});

// ── ส่งออกข้อมูลรายคน ────────────────────────────────

it('เปิดหน้าข้อมูลส่วนบุคคลของลูกค้ารายคนได้', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get(route('customers.personal-data', $this->customer))
        ->assertOk()
        ->assertSee('0105512345678')
        ->assertSee('สมชาย ใจดี');
});

it('ดาวน์โหลดสำเนาข้อมูลเป็น JSON ที่อ่านภาษาไทยได้', function (): void {
    actingAsRole(RoleName::Sales);

    $response = $this->get(route('customers.personal-data.download', $this->customer))->assertOk();

    $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('personal-data_CUS-9001_');

    $payload = $response->json();

    expect($payload['customer']['name_th'])->toBe('บริษัท ทดสอบ พีดีพีเอ จำกัด')
        ->and($payload['customer']['tax_id'])->toBe('0105512345678')
        ->and($payload['contacts'][0]['name'])->toBe('สมชาย ใจดี')
        ->and($payload)->toHaveKeys(['exported_at', 'sites', 'documents', 'access_log']);
});

it('บันทึกการส่งออกทุกครั้ง ไม่ยุบเหมือนการเปิดดู', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get(route('customers.personal-data.download', $this->customer))->assertOk();
    $this->get(route('customers.personal-data.download', $this->customer))->assertOk();

    expect(Activity::query()->where('log_name', PersonalDataService::LOG)->where('event', 'exported')->count())->toBe(2);
});

it('role ที่ไม่มีสิทธิ์ดูข้อมูลลูกค้า เข้าหน้าข้อมูลส่วนบุคคลไม่ได้', function (): void {
    actingAsRole(RoleName::Warehouse);

    $this->get(route('customers.personal-data', $this->customer))->assertForbidden();
    $this->get(route('customers.personal-data.download', $this->customer))->assertForbidden();
});

// ── ลบตามคำขอ ────────────────────────────────────────

it('ลบข้อมูลส่วนบุคคลทิ้งแต่เก็บเอกสารภาษีไว้', function (): void {
    $admin = actingAsRole(RoleName::Admin);

    $quotation = Quotation::factory()->forSales($admin)->create([
        'customer_id' => $this->customer->id,
        'quote_no' => 'QT-202608-9001',
        'grand_total' => '107000.00',
    ]);

    CustomerSite::factory()->create([
        'customer_id' => $this->customer->id,
        'access_note' => 'ติดต่อ รปภ. คุณสมศักดิ์ ก่อนเข้าพื้นที่',
    ]);

    $this->delete(route('customers.personal-data.erase', $this->customer), [
        'confirmation' => 'CUS-9001',
        'reason' => 'ลูกค้าใช้สิทธิ์ขอให้ลบ',
    ])->assertRedirect();

    $customer = Customer::withTrashed()->find($this->customer->id);

    // ข้อมูลส่วนบุคคลหายหมด
    expect($customer->name_th)->toBe(PersonalDataService::ERASED)
        ->and($customer->tax_id)->toBeNull()
        ->and($customer->phone)->toBeNull()
        ->and($customer->email)->toBeNull()
        ->and($customer->contacts()->count())->toBe(0)
        ->and($customer->sites()->first()->access_note)->toBeNull()
        ->and($customer->anonymized_at)->not->toBeNull()
        ->and($customer->trashed())->toBeTrue();

    // เอกสารและยอดเงินยังอยู่ครบ
    expect(Quotation::find($quotation->id))->not->toBeNull()
        ->and(Quotation::find($quotation->id)->grand_total)->toBe('107000.00');
});

it('ลูกค้าที่ยังไม่มีเอกสารเลย ถูกลบออกจากตารางจริง', function (): void {
    actingAsRole(RoleName::Admin);

    $this->delete(route('customers.personal-data.erase', $this->customer), [
        'confirmation' => 'CUS-9001',
        'reason' => 'ลูกค้าใช้สิทธิ์ขอให้ลบ',
    ])->assertRedirect(route('customers.index'));

    expect(Customer::withTrashed()->find($this->customer->id))->toBeNull()
        ->and(CustomerContact::query()->count())->toBe(0);
});

it('บันทึก log การลบพร้อมเหตุผล แต่ไม่เก็บค่าที่ลบไป', function (): void {
    actingAsRole(RoleName::Admin);

    Quotation::factory()->create(['customer_id' => $this->customer->id, 'quote_no' => 'QT-202608-9002']);

    $this->delete(route('customers.personal-data.erase', $this->customer), [
        'confirmation' => 'CUS-9001',
        'reason' => 'คำขอทางอีเมล 28 ส.ค. 2569',
    ])->assertRedirect();

    $entry = Activity::query()->where('event', 'anonymized')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->properties->get('reason'))->toBe('คำขอทางอีเมล 28 ส.ค. 2569')
        ->and($entry->properties->get('contacts'))->toBe(1)
        // ค่าที่ลบต้องไม่ถูกเก็บไว้ที่ไหน ไม่งั้นก็เท่ากับไม่ได้ลบ
        ->and(json_encode($entry->properties))->not->toContain('0105512345678');
});

it('พิมพ์รหัสลูกค้ายืนยันไม่ตรง ลบไม่ได้', function (): void {
    actingAsRole(RoleName::Admin);

    $this->from(route('customers.personal-data', $this->customer))
        ->delete(route('customers.personal-data.erase', $this->customer), [
            'confirmation' => 'CUS-0000',
            'reason' => 'ทดสอบ',
        ])
        ->assertSessionHasErrors('confirmation');

    expect(Customer::find($this->customer->id)->tax_id)->toBe('0105512345678');
});

it('ไม่ระบุเหตุผล ลบไม่ได้', function (): void {
    actingAsRole(RoleName::Admin);

    $this->from(route('customers.personal-data', $this->customer))
        ->delete(route('customers.personal-data.erase', $this->customer), ['confirmation' => 'CUS-9001'])
        ->assertSessionHasErrors('reason');
});

it('ฝ่ายขายลบข้อมูลถาวรไม่ได้ สงวนไว้ให้ admin', function (): void {
    actingAsRole(RoleName::Sales);

    $this->delete(route('customers.personal-data.erase', $this->customer), [
        'confirmation' => 'CUS-9001',
        'reason' => 'ทดสอบ',
    ])->assertForbidden();

    expect(Customer::find($this->customer->id)->tax_id)->toBe('0105512345678');
});

// ── soft delete และการกู้คืน ──────────────────────────

it('ลบลูกค้าแบบ soft delete แล้วกู้กลับมาได้', function (): void {
    actingAsRole(RoleName::Admin);

    $this->delete(route('customers.destroy', $this->customer))->assertRedirect();

    expect(Customer::find($this->customer->id))->toBeNull()
        ->and(Customer::withTrashed()->find($this->customer->id))->not->toBeNull();

    $this->post(route('customers.restore', $this->customer->id))->assertRedirect();

    expect(Customer::find($this->customer->id)->name_th)->toBe('บริษัท ทดสอบ พีดีพีเอ จำกัด');
});

it('ลูกค้าที่ถูกลบข้อมูลตามคำขอแล้ว กู้คืนไม่ได้', function (): void {
    $admin = actingAsRole(RoleName::Admin);

    app(PersonalDataService::class)->anonymize($this->customer, $admin);

    $this->post(route('customers.restore', $this->customer->id))->assertForbidden();
});

it('admin ค้นหาลูกค้าที่ถูกลบไปแล้วเจอ แต่ฝ่ายขายไม่เห็นตัวกรองนี้', function (): void {
    actingAsRole(RoleName::Admin);
    $this->customer->delete();

    $this->get(route('customers.index', ['trashed' => 1]))->assertOk()->assertSee('CUS-9001');

    $this->app['auth']->forgetGuards();
    $this->actingAs(userWithRole(RoleName::Sales));

    // ฝ่ายขายส่ง trashed=1 มาเองก็ยังไม่เห็น เพราะสิทธิ์ไม่ถึง
    $this->get(route('customers.index', ['trashed' => 1]))->assertOk()->assertDontSee('CUS-9001');
});
