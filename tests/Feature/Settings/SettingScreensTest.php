<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Enums\SettingKey;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('ผู้ดูแลระบบเปิดหน้าตั้งค่าและบันทึกข้อมูลบริษัทได้', function (): void {
    actingAsRole(RoleName::Admin);

    $this->get(route('settings.index'))->assertOk();

    $this->put(route('settings.update'), [
        'group' => 'company',
        'values' => [
            SettingKey::CompanyNameTh->value => 'บริษัท เท็กซัน จำกัด (สำนักงานใหญ่)',
            SettingKey::CompanyTaxId->value => '0105558000123',
        ],
    ])->assertRedirect();

    expect(app(SettingService::class)->string(SettingKey::CompanyNameTh))
        ->toBe('บริษัท เท็กซัน จำกัด (สำนักงานใหญ่)');
});

it('คีย์ที่ไม่รู้จักหรือไม่ได้อยู่ในกลุ่มนี้ถูกทิ้ง ไม่ถูกบันทึก', function (): void {
    actingAsRole(RoleName::Admin);

    $this->put(route('settings.update'), [
        'group' => 'company',
        'values' => [
            SettingKey::CompanyNameTh->value => 'ชื่อจริง',
            // คีย์ปลอมและคีย์ของกลุ่มอื่นที่ยัดมากับฟอร์ม
            'evil.key' => 'hacked',
            SettingKey::ApprovalMaxGrandTotal->value => '1',
        ],
    ])->assertRedirect();

    expect(Setting::where('key', 'evil.key')->exists())->toBeFalse()
        // เกณฑ์อนุมัติต้องไม่ถูกแก้จากฟอร์มของกลุ่ม company
        ->and(app(SettingService::class)->decimal(SettingKey::ApprovalMaxGrandTotal))->toBe('500000.00');
});

it('แก้เกณฑ์อนุมัติแล้วมีผลกับการตัดสินใบทันที', function (): void {
    actingAsRole(RoleName::Admin);

    $this->put(route('settings.update'), [
        'group' => 'approval',
        'values' => [SettingKey::ApprovalMaxGrandTotal->value => '1000'],
    ])->assertRedirect();

    expect(app(SettingService::class)->decimal(SettingKey::ApprovalMaxGrandTotal))->toBe('1000');
});

it('ผู้จัดการฝ่ายขายดูค่าตั้งได้แต่แก้ไม่ได้', function (): void {
    actingAsRole(RoleName::SalesManager);

    $this->get(route('settings.index'))->assertOk();

    $this->put(route('settings.update'), [
        'group' => 'approval',
        'values' => [SettingKey::ApprovalMaxGrandTotal->value => '1'],
    ])->assertForbidden();
});

it('ฝ่ายขายเข้าหน้าตั้งค่าไม่ได้', function (): void {
    actingAsRole(RoleName::Sales);

    $this->get(route('settings.index'))->assertForbidden();
});

it('อัปโหลดโลโก้เก็บลง storage private และตั้งชื่อไฟล์ใหม่', function (): void {
    Storage::fake('private');
    actingAsRole(RoleName::Admin);

    $this->put(route('settings.update'), [
        'group' => 'company',
        'values' => [SettingKey::CompanyNameTh->value => 'บริษัททดสอบ'],
        'logo' => UploadedFile::fake()->image('โลโก้บริษัท.png', 200, 80),
    ])->assertRedirect();

    $path = app(SettingService::class)->string(SettingKey::CompanyLogoPath);

    expect($path)->toStartWith('branding/')
        // ชื่อไฟล์เดิมของผู้ใช้ต้องไม่ถูกใช้เป็นชื่อบนดิสก์
        ->and($path)->not->toContain('โลโก้')
        ->and(Storage::disk('private')->exists($path))->toBeTrue();
});

it('ไฟล์ที่ไม่ใช่รูปถูกปฏิเสธด้วยการตรวจ mime จริง', function (): void {
    Storage::fake('private');
    actingAsRole(RoleName::Admin);

    // ต้องเป็นไฟล์จริงบนดิสก์ ไม่ใช่ UploadedFile::fake()
    // เพราะไฟล์ปลอมของ Laravel รายงาน mime จากนามสกุล ไม่ได้อ่านเนื้อไฟล์
    // ซึ่งจะทำให้เทสต์ผ่านทั้งที่ยังไม่ได้พิสูจน์ว่ามีการตรวจด้วย finfo จริง
    $path = tempnam(sys_get_temp_dir(), 'texson-upload-');
    file_put_contents($path, '<?php echo "pwned";');

    $this->put(route('settings.update'), [
        'group' => 'company',
        'values' => [SettingKey::CompanyNameTh->value => 'บริษัททดสอบ'],
        // นามสกุล .png แต่เนื้อไฟล์เป็น PHP
        'logo' => new UploadedFile($path, 'shell.png', null, null, true),
    ])->assertSessionHasErrors('logo');

    expect(app(SettingService::class)->string(SettingKey::CompanyLogoPath))->toBe('');

    @unlink($path);
});

it('ไฟล์โลโก้ถูกส่งผ่าน controller ที่ตรวจสิทธิ์ ไม่ใช่ลิงก์สาธารณะ', function (): void {
    Storage::fake('private');
    actingAsRole(RoleName::Admin);

    $this->put(route('settings.update'), [
        'group' => 'company',
        'values' => [SettingKey::CompanyNameTh->value => 'บริษัททดสอบ'],
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $this->get(route('settings.asset', SettingKey::CompanyLogoPath->value))->assertOk();

    // ผู้ใช้ที่ไม่มีสิทธิ์ดูค่าตั้งเข้าถึงไฟล์ไม่ได้
    actingAsRole(RoleName::Sales);
    $this->get(route('settings.asset', SettingKey::CompanyLogoPath->value))->assertForbidden();
});

it('ขอไฟล์จากคีย์ที่ไม่ใช่ไฟล์ได้ 404', function (): void {
    actingAsRole(RoleName::Admin);

    $this->get(route('settings.asset', SettingKey::CompanyNameTh->value))->assertNotFound();
    $this->get(route('settings.asset', 'ไม่มีคีย์นี้'))->assertNotFound();
});

it('บันทึกค่าตั้งแล้ว cache ถูกล้าง ค่าที่อ่านครั้งถัดไปเป็นค่าใหม่', function (): void {
    $service = app(SettingService::class);

    expect($service->decimal(SettingKey::VatRate))->toBe('7.00');

    $service->set(SettingKey::VatRate, '10.00');

    expect($service->decimal(SettingKey::VatRate))->toBe('10.00');
});

it('คีย์ที่ยังไม่เคยตั้งค่าคืนค่า default ตามสเปกข้อ 4.3', function (): void {
    $service = app(SettingService::class);

    expect($service->decimal(SettingKey::ApprovalMaxDiscountPercent))->toBe('15.00')
        ->and($service->decimal(SettingKey::ApprovalMinMarginPercent))->toBe('10.00')
        ->and($service->decimal(SettingKey::ApprovalMaxGrandTotal))->toBe('500000.00')
        ->and($service->integer(SettingKey::QuoteValidDays))->toBe(30);
});

it('บันทึกการแก้ค่าตั้งลง activity log พร้อมค่าก่อนและหลัง', function (): void {
    $admin = actingAsRole(RoleName::Admin);

    $service = app(SettingService::class);
    $service->set(SettingKey::ApprovalMaxGrandTotal, '500000.00');
    $service->set(SettingKey::ApprovalMaxGrandTotal, '900000.00');

    $log = Spatie\Activitylog\Models\Activity::query()
        ->where('subject_type', Setting::class)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['old']['value'])->toBe('500000.00')
        ->and($log->properties['attributes']['value'])->toBe('900000.00')
        ->and($log->causer_id)->toBe($admin->id);
});
