<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * บังคับให้ทุก request ใต้ /api ถูกมองว่าขอ JSON
 *
 * เหตุผล: Laravel ตัดสินรูปแบบ error response จาก header Accept
 * ถ้า client ลืมส่ง `Accept: application/json` มา จะได้ HTML ของหน้า login (302)
 * แทนที่จะเป็น 401 JSON ซึ่งดีบักยากมากฝั่งผู้เรียก
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
