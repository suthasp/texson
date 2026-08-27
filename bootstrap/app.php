<?php

declare(strict_types=1);

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * Exception ของกฎธุรกิจ (ของไม่พอ / เปลี่ยนสถานะข้ามขั้น) ไม่ใช่ error 500
         *
         * ฝั่งเว็บ: เด้งกลับหน้าเดิมพร้อมข้อความ ผู้ใช้แก้แล้วลองใหม่ได้
         * ฝั่ง API: ตอบ status ตามที่ exception กำหนด พร้อมรายละเอียด (spec 6)
         */
        $exceptions->render(function (DomainException $e, Request $request): ?Response {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    ...$e->context(),
                ], $e->httpStatus());
            }

            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        });
    })->create();
