<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security header ตาม spec 8
 *
 * ไม่ตั้งทับค่าที่ response ตั้งมาเองแล้ว เพื่อให้ไฟล์ที่ stream ออกไป
 * (PDF ใบเสนอราคา / xlsx / ไฟล์แนบ) ยังคุม Content-Type ของตัวเองได้
 */
class SecurityHeaders
{
    /**
     * Content-Security-Policy
     *
     * 'unsafe-eval' จำเป็นสำหรับ Alpine.js — Alpine 3 คอมไพล์ expression ใน x-data/x-show
     * ด้วย new Function() การถอดออกต้องเปลี่ยนไปใช้ Alpine CSP build ซึ่งบังคับให้เขียน
     * ทุก expression เป็น method ของ component object คือรื้อ Blade ทุกไฟล์ (ดู ADR-025)
     *
     * 'unsafe-inline' ของ style-src มาจาก inline style ที่จำเป็นจริง เช่น ความสูงของแท่งกราฟ
     * ในหน้ารายงานที่คำนวณจากข้อมูล — ค่าเหล่านั้นเป็นตัวเลขที่ระบบสร้างเอง ไม่ใช่ข้อความจากผู้ใช้
     *
     * @var array<string, string>
     */
    private const POLICY = [
        'default-src' => "'self'",
        'script-src' => "'self' 'unsafe-eval'",
        'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com",
        'font-src' => "'self' data: https://fonts.gstatic.com",
        'img-src' => "'self' data:",
        'connect-src' => "'self'",
        'form-action' => "'self'",
        'frame-ancestors' => "'none'",
        'base-uri' => "'self'",
        'object-src' => "'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            // ปิดอุปกรณ์ที่ระบบไม่ได้ใช้ กันสคริปต์แปลกปลอมขอสิทธิ์จากเบราว์เซอร์
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        // HSTS มีความหมายเฉพาะตอนวิ่งบน HTTPS จริง ตั้งบน http ไว้ก็ไม่มีผลและชวนเข้าใจผิด
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $policy = self::POLICY;

        /*
         * ตอน dev เสิร์ฟ asset จาก Vite dev server คนละพอร์ต ถ้าไม่เปิดช่องให้
         * หน้าเว็บจะไม่มี CSS/JS เลยจนแก้อะไรไม่ได้ — production ไม่มีบรรทัดนี้
         */
        if (app()->environment('local') && is_file(public_path('hot'))) {
            $vite = (string) file_get_contents(public_path('hot'));
            $socket = str_replace(['http://', 'https://'], 'ws://', $vite);

            $policy['script-src'] .= ' '.$vite;
            $policy['style-src'] .= ' '.$vite;
            $policy['connect-src'] .= ' '.$vite.' '.$socket;
        }

        return collect($policy)
            ->map(fn (string $value, string $directive): string => $directive.' '.$value)
            ->implode('; ');
    }
}
