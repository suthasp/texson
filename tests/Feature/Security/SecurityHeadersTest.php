<?php

declare(strict_types=1);

use App\Enums\RoleName;

/**
 * Security header ตาม spec 8
 */
it('ส่ง security header ครบทุกตัวที่สเปกกำหนด', function (): void {
    actingAsRole(RoleName::Viewer);

    $response = $this->get(route('dashboard'))->assertOk();

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Referrer-Policy'))->toBe('same-origin')
        ->and($response->headers->get('Content-Security-Policy'))->not->toBeNull();
});

it('ติด header ให้ response ของ API ด้วย', function (): void {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('ติด header แม้ตอนยังไม่ได้ล็อกอิน', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY');
});

/**
 * โลโก้กับ favicon เสิร์ฟจาก public/ ซึ่ง CSP ครอบด้วย img-src 'self'
 * ถ้ามีคนย้ายไฟล์ไปโฮสต์อื่น รูปจะหายไปเงียบ ๆ เพราะ CSP บล็อก
 */
it('โลโก้และ favicon อยู่บนโดเมนเดียวกัน ไม่ถูก CSP บล็อก', function (): void {
    actingAsRole(RoleName::Viewer);

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain(asset('logo/favicon-128.png'))
        ->toContain(asset('logo/logo-dark.png'));

    // หน้าเข้าสู่ระบบที่ยังไม่ล็อกอินก็ต้องมีเหมือนกัน
    $this->app['auth']->forgetGuards();

    expect($this->get(route('login'))->getContent())
        ->toContain(asset('logo/favicon-128.png'));
});

it('CSP ห้ามฝังหน้าใน iframe และห้าม object', function (): void {
    actingAsRole(RoleName::Viewer);

    $csp = (string) $this->get(route('dashboard'))->headers->get('Content-Security-Policy');

    expect($csp)->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'");
});

/**
 * ถ้า script-src เผลอเปิด 'unsafe-inline' เมื่อไร CSP ก็แทบไม่กันอะไรอีกเลย
 * เทสต์นี้อยู่เพื่อให้การเปิดเป็นการตัดสินใจ ไม่ใช่อุบัติเหตุ
 */
it("CSP ไม่เปิด 'unsafe-inline' ให้ script", function (): void {
    actingAsRole(RoleName::Viewer);

    $csp = (string) $this->get(route('dashboard'))->headers->get('Content-Security-Policy');

    preg_match('/script-src ([^;]+)/', $csp, $matches);

    expect($matches[1] ?? '')->not->toContain('unsafe-inline');
});

/**
 * CSP ข้างบนจะไร้ความหมายทันทีถ้ามีคนเผลอเขียน inline handler กลับเข้ามา —
 * เบราว์เซอร์จะบล็อกเงียบ ๆ ปุ่มยืนยันก่อนลบก็หายไปโดยไม่มีใครรู้
 */
it('ไม่มี inline event handler เหลืออยู่ใน Blade', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if (! str_ends_with((string) $file, '.blade.php')) {
            continue;
        }

        $contents = (string) file_get_contents((string) $file);

        if (preg_match('/\son(click|submit|change|load|error|focus|blur)\s*=/i', $contents) === 1) {
            $offenders[] = basename((string) $file);
        }
    }

    expect($offenders)->toBe([]);
});

it('ไม่ส่ง HSTS บน http แต่ส่งเมื่อวิ่งบน https', function (): void {
    actingAsRole(RoleName::Viewer);

    expect($this->get(route('dashboard'))->headers->has('Strict-Transport-Security'))->toBeFalse();

    $secure = $this->get(str_replace('http://', 'https://', route('dashboard')), ['HTTPS' => 'on']);

    expect($secure->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});
