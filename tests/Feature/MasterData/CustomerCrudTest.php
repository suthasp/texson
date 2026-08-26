<?php

declare(strict_types=1);

use App\Enums\PriceTier;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\CustomerContact;

function customerPayload(array $overrides = []): array
{
    return [
        'code' => 'CUS-TEST-01',
        'name_th' => 'บริษัท ทดสอบ ดาต้าเซ็นเตอร์ จำกัด',
        'name_en' => 'Test Data Center Co., Ltd.',
        'tax_id' => '0105551000011',
        'branch_code' => '00000',
        'address_line' => '99 ถนนทดสอบ',
        'subdistrict' => 'ปทุมวัน',
        'district' => 'ปทุมวัน',
        'province' => 'กรุงเทพมหานคร',
        'postcode' => '10330',
        'phone' => '02-100-2000',
        'email' => 'test@example.co.th',
        'credit_term_days' => '30',
        'payment_terms' => 'โอนภายใน 30 วัน',
        'price_tier' => PriceTier::Project->value,
        'is_active' => '1',
        ...$overrides,
    ];
}

it('sales สร้างลูกค้าใหม่ได้', function (): void {
    actingAsRole(RoleName::Sales);

    $this->post(route('customers.store'), customerPayload())->assertRedirect();

    $customer = Customer::where('code', 'CUS-TEST-01')->firstOrFail();

    expect($customer->name_th)->toBe('บริษัท ทดสอบ ดาต้าเซ็นเตอร์ จำกัด')
        ->and($customer->price_tier)->toBe(PriceTier::Project)
        ->and($customer->branchLabel())->toBe('สำนักงานใหญ่');
});

it('ปฏิเสธเลขผู้เสียภาษีที่ไม่ครบ 13 หลัก', function (): void {
    actingAsRole(RoleName::Sales);

    $this->post(route('customers.store'), customerPayload(['tax_id' => '123']))
        ->assertSessionHasErrors('tax_id');

    expect(Customer::count())->toBe(0);
});

it('เว้นรหัสสาขาว่างแล้วได้ 00000 เป็นสำนักงานใหญ่', function (): void {
    actingAsRole(RoleName::Sales);

    $this->post(route('customers.store'), customerPayload(['branch_code' => '']))->assertRedirect();

    expect(Customer::firstOrFail()->branch_code)->toBe('00000');
});

it('เพิ่มผู้ติดต่อหลักแล้วคนเดิมถูกปลดอัตโนมัติ', function (): void {
    actingAsRole(RoleName::Sales);

    $customer = Customer::factory()->create();
    $first = CustomerContact::factory()->primary()->create(['customer_id' => $customer->id]);

    $this->post(route('customers.contacts.store', $customer), [
        'name' => 'คุณผู้ติดต่อคนใหม่',
        'position' => 'ผู้จัดการ',
        'phone' => '081-000-0000',
        'is_primary' => '1',
    ])->assertRedirect(route('customers.show', $customer));

    expect($first->refresh()->is_primary)->toBeFalse()
        ->and($customer->contacts()->where('is_primary', true)->count())->toBe(1)
        ->and($customer->primaryContact->name)->toBe('คุณผู้ติดต่อคนใหม่');
});

it('เพิ่มหน้างานให้ลูกค้าได้และรหัสหน้างานห้ามซ้ำภายในลูกค้ารายเดียวกัน', function (): void {
    actingAsRole(RoleName::Sales);
    $customer = Customer::factory()->create();

    $payload = [
        'site_code' => 'DC-01',
        'site_name' => 'DC ชั้น 3 อาคาร A',
        'province' => 'กรุงเทพมหานคร',
        'is_active' => '1',
    ];

    $this->post(route('customers.sites.store', $customer), $payload)->assertRedirect();
    $this->post(route('customers.sites.store', $customer), $payload)->assertSessionHasErrors('site_code');

    expect($customer->sites()->count())->toBe(1);
});

it('ลูกค้าคนละรายใช้รหัสหน้างานเดียวกันได้', function (): void {
    actingAsRole(RoleName::Sales);
    $first = Customer::factory()->create();
    $second = Customer::factory()->create();

    $payload = ['site_code' => 'DC-01', 'site_name' => 'หน้างานหลัก', 'is_active' => '1'];

    $this->post(route('customers.sites.store', $first), $payload)->assertRedirect();
    $this->post(route('customers.sites.store', $second), $payload)->assertRedirect();

    expect($first->sites()->count())->toBe(1)
        ->and($second->sites()->count())->toBe(1);
});

it('หน้างานของลูกค้ารายอื่นเข้าถึงข้ามกันไม่ได้', function (): void {
    actingAsRole(RoleName::Sales);

    $owner = Customer::factory()->create();
    $other = Customer::factory()->create();
    $site = $owner->sites()->create(['site_code' => 'DC-01', 'site_name' => 'หน้างานของ owner']);

    $this->get(route('customers.sites.edit', [$other, $site]))->assertNotFound();
});

it('ลบลูกค้าแบบ soft delete เพื่อให้กู้คืนได้ตาม PDPA', function (): void {
    actingAsRole(RoleName::Sales);
    $customer = Customer::factory()->create();

    $this->delete(route('customers.destroy', $customer))->assertRedirect(route('customers.index'));

    expect(Customer::find($customer->id))->toBeNull()
        ->and(Customer::withTrashed()->find($customer->id))->not->toBeNull();
});

it('ค้นหาลูกค้าด้วยรหัส ชื่อ หรือเลขผู้เสียภาษีได้', function (): void {
    actingAsRole(RoleName::Sales);

    Customer::factory()->create(['code' => 'CUS-9001', 'name_th' => 'สยาม ดาต้า', 'tax_id' => '1111111111111']);
    Customer::factory()->create(['code' => 'CUS-9002', 'name_th' => 'ไทยแบงก์', 'tax_id' => '2222222222222']);

    $this->get(route('customers.index', ['q' => 'สยาม']))
        ->assertOk()
        ->assertSee('CUS-9001')
        ->assertDontSee('CUS-9002');

    $this->get(route('customers.index', ['q' => '2222222222222']))
        ->assertOk()
        ->assertSee('CUS-9002')
        ->assertDontSee('CUS-9001');
});
