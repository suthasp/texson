<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * สลับภาษา TH/EN จาก ?lang= แล้วจำไว้ใน session (spec 7)
 *
 * รับเฉพาะภาษาที่รองรับจริง เพื่อไม่ให้ค่าจาก query string ไปกำหนด path ของไฟล์แปล
 */
class SetLocale
{
    /** @var array<int, string> */
    private const SUPPORTED = ['th', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->string('lang')->toString();

        if (in_array($requested, self::SUPPORTED, true)) {
            $request->session()->put('locale', $requested);
        }

        $locale = $request->session()->get('locale');

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
