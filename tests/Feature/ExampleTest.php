<?php

declare(strict_types=1);

it('หน้าแรกพาผู้ใช้ที่ยังไม่ล็อกอินไปหน้า login', function (): void {
    $this->get('/')
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('health check ตอบ 200', function (): void {
    $this->get('/up')->assertOk();
});
