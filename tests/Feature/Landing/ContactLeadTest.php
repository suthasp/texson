<?php

declare(strict_types=1);

use App\Enums\ContactLeadStatus;
use App\Enums\RoleName;
use App\Enums\ServiceInterest;
use App\Http\Requests\ContactLeadRequest;
use App\Mail\ContactLeadReceived;
use App\Models\ContactLead;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

/**
 * ฟอร์ม "ปรึกษาฟรี" และการติดตามคำขอฝั่งหลังบ้าน (ADR-029)
 */
$payload = fn (array $overrides = []): array => [
    'name' => 'คุณสมชาย ทดสอบ',
    'company' => 'บริษัท ทดสอบ จำกัด',
    'contact' => '081-234-5678',
    'service_interest' => ServiceInterest::Audit->value,
    'message' => 'อยากให้เข้ามาประเมินห้อง Server ชั้น 5',
    ...$overrides,
];

// ── ส่งฟอร์ม ────────────────────────────────────────────

it('คนนอกส่งฟอร์มได้โดยไม่ต้องล็อกอิน และข้อมูลถูกเก็บครบ', function () use ($payload): void {
    Mail::fake();

    $this->post(route('landing.contact'), $payload())
        ->assertRedirect(route('landing').'#contact')
        ->assertSessionHas('success');

    $lead = ContactLead::sole();

    expect($lead->name)->toBe('คุณสมชาย ทดสอบ')
        ->and($lead->company)->toBe('บริษัท ทดสอบ จำกัด')
        ->and($lead->contact)->toBe('081-234-5678')
        ->and($lead->service_interest)->toBe(ServiceInterest::Audit)
        ->and($lead->status)->toBe(ContactLeadStatus::New)
        ->and($lead->handled_by)->toBeNull()
        // ร่องรอยที่ใช้ตามตอนโดนยิงสแปม
        ->and($lead->ip)->not->toBeNull()
        ->and($lead->locale)->toBe('th');
});

it('แจ้งเตือนทีมขายทางอีเมลเมื่อมีคำขอใหม่', function () use ($payload): void {
    Mail::fake();
    $sales = userWithRole(RoleName::Sales);

    $this->post(route('landing.contact'), $payload());

    Mail::assertSent(ContactLeadReceived::class, fn ($mail): bool => $mail->hasTo($sales->email));
});

it('เมลส่งไม่ออกก็ยังบันทึกคำขอไว้ ไม่ทำให้ผู้ติดต่อเห็น error', function () use ($payload): void {
    userWithRole(RoleName::Sales);

    // จำลองเมลเซิร์ฟเวอร์ล่ม — คำขอต้องไม่หายไปกับมัน
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP ล่ม'));

    $this->post(route('landing.contact'), $payload())
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(ContactLead::count())->toBe(1);
});

it('บันทึกภาษาที่ผู้ติดต่อใช้กรอก', function () use ($payload): void {
    Mail::fake();

    $this->get('/?lang=en');
    $this->post(route('landing.contact'), $payload());

    expect(ContactLead::sole()->locale)->toBe('en');
});

// ── ตรวจข้อมูลและกันสแปม ────────────────────────────────

it('ต้องกรอกชื่อและช่องทางติดต่อกลับ', function () use ($payload): void {
    Mail::fake();

    $this->post(route('landing.contact'), $payload(['name' => '', 'contact' => '']))
        ->assertSessionHasErrors(['name', 'contact']);

    expect(ContactLead::count())->toBe(0);
});

it('บริการที่สนใจรับเฉพาะค่าที่ระบบรู้จัก', function () use ($payload): void {
    Mail::fake();

    $this->post(route('landing.contact'), $payload(['service_interest' => 'ปลอม']))
        ->assertSessionHasErrors('service_interest');
});

it('กับดักสแปม: กรอกช่องที่คนมองไม่เห็นแล้วคำขอไม่ถูกบันทึก', function () use ($payload): void {
    Mail::fake();

    $this->post(route('landing.contact'), $payload([ContactLeadRequest::HONEYPOT => 'http://spam.example']))
        ->assertSessionHasErrors(ContactLeadRequest::HONEYPOT);

    expect(ContactLead::count())->toBe(0);
    Mail::assertNothingSent();
});

it('ข้อความยาวเกินถูกปฏิเสธ ไม่ปล่อยให้ยัดข้อมูลเข้าฐาน', function () use ($payload): void {
    Mail::fake();

    $this->post(route('landing.contact'), $payload(['message' => str_repeat('ก', 2001)]))
        ->assertSessionHasErrors('message');
});

it('escape HTML ที่คนนอกกรอกเข้ามา จึงไม่เกิด XSS ในหน้าของทีมขาย', function () use ($payload): void {
    Mail::fake();
    $this->post(route('landing.contact'), $payload(['name' => '<script>alert(1)</script>']));

    actingAsRole(RoleName::Sales);

    $this->get(route('leads.show', ContactLead::sole()))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

// ── ฝั่งหลังบ้าน ────────────────────────────────────────

it('ฝ่ายขายเห็นคำขอทุกใบ ไม่ใช่เฉพาะของตัวเอง', function (): void {
    ContactLead::factory()->count(3)->create();
    actingAsRole(RoleName::Sales);

    $this->get(route('leads.index'))->assertOk()->assertSee(ContactLead::first()->name);
});

it('เปลี่ยนสถานะแล้วคนที่กดเป็นผู้ดูแลเรื่องนั้น', function (): void {
    $sales = actingAsRole(RoleName::Sales);
    $lead = ContactLead::factory()->create();

    $this->put(route('leads.update', $lead), [
        'status' => ContactLeadStatus::Contacted->value,
        'internal_note' => 'โทรกลับแล้ว นัดเข้าสำรวจ',
    ])->assertRedirect();

    $lead->refresh();

    expect($lead->status)->toBe(ContactLeadStatus::Contacted)
        ->and($lead->handled_by)->toBe($sales->id)
        ->and($lead->handled_at)->not->toBeNull()
        ->and($lead->internal_note)->toBe('โทรกลับแล้ว นัดเข้าสำรวจ');
});

it('คนที่รับเรื่องไว้แล้วไม่ถูกคนอื่นแย่งไปตอนอัปเดตต่อ', function (): void {
    $first = actingAsRole(RoleName::Sales);
    $lead = ContactLead::factory()->create();

    $this->put(route('leads.update', $lead), ['status' => ContactLeadStatus::Contacted->value]);

    $this->app['auth']->forgetGuards();
    $this->actingAs(userWithRole(RoleName::SalesManager));

    $this->put(route('leads.update', $lead), ['status' => ContactLeadStatus::Closed->value]);

    expect($lead->refresh()->handled_by)->toBe($first->id);
});

it('เรื่องที่ปิดแล้วเปิดใหม่ไม่ได้ ตอบ 409', function (): void {
    actingAsRole(RoleName::Sales);
    $lead = ContactLead::factory()->status(ContactLeadStatus::Closed)->create();

    $this->put(route('leads.update', $lead), ['status' => ContactLeadStatus::Contacted->value])
        ->assertSessionHas('error');

    expect($lead->refresh()->status)->toBe(ContactLeadStatus::Closed);
});

it('บันทึกการเปลี่ยนสถานะลง activity log พร้อมค่าก่อน/หลัง', function (): void {
    actingAsRole(RoleName::Sales);
    $lead = ContactLead::factory()->create();

    $this->put(route('leads.update', $lead), ['status' => ContactLeadStatus::Contacted->value]);

    $entry = Activity::query()->where('subject_type', ContactLead::class)->where('event', 'updated')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->properties->get('old')['status'])->toBe('new')
        ->and($entry->properties->get('attributes')['status'])->toBe('contacted');
});

it('role ที่ไม่เกี่ยวกับงานขายเข้าหน้าคำขอไม่ได้', function (RoleName $role): void {
    actingAsRole($role);

    $this->get(route('leads.index'))->assertForbidden();
})->with([RoleName::Warehouse, RoleName::Engineer, RoleName::Viewer]);

it('ฝ่ายขายลบคำขอทิ้งไม่ได้ สงวนให้ admin', function (): void {
    actingAsRole(RoleName::Sales);
    $lead = ContactLead::factory()->create();

    $this->delete(route('leads.destroy', $lead))->assertForbidden();

    expect(ContactLead::count())->toBe(1);
});

it('admin ลบคำขอได้แบบ soft delete ยังกู้คืนได้', function (): void {
    actingAsRole(RoleName::Admin);
    $lead = ContactLead::factory()->create();

    $this->delete(route('leads.destroy', $lead))->assertRedirect(route('leads.index'));

    expect(ContactLead::count())->toBe(0)
        ->and(ContactLead::withTrashed()->count())->toBe(1);
});
