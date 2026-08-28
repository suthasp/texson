<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\SettingKey;
use App\Models\User;
use App\Services\SettingService;

/**
 * หน้าเว็บสาธารณะ (ADR-029)
 *
 * เป็นหน้าเดียวที่คนนอกองค์กรเข้าถึงได้ จึงต้องเปิดได้โดยไม่ล็อกอิน
 * แต่ยังต้องมี security header และไม่รั่วอะไรออกไป
 */
it('คนที่ยังไม่ล็อกอินเปิดหน้าแรกได้ ไม่ถูกเด้งไปหน้า login', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('ห้อง Server ของคุณ พร้อมรับมือเหตุไฟดับ–ระบบล่ม'));
});

it('มีครบทุกส่วนตามไฟล์ตัวอย่าง', function (string $heading): void {
    $this->get('/')->assertOk()->assertSee($heading);
})->with([
    'ปัญหาเหล่านี้ คุ้นไหมครับ?',
    'บริการของเรา',
    'สินค้าและอุปกรณ์',
    'ทำไมต้องเรา',
    'ขั้นตอนการทำงาน',
    'ปรึกษาฟรี ไม่มีค่าใช้จ่าย',
]);

it('มีเมนูเข้าสู่ระบบให้พนักงานกดเข้าหลังบ้าน', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('login'), escape: false)
        ->assertSee(__('เข้าสู่ระบบ'));
});

it('คนที่ล็อกอินแล้วเห็นปุ่มไปแดชบอร์ดแทนปุ่มเข้าสู่ระบบ', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get('/')
        ->assertOk()
        ->assertSee(route('dashboard'), escape: false)
        ->assertSee(__('แดชบอร์ด'));
});

it('สลับเป็นภาษาอังกฤษได้ทั้งหน้า', function (): void {
    $this->get('/?lang=en')
        ->assertOk()
        ->assertSee('Is Your Server Room Ready')
        ->assertSee('Do Any of These Sound Familiar?')
        ->assertSee('How We Work')
        ->assertDontSee('ปัญหาเหล่านี้ คุ้นไหมครับ?');
});

it('ภาษาที่ไม่รองรับถูกเมิน ไม่ทำให้หน้าเสีย', function (): void {
    $this->get('/?lang=../../etc/passwd')
        ->assertOk()
        ->assertSee(__('บริการของเรา'));
});

it('ดึงเบอร์และอีเมลจากค่าตั้งระบบ ผู้ดูแลแก้ได้เองโดยไม่ต้องแก้โค้ด', function (): void {
    app(SettingService::class)->setMany([
        SettingKey::CompanyPhone->value => '02-999-0000',
        SettingKey::CompanyEmail->value => 'hello@texson.co.th',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('02-999-0000')
        ->assertSee('hello@texson.co.th');
});

it('ติด security header เหมือนหน้าอื่นแม้เป็นหน้าสาธารณะ', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('ไม่หลุดข้อมูลภายในองค์กรออกไปหน้าสาธารณะ', function (): void {
    seedRoles();
    User::factory()->create(['name' => 'พนักงานลับ', 'email' => 'secret@texson.local']);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('secret@texson.local')
        ->assertDontSee('พนักงานลับ');
});
