<?php

declare(strict_types=1);

/**
 * ตั้งแต่ ADR-029 หน้าแรกเป็นหน้าเว็บสาธารณะ ไม่ใช่ทางผ่านไปแดชบอร์ดอีกแล้ว
 * ส่วนหลังบ้านยังอยู่หลัง auth เหมือนเดิมทุกประการ
 */
it('หน้าแรกเปิดได้โดยไม่ต้องล็อกอิน', function (): void {
    $this->get('/')->assertOk();
});

it('หน้าหลังบ้านยังพาผู้ใช้ที่ยังไม่ล็อกอินไปหน้า login', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('health check ตอบ 200', function (): void {
    $this->get('/up')->assertOk();
});
